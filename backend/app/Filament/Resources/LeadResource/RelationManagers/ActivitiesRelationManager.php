<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Domain\Crm\ActivityLogger;
use App\Enums\Permission;
use App\Models\Crm\Lead;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activity';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('at')->label('When')->dateTime('D j M, g:ia', 'Africa/Lagos'),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state): string => ActivityLogger::TYPES[$state] ?? str_replace('_', ' ', ucfirst($state))),
                Tables\Columns\TextColumn::make('body')->wrap(),
                Tables\Columns\TextColumn::make('user.name')->label('By')->placeholder('System'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('log')
                    ->label('Log activity')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => auth()->user()?->can(Permission::CrmManage->value) ?? false)
                    ->form([
                        Forms\Components\Select::make('type')->options(ActivityLogger::TYPES)->required()->default('call'),
                        Forms\Components\Textarea::make('body')->label('What happened?')->required()->rows(3),
                    ])
                    ->action(function (array $data): void {
                        /** @var Lead $lead */
                        $lead = $this->getOwnerRecord();
                        app(ActivityLogger::class)->log($lead, $data['type'], $data['body'], auth()->user());
                    }),
            ]);
    }
}
