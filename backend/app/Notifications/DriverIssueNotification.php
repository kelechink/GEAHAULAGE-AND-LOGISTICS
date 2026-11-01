<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DriverIssue;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverIssueNotification extends Notification
{
    use Queueable;

    public function __construct(public DriverIssue $issue) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $type = DriverIssue::TYPES[$this->issue->type] ?? $this->issue->type;
        $n = FilamentNotification::make()
            ->title("{$type} reported on {$this->issue->shipment->tracking_code}")
            ->body("{$this->issue->driver->name}: ".($this->issue->note ?: 'No details given.').' Call '.$this->issue->driver->phone.'.');

        return (in_array($this->issue->type, ['breakdown', 'accident'], true) ? $n->danger() : $n->warning())->getDatabaseMessage();
    }
}
