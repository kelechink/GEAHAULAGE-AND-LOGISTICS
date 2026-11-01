<?php

declare(strict_types=1);

namespace App\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Rule-based real-time alert in the admin bell (PRD 12.5). No AI involved. */
class OpsAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $level = 'warning',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $n = FilamentNotification::make()->title($this->title)->body($this->body)->status($this->level);
        if ($this->url !== null) {
            $n->actions([Action::make('open')->label('Open')->url($this->url)]);
        }

        return $n->getDatabaseMessage();
    }
}
