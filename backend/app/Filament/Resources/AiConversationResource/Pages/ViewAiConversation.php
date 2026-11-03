<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiConversationResource\Pages;

use App\Domain\Ai\Inbox;
use App\Filament\Resources\AiConversationResource;
use App\Filament\Resources\LeadResource;
use App\Models\Ai\Conversation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Transcript, take over and reply. Replies reach the visitor through the widget's polling (PRD 12.4).
 *
 * @property Conversation $record
 */
class ViewAiConversation extends ViewRecord
{
    protected static string $resource = AiConversationResource::class;

    protected static string $view = 'filament.ai.conversation';

    public string $reply = '';

    public function getTitle(): string
    {
        return 'Chat with '.($this->record->visitor_name ?? 'a website visitor');
    }

    public function send(Inbox $inbox): void
    {
        $this->validate(['reply' => ['required', 'string', 'max:2000']]);
        $inbox->reply($this->record, $this->user(), $this->reply);
        $this->reply = '';
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        $c = $this->record;

        return [
            Action::make('takeOver')->label('Take over')->icon('heroicon-o-hand-raised')->color('warning')
                ->visible(fn (): bool => $c->status !== 'human' || $c->assigned_user_id !== auth()->id())
                ->action(function (Inbox $inbox): void {
                    $inbox->takeOver($this->record, $this->user());
                    Notification::make()->success()->title('You are now replying. The visitor sees that a team member joined.')->send();
                }),
            Action::make('handBack')->label('Hand back to assistant')->icon('heroicon-o-cpu-chip')->color('gray')
                ->visible(fn (): bool => $c->isHuman())
                ->action(fn (Inbox $inbox) => $inbox->handBack($this->record, $this->user())),
            Action::make('close')->label('Close chat')->icon('heroicon-o-x-circle')->color('gray')->requiresConfirmation()
                ->visible(fn (): bool => $c->status !== 'closed')
                ->action(fn (Inbox $inbox) => $inbox->close($this->record, $this->user())),
            Action::make('lead')->label('Open lead')->icon('heroicon-o-arrow-top-right-on-square')
                ->visible(fn (): bool => $c->lead_id !== null)
                ->url(fn (): ?string => $c->lead_id !== null ? LeadResource::getUrl('edit', ['record' => $c->lead_id]) : null),
        ];
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
