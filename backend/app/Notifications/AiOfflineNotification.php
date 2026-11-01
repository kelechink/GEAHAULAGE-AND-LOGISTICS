<?php

declare(strict_types=1);

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Both AI providers are down or out of free quota (PRD 12.1). The chat keeps taking leads by form. */
class AiOfflineNotification extends Notification
{
    use Queueable;

    public function __construct(public string $reason) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()->warning()
            ->title('The website assistant is offline')
            ->body('Gemini and Groq are both unavailable or out of free quota. Visitors are being asked to leave their details. '.$this->reason)
            ->getDatabaseMessage();
    }
}
