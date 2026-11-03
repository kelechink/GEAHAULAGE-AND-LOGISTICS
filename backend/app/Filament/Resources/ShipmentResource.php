<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Documents\DocumentLinks;
use App\Domain\Shipping\ShipmentService;
use App\Domain\Shipping\ShippingException;
use App\Domain\Tracking\ManualUpdate;
use App\Enums\Permission;
use App\Enums\ShipmentStage;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\ShipmentResource\Pages;
use App\Filament\Resources\ShipmentResource\RelationManagers;
use App\Models\Driver;
use App\Models\Place;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use App\Models\Truck;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ShipmentResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Shipment::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Shipments';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'tracking_code';

    protected static function viewPermission(): Permission
    {
        return Permission::ShipmentsView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::ShipmentsManage;
    }

    /** @return array<int, string> */
    public static function truckOptions(?Shipment $shipment, mixed $tonnage): array
    {
        $t = is_numeric($tonnage) ? (float) $tonnage : null;

        return app(ShipmentService::class)->eligibleTrucks($shipment, $t)
            ->mapWithKeys(fn (Truck $tr) => [$tr->id => "{$tr->registration} · ".(Truck::TYPES[$tr->type] ?? $tr->type)." · {$tr->capacity_tonnes} t"])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Booking')->columns(2)->schema([
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('phone')->tel(),
                        Forms\Components\TextInput::make('email')->email(),
                    ]),
                Forms\Components\TextInput::make('waybill_number')->maxLength(40),
                Forms\Components\TextInput::make('cargo_description')->label('Cargo')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\TextInput::make('tonnage')->numeric()->minValue(0)->maxValue(60)->suffix('t')->live(onBlur: true),
                Forms\Components\DatePicker::make('pickup_date'),
                Forms\Components\TextInput::make('origin')->label('Pickup town')->required()->maxLength(160)
                    ->datalist(fn () => Place::query()->orderBy('name')->get()->map->label()->all()),
                Forms\Components\TextInput::make('destination')->label('Delivery town')->required()->maxLength(160)
                    ->datalist(fn () => Place::query()->orderBy('name')->get()->map->label()->all()),
                Forms\Components\Textarea::make('notes')->label('Private notes')->helperText('Staff only. Never shown to the customer.')->rows(2)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Truck and driver')->columns(2)->visibleOn('create')->schema([
                Forms\Components\Select::make('truck_id')->label('Truck')
                    ->options(fn (Forms\Get $get) => self::truckOptions(null, $get('tonnage')))
                    ->helperText('Only available trucks big enough for the load. You can assign one later.')
                    ->searchable()->live(),
                Forms\Components\Select::make('driver_id')->label('Driver')
                    ->options(fn () => Driver::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->helperText("Leave empty to use the truck's usual driver.")
                    ->searchable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'truck', 'driver']))
            ->columns([
                Tables\Columns\TextColumn::make('tracking_code')->label('Code')->searchable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('customer.name')->searchable()->limit(24),
                Tables\Columns\TextColumn::make('route')->state(fn (Shipment $r): string => "{$r->origin} → {$r->destination}"),
                Tables\Columns\TextColumn::make('stage')->badge()
                    ->formatStateUsing(fn (ShipmentStage $state): string => $state->label())
                    ->color(fn (ShipmentStage $state): string => match ($state) {
                        ShipmentStage::Delayed, ShipmentStage::Cancelled => 'danger',
                        ShipmentStage::Delivered, ShipmentStage::Closed => 'success',
                        ShipmentStage::InTransit, ShipmentStage::Arrived => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('truck.registration')->label('Truck')->placeholder('Not assigned'),
                Tables\Columns\TextColumn::make('driver.name')->label('Driver')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('last_update_at')->label('Last update')->since()->sortable()
                    ->color(fn (Shipment $r): ?string => $r->isStale() ? 'danger' : null)
                    ->description(fn (Shipment $r): ?string => $r->isStale() ? 'Stale' : null),
                Tables\Columns\TextColumn::make('pickup_date')->date('j M')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage')->options(ShipmentStage::options())->multiple(),
                Tables\Filters\Filter::make('active')->label('Active only')->default()
                    ->query(fn (Builder $query) => $query->whereIn('stage', ShipmentStage::activeValues())),
                Tables\Filters\Filter::make('stale')->label('Stale (no update in 60 min)')
                    ->query(fn (Builder $query) => $query->whereIn('stage', ['in_transit', 'delayed'])
                        ->where(fn ($q) => $q->whereNull('last_update_at')->orWhere('last_update_at', '<', now()->subHour()))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                self::updateAction(Tables\Actions\Action::make('update')),
            ]);
    }

    /**
     * The manual update form (PRD 8.3, 10.1). Used on the table, the view page and the console.
     *
     * @template T of Tables\Actions\Action|\Filament\Actions\Action
     *
     * @param  T  $action
     * @return T
     */
    public static function updateAction($action)
    {
        return $action
            ->label('Update')
            ->icon('heroicon-o-map-pin')
            ->visible(fn (Shipment $record): bool => $record->stage->isActive() && (auth()->user()?->can(Permission::TrackingUpdate->value) ?? false))
            ->modalHeading(fn (Shipment $record): string => "Update {$record->tracking_code}")
            ->form(fn (Shipment $record): array => [
                Forms\Components\Select::make('stage')->label('New stage')
                    ->options(collect($record->stage->next())->mapWithKeys(fn (ShipmentStage $s) => [$s->value => $s->label()])->all())
                    ->placeholder('No stage change')->live(),
                Forms\Components\Select::make('place_id')->label('Checkpoint or town')
                    ->options(fn () => Place::query()->orderBy('name')->get()->mapWithKeys(fn (Place $p) => [$p->id => $p->label()])->all())
                    ->searchable(),
                Forms\Components\TextInput::make('town')->label('Or type a town')->maxLength(120)
                    ->helperText('Looked up on the map automatically.'),
                Forms\Components\Textarea::make('note')->rows(2)
                    ->placeholder('e.g. Driver called from Ore, 2:15pm')
                    ->required(fn (Forms\Get $get): bool => in_array($get('stage'), ['delayed', 'cancelled'], true))
                    ->helperText(fn (Forms\Get $get): ?string => match ($get('stage')) {
                        'delayed' => 'Say why it is delayed. The customer sees this.',
                        'cancelled' => 'Give the reason for cancelling.',
                        default => null,
                    }),
                Forms\Components\Toggle::make('public')->label('Show this update to the customer')->default(true),
                Forms\Components\Fieldset::make('Proof of delivery')
                    ->visible(fn (Forms\Get $get): bool => $get('stage') === ShipmentStage::Delivered->value && $record->proofs()->doesntExist())
                    ->schema([
                        Forms\Components\TextInput::make('receiver_name')->label('Received by')->required(),
                        Forms\Components\FileUpload::make('document_path')->label('Signed waybill (scan or photo)')->required()
                            ->disk('local')->directory('pod')->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])->maxSize(8192),
                    ]),
            ])
            ->action(function (Shipment $record, array $data): void {
                $stage = filled($data['stage'] ?? null) ? ShipmentStage::from($data['stage']) : null;
                try {
                    if ($stage === ShipmentStage::Delivered && filled($data['receiver_name'] ?? null)) {
                        $record->proofs()->create([
                            'receiver_name' => $data['receiver_name'],
                            'document_path' => $data['document_path'] ?? null,
                            'source' => 'manual',
                            'user_id' => auth()->id(),
                        ]);
                    }
                    app(ManualUpdate::class)->apply(
                        $record, auth()->user(), $stage,
                        filled($data['place_id'] ?? null) ? (int) $data['place_id'] : null,
                        $data['town'] ?? null, null, $data['note'] ?? null, (bool) ($data['public'] ?? true),
                    );
                    Notification::make()->title('Update saved')->success()->send();
                } catch (ShippingException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(4)->schema([
                Infolists\Components\TextEntry::make('tracking_code')->label('Tracking code')->copyable()->weight('bold'),
                Infolists\Components\TextEntry::make('stage')->badge()->formatStateUsing(fn (ShipmentStage $state): string => $state->label()),
                Infolists\Components\TextEntry::make('customer.name')->label('Customer'),
                Infolists\Components\TextEntry::make('last_update_at')->label('Last update')->since()->placeholder('None yet'),
                Infolists\Components\TextEntry::make('origin')->label('From'),
                Infolists\Components\TextEntry::make('destination')->label('To'),
                Infolists\Components\TextEntry::make('cargo_description')->label('Cargo'),
                Infolists\Components\TextEntry::make('tonnage')->suffix(' t')->placeholder('Not given'),
                Infolists\Components\TextEntry::make('truck.registration')->label('Truck')->placeholder('Not assigned'),
                Infolists\Components\TextEntry::make('driver.name')->label('Driver')->placeholder('Not assigned'),
                Infolists\Components\TextEntry::make('driver.phone')->label('Driver phone')->placeholder('—')->copyable(),
                Infolists\Components\TextEntry::make('waybill_number')->placeholder('—'),
                Infolists\Components\TextEntry::make('delay_reason')->visible(fn (Shipment $r): bool => $r->stage === ShipmentStage::Delayed)->color('danger'),
                Infolists\Components\TextEntry::make('cancel_reason')->visible(fn (Shipment $r): bool => $r->stage === ShipmentStage::Cancelled),
                Infolists\Components\TextEntry::make('notes')->label('Private notes')->columnSpanFull()->placeholder('—'),
            ]),
            Infolists\Components\Section::make('Map')->schema([
                Infolists\Components\ViewEntry::make('map')->view('filament.shipment-map')->columnSpanFull(),
            ]),
            Infolists\Components\Section::make('Proof of delivery')->visible(fn (Shipment $r): bool => $r->proofs()->exists())->schema([
                Infolists\Components\RepeatableEntry::make('proofs')->label('')->columns(3)->schema([
                    Infolists\Components\TextEntry::make('receiver_name')->label('Received by'),
                    Infolists\Components\TextEntry::make('source'),
                    Infolists\Components\TextEntry::make('created_at')->label('When')->dateTime('D j M, g:ia', 'Africa/Lagos'),
                    // Short-lived signed links so staff can open the photo, signature or scanned waybill.
                    Infolists\Components\TextEntry::make('files')->label('Files')->columnSpanFull()->html()
                        ->state(fn (ProofOfDelivery $record): string => collect(DocumentLinks::proofLinks($record, 30))
                            ->map(fn (string $url, string $label): string => '<a class="text-primary-600 underline" target="_blank" rel="noopener" href="'.e($url).'">'.e($label).'</a>')
                            ->implode(' · ') ?: 'No files'),
                ]),
            ]),
        ]);
    }

    public static function whatsappMessage(Shipment $s): string
    {
        return "Hello {$s->customer->name}, your GEA shipment {$s->tracking_code} from {$s->origin} to {$s->destination} is {$s->stage->label()}. Track it here: ".route('track.show', $s->tracking_code);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\EventsRelationManager::class, RelationManagers\IssuesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'view' => Pages\ViewShipment::route('/{record}'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
        ];
    }

    public static function proofUrl(?string $path): ?string
    {
        return $path !== null && Storage::disk('local')->exists($path) ? $path : null;
    }
}
