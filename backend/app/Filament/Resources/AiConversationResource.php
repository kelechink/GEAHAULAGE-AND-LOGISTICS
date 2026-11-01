<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\AiConversationResource\Pages;
use App\Models\Ai\Conversation;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** "AI Conversations" inbox (PRD 12.4). */
class AiConversationResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Conversation::class;

    protected static ?string $slug = 'ai-conversations';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Assistant';

    protected static ?string $navigationLabel = 'Conversations';

    protected static ?string $modelLabel = 'conversation';

    protected static ?int $navigationSort = 10;

    protected static function viewPermission(): Permission
    {
        return Permission::AiInbox;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Conversation::query()->where('status', 'needs_human')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('messages')->whereHas('messages', fn ($m) => $m->where('role', 'visitor')))
            ->defaultSort('last_message_at', 'desc')
            ->poll('15s')
            ->columns([
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => Conversation::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'needs_human' => 'warning', 'human' => 'success', 'closed' => 'gray', default => 'info'
                    }),
                Tables\Columns\TextColumn::make('visitor_name')->label('Visitor')->placeholder('Anonymous')->searchable()
                    ->description(fn (Conversation $r): ?string => $r->visitor_phone),
                Tables\Columns\TextColumn::make('handoff_reason')->label('Why')->limit(60)->placeholder('—')->wrap(),
                Tables\Columns\TextColumn::make('page')->limit(30)->color('gray'),
                Tables\Columns\TextColumn::make('turns')->label('Turns')->numeric(),
                Tables\Columns\TextColumn::make('lead_id')->label('Lead')->formatStateUsing(fn (?int $state): string => $state ? 'Lead #'.$state : '')->placeholder('—')
                    ->url(fn (Conversation $r): ?string => $r->lead_id ? LeadResource::getUrl('edit', ['record' => $r->lead_id]) : null),
                Tables\Columns\TextColumn::make('last_message_at')->label('Last message')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Conversation::STATUSES),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label('Open')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiConversations::route('/'),
            'view' => Pages\ViewAiConversation::route('/{record}'),
        ];
    }
}
