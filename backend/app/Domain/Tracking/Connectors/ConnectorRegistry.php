<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Connectors;

use App\Models\TrackingConnector as ConnectorRow;

/**
 * Builds connectors from their database rows. Phone and Manual are always on; Traccar and the
 * generic webhook only when enabled in Settings → Integrations.
 */
final class ConnectorRegistry
{
    public const NAMES = ['phone', 'manual', 'traccar', 'webhook'];

    public function make(string $name): ?TrackingConnector
    {
        if (in_array($name, ['phone', 'manual'], true)) {
            return new InternalConnector($name);
        }

        $row = ConnectorRow::query()->where('name', $name)->first();
        if ($row === null || ! $row->enabled) {
            return null;
        }

        return match ($name) {
            'webhook' => new GenericWebhookConnector,
            'traccar' => filled($row->settings['url'] ?? null) && filled($row->settings['token'] ?? null)
                ? new TraccarConnector((string) $row->settings['url'], (string) $row->settings['token'])
                : null,
            default => null,
        };
    }

    /** @return list<array{row: ConnectorRow, connector: TrackingConnector}> */
    public function pollable(): array
    {
        $out = [];
        foreach (ConnectorRow::query()->where('enabled', true)->get() as $row) {
            $c = $this->make($row->name);
            if ($c !== null && ! $c->supportsWebhook()) {
                $out[] = ['row' => $row, 'connector' => $c];
            }
        }

        return $out;
    }
}
