<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Enums\Permission;
use App\Filament\Resources\LeadResource;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'followUps';

    protected static ?string $title = 'Follow-ups';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    private static function canManage(): bool
    {
        return auth()->user()?->can(Permission::CrmManage->value) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_at')
            ->columns([
                Tables\Columns\TextColumn::make('due_at')->label('Due')->dateTime('D j M, g:ia', 'Africa/Lagos')
                    ->color(fn (FollowUp $r): ?string => $r->status === 'open' && $r->due_at->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('assignee.name')->label('Assigned to')->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success', 'missed' => 'danger', default => 'warning'
                    }),
                Tables\Columns\TextColumn::make('note')->wrap(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add')
                    ->label('Add follow-up')
                    ->icon('heroicon-o-calendar')
                    ->visible(fn (): bool => self::canManage())
                    ->form([
                        Forms\Components\DateTimePicker::make('due_at')->label('Due')->required()->seconds(false)->default(now()->addDay()),
                        Forms\Components\Select::make('assigned_to')->label('Assign to')->options(fn () => LeadResource::salesUsers())->default(auth()->id()),
                        Forms\Components\Textarea::make('note')->rows(2),
                    ])
                    ->action(function (array $data): void {
                        /** @var Lead $lead */
                        $lead = $this->getOwnerRecord();
                        $lead->followUps()->create($data + ['status' => 'open']);
                        $lead->update(['next_follow_up_at' => $lead->followUps()->where('status', 'open')->min('due_at')]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('done')
                    ->label('Mark done')
                    ->icon('heroicon-o-check')
                    ->visible(fn (FollowUp $r): bool => $r->status !== 'done' && self::canManage())
                    ->action(function (FollowUp $record): void {
                        $record->update(['status' => 'done']);
                        $lead = $record->lead;
                        $lead->update(['next_follow_up_at' => $lead->followUps()->where('status', 'open')->min('due_at')]);
                    }),
            ]);
    }
}
