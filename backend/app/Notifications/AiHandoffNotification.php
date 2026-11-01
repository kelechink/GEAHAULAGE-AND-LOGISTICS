<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ai\Conversation;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** A website visitor needs a person (PRD 12.4). */
class AiHandoffNotification extends Notification
{
    use Queueable;

    public function __construct(public Conversation $conversation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()->warning()
            ->title('A website visitor needs a person')
            ->body(($this->conversation->visitor_name ?? 'A visitor').': '.($this->conversation->handoff_reason ?? 'asked for help').'.')
            ->actions([Action::make('open')->label('Open chat')->url(route('filament.admin.resources.ai-conversations.view', $this->conversation))])
            ->getDatabaseMessage();
    }
}
