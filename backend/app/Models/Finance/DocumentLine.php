<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A line on an invoice or a quote.
 *
 * @property int $id
 * @property string $description
 * @property string $quantity
 * @property int $unit_price_kobo
 * @property int $amount_kobo
 * @property int $sort
 */
class DocumentLine extends Model
{
    protected $fillable = ['description', 'quantity', 'unit_price_kobo', 'amount_kobo', 'sort'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price_kobo' => 'integer', 'amount_kobo' => 'integer', 'sort' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function document(): MorphTo
    {
        return $this->morphTo();
    }
}
