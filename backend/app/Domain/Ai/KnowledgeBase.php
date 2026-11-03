<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Models\Ai\KnowledgeItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Simple retrieval (PRD 12.2): score active items by keyword overlap with the question and the page,
 * no vector database. Contact details are always included so the assistant can offer a next step.
 */
final class KnowledgeBase
{
    private const STOP = ['the', 'a', 'an', 'and', 'or', 'to', 'of', 'for', 'in', 'on', 'is', 'are', 'do', 'you', 'your', 'i', 'my',
        'me', 'we', 'can', 'what', 'how', 'with', 'from', 'it', 'be', 'abeg', 'una', 'dey', 'wey', 'na', 'go', 'fit', 'pls', 'please'];

    /** Pidgin and common spellings mapped to the words used in the knowledge base. */
    private const SYNONYMS = ['moto' => 'truck', 'motor' => 'truck', 'trailer' => 'truck', 'lorry' => 'truck', 'tipper' => 'truck',
        'carry' => 'haulage', 'move' => 'haulage', 'transport' => 'haulage', 'load' => 'cargo', 'goods' => 'cargo', 'kudi' => 'price',
        'cost' => 'price', 'much' => 'price', 'rate' => 'price', 'track' => 'tracking', 'where' => 'tracking', 'job' => 'careers',
        'work' => 'careers', 'diesel' => 'petroleum', 'fuel' => 'petroleum', 'ago' => 'petroleum', 'pms' => 'petroleum', 'container' => 'container'];

    /** @return Collection<int, KnowledgeItem> */
    public function all(): Collection
    {
        /** @var Collection<int, KnowledgeItem> */
        return Cache::remember('kb:all', 600, fn () => KnowledgeItem::query()->where('is_active', true)->orderBy('sort')->get());
    }

    /** @return Collection<int, KnowledgeItem> */
    public function relevant(string $question, ?string $page = null, int $limit = 6): Collection
    {
        $terms = self::terms($question.' '.self::pageHint($page));
        $items = $this->all();

        $scored = $items->map(function (KnowledgeItem $item) use ($terms): array {
            $hay = self::terms($item->title.' '.$item->keywords.' '.$item->category);
            $body = self::terms($item->body);
            $score = count(array_intersect($terms, $hay)) * 3 + count(array_intersect($terms, $body));

            return ['item' => $item, 'score' => $score];
        })->filter(fn (array $r): bool => $r['score'] > 0)->sortByDesc('score')->take($limit)->pluck('item');

        $contact = $items->where('category', 'contact');

        return $scored->merge($contact)->unique('id')->values();
    }

    /** Best single FAQ-style item for a topic (the get_faq tool). */
    public function find(string $topic): ?KnowledgeItem
    {
        return $this->relevant($topic, null, 1)->first();
    }

    /** @param  Collection<int, KnowledgeItem>  $items */
    public static function asContext(Collection $items): string
    {
        return $items->map(fn (KnowledgeItem $i): string => "## {$i->title}\n{$i->body}")->implode("\n\n");
    }

    /** @return list<string> */
    public static function terms(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text))) ?: [];
        $out = [];
        foreach ($words as $w) {
            if (strlen($w) < 2 || in_array($w, self::STOP, true)) {
                continue;
            }
            $w = self::SYNONYMS[$w] ?? $w;
            $out[] = Str::singular($w);
        }

        return array_values(array_unique($out));
    }

    private static function pageHint(?string $page): string
    {
        if ($page === null) {
            return '';
        }
        $path = (string) parse_url($page, PHP_URL_PATH);

        return str_replace(['/', '-'], ' ', $path);
    }
}
