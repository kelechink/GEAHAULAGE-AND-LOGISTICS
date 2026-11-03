<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Crm\LeadPipeline;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\LeadResource\Pages;
use App\Filament\Resources\LeadResource\RelationManagers;
use App\Models\Crm\Lead;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Leads';

    protected static ?int $navigationSort = 10;

    protected static function viewPermission(): Permission
    {
        return Permission::CrmView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::CrmManage;
    }

    /** @return array<string, string> */
    public static function stageOptions(): array
    {
        return collect(LeadStage::cases())->mapWithKeys(fn (LeadStage $s) => [$s->value => $s->label()])->all();
    }

    /** @return array<string, string> */
    public static function sourceOptions(): array
    {
        return collect(LeadSource::cases())->mapWithKeys(fn (LeadSource $s) => [$s->value => $s->label()])->all();
    }

    /** @return array<int, string> */
    public static function salesUsers(): array
    {
        return User::query()->role([Role::Sales->value, Role::Management->value, Role::SuperAdmin->value])
            ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Request')->columns(3)->schema([
                Forms\Components\Select::make('service_interest')->label('Service')
                    ->options(fn () => Service::query()->active()->pluck('title', 'title')->all())->searchable(),
                Forms\Components\TextInput::make('origin')->label('From')->maxLength(160),
                Forms\Components\TextInput::make('destination')->label('To')->maxLength(160),
                Forms\Components\Textarea::make('cargo')->label('Cargo details')->rows(2)->columnSpanFull(),
                Forms\Components\TextInput::make('tonnage')->numeric()->minValue(0)->maxValue(1000)->suffix('t'),
                Forms\Components\DatePicker::make('preferred_date'),
                Forms\Components\TextInput::make('estimated_value')->label('Estimated value')->prefix('₦')
                    ->regex('/^\d{1,3}(,?\d{3})*(\.\d{1,2})?$/')
                    ->formatStateUsing(fn (?Lead $record) => $record?->estimated_value_kobo !== null ? Money::kobo($record->estimated_value_kobo)->toDecimalString() : null)
                    ->dehydrated(false),
            ]),
            Forms\Components\Section::make('Pipeline')->columns(3)->schema([
                Forms\Components\Select::make('source')->options(self::sourceOptions())->required()->disabledOn('edit'),
                Forms\Components\Select::make('owner_user_id')->label('Owner')->options(fn () => self::salesUsers())->searchable(),
                Forms\Components\DateTimePicker::make('next_follow_up_at')->label('Next follow-up')->seconds(false),
                Forms\Components\TextInput::make('intent_score')->numeric()->minValue(0)->maxValue(100)->helperText('0–100. Set by the AI assistant or by you.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Received')->since()->sortable(),
                Tables\Columns\TextColumn::make('contact.name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('contact.phone')->label('Phone')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('service_interest')->label('Service')->limit(26)->toggleable(),
                Tables\Columns\TextColumn::make('route')->label('Route')
                    ->state(fn (Lead $r): string => ($r->origin ?? '?').' → '.($r->destination ?? '?')),
                Tables\Columns\TextColumn::make('stage')->badge()
                    ->formatStateUsing(fn (LeadStage $state): string => $state->label())
                    ->color(fn (LeadStage $state): string => match ($state) {
                        LeadStage::Won => 'success', LeadStage::Lost => 'danger', LeadStage::New => 'warning', default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('source')->formatStateUsing(fn (LeadSource $state): string => $state->label())->toggleable(),
                Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('next_follow_up_at')->label('Follow up by')
                    ->dateTime('D j M, g:ia', 'Africa/Lagos')->sortable()
                    ->color(fn (Lead $r): ?string => $r->isOpen() && $r->next_follow_up_at?->isPast() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage')->options(self::stageOptions())->multiple(),
                Tables\Filters\SelectFilter::make('source')->options(self::sourceOptions()),
                Tables\Filters\SelectFilter::make('owner_user_id')->label('Owner')->options(fn () => self::salesUsers()),
                Tables\Filters\Filter::make('mine')->label('My leads')->query(fn (Builder $query) => $query->where('owner_user_id', auth()->id())),
                Tables\Filters\Filter::make('overdue')->label('Follow-up overdue')
                    ->query(fn (Builder $query) => $query->whereNotIn('stage', ['won', 'lost'])->where('next_follow_up_at', '<', now())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                self::moveAction(Tables\Actions\Action::make('move')),
            ]);
    }

    /**
     * @template T of Tables\Actions\Action|\Filament\Actions\Action
     *
     * @param  T  $action
     * @return T
     */
    public static function moveAction($action)
    {
        return $action
            ->label('Move stage')
            ->icon('heroicon-o-arrows-right-left')
            ->visible(fn (): bool => auth()->user()?->can(Permission::CrmManage->value) ?? false)
            ->form([
                Forms\Components\Select::make('stage')->options(self::stageOptions())->required()->live(),
                Forms\Components\TextInput::make('lost_reason')->label('Why was it lost?')
                    ->required(fn (Forms\Get $get): bool => $get('stage') === LeadStage::Lost->value)
                    ->visible(fn (Forms\Get $get): bool => $get('stage') === LeadStage::Lost->value),
            ])
            ->fillForm(fn (Lead $record): array => ['stage' => $record->stage->value])
            ->action(function (Lead $record, array $data): void {
                app(LeadPipeline::class)->move($record, LeadStage::from($data['stage']), auth()->user(), $data['lost_reason'] ?? null);
                Notification::make()->title('Moved to '.LeadStage::from($data['stage'])->label())->success()->send();
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Contact')->columns(4)->schema([
                Infolists\Components\TextEntry::make('contact.name')->label('Name'),
                Infolists\Components\TextEntry::make('contact.phone')->label('Phone')->copyable(),
                Infolists\Components\TextEntry::make('contact.email')->label('Email')->placeholder('Not given'),
                Infolists\Components\TextEntry::make('company.name')->label('Company')->placeholder('Not given'),
            ]),
            Infolists\Components\Section::make('Request')->columns(4)->schema([
                Infolists\Components\TextEntry::make('service_interest')->label('Service')->placeholder('Not given'),
                Infolists\Components\TextEntry::make('origin')->label('From')->placeholder('Not given'),
                Infolists\Components\TextEntry::make('destination')->label('To')->placeholder('Not given'),
                Infolists\Components\TextEntry::make('tonnage')->suffix(' t')->placeholder('Not given'),
                Infolists\Components\TextEntry::make('cargo')->label('Cargo details')->columnSpanFull()->placeholder('Not given'),
                Infolists\Components\TextEntry::make('estimated_value_kobo')->label('Estimated value')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '' : Money::kobo($state)->format())->placeholder('Not set'),
                Infolists\Components\TextEntry::make('preferred_date')->date()->placeholder('Not given'),
            ]),
            Infolists\Components\Section::make('Pipeline')->columns(4)->schema([
                Infolists\Components\TextEntry::make('stage')->badge()->formatStateUsing(fn (LeadStage $state): string => $state->label()),
                Infolists\Components\TextEntry::make('source')->formatStateUsing(fn (LeadSource $state): string => $state->label()),
                Infolists\Components\TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                Infolists\Components\TextEntry::make('next_follow_up_at')->label('Next follow-up')->dateTime('D j M, g:ia', 'Africa/Lagos')->placeholder('None'),
                Infolists\Components\TextEntry::make('lost_reason')->visible(fn (Lead $r): bool => $r->stage === LeadStage::Lost),
                Infolists\Components\TextEntry::make('customer.name')->label('Customer')->visible(fn (Lead $r): bool => $r->customer_id !== null),
                Infolists\Components\TextEntry::make('ai_summary')->label('AI summary')->columnSpanFull()->visible(fn (Lead $r): bool => filled($r->ai_summary)),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivitiesRelationManager::class,
            RelationManagers\FollowUpsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
