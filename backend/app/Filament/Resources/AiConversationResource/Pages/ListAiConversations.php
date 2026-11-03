<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiConversationResource\Pages;

use App\Domain\Ai\Inbox;
use App\Filament\Resources\AiConversationResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListAiConversations extends ListRecords
{
    protected static string $resource = AiConversationResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $rows = collect(Inbox::usageToday());
        if ($rows->isEmpty()) {
            return 'AI usage today: no requests yet.';
        }

        return 'AI usage today: '.$rows->map(fn (array $r): string => $r['provider'] === 'cache'
            ? $r['cache_hits'].' cached answers'
            : ucfirst($r['provider']).' '.$r['requests'].' requests'.($r['failures'] ? ', '.$r['failures'].' failed' : ''))->implode(' · ').'.';
    }
}
