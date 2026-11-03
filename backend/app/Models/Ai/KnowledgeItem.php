<?php

declare(strict_types=1);

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One fact the assistant may use (PRD 12.2). Editable in admin.
 *
 * @property int $id
 * @property string $category
 * @property string $title
 * @property string|null $keywords
 * @property string $body
 * @property bool $is_active
 * @property int $sort
 */
class KnowledgeItem extends Model
{
    public const CATEGORIES = [
        'services' => 'Services', 'fleet' => 'Fleet and capacity', 'coverage' => 'Coverage', 'process' => 'How it works',
        'faq' => 'FAQ', 'contact' => 'Contact and office hours', 'careers' => 'Careers', 'company' => 'Values, mission and vision',
    ];

    protected $fillable = ['category', 'title', 'keywords', 'body', 'is_active', 'sort'];

    protected $attributes = ['is_active' => true, 'sort' => 0];

    protected static function booted(): void
    {
        // The assistant caches the active list for 10 minutes; edits apply at once.
        static::saved(fn () => Cache::forget('kb:all'));
        static::deleted(fn () => Cache::forget('kb:all'));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer'];
    }
}
