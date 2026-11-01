<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\FollowUpResource\Pages;
use App\Models\Crm\FollowUp;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FollowUpResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = FollowUp::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Follow-ups';

    protected static ?int $navigationSort = 5;

    protected static function viewPermission(): Permission
    {
        return Permission::CrmView;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = FollowUp::query()->overdue()->when(! auth()->user()?->can(Permission::CrmView->value), fn ($q) => $q->whereRaw('0 = 1'))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_at')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('lead.contact'))
            ->columns([
                Tables\Columns\TextColumn::make('due_at')->label('Due')->dateTime('D j M, g:ia', 'Africa/Lagos')->sortable()
                    ->color(fn (FollowUp $r): ?string => $r->status === 'open' && $r->due_at->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('lead.contact.name')->label('Lead')->searchable()
                    ->url(fn (FollowUp $r): string => LeadResource::getUrl('view', ['record' => $r->lead_id])),
                Tables\Columns\TextColumn::make('lead.contact.phone')->label('Phone')->copyable(),
                Tables\Columns\TextColumn::make('assignee.name')->label('Assigned to')->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success', 'missed' => 'danger', default => 'warning'
                    }),
                Tables\Columns\TextColumn::make('note')->limit(50)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['open' => 'Open', 'done' => 'Done', 'missed' => 'Missed'])->default('open'),
                Tables\Filters\Filter::make('mine')->label('Assigned to me')->query(fn (Builder $query) => $query->where('assigned_to', auth()->id())),
                Tables\Filters\Filter::make('overdue')->label('Overdue')->query(fn (Builder $query) => $query->where('status', 'open')->where('due_at', '<', now())),
            ])
            ->actions([
                Tables\Actions\Action::make('done')->label('Mark done')->icon('heroicon-o-check')
                    ->visible(fn (FollowUp $r): bool => $r->status !== 'done' && (auth()->user()?->can(Permission::CrmManage->value) ?? false))
                    ->action(function (FollowUp $record): void {
                        $record->update(['status' => 'done']);
                        $record->lead->update(['next_follow_up_at' => $record->lead->followUps()->where('status', 'open')->min('due_at')]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFollowUps::route('/')];
    }
}
