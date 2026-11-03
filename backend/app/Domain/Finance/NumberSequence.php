<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free, sequential document numbers per series and year (PRD 10.3): GEA-INV-2026-00001.
 * The counter row is locked for the length of the caller's transaction, so concurrent issues queue up.
 */
final class NumberSequence
{
    public const INVOICE = 'INV';

    public const PROFORMA = 'PRO';

    public const CREDIT_NOTE = 'CRN';

    public function next(string $series, ?int $year = null): string
    {
        $year ??= (int) now()->timezone('Africa/Lagos')->format('Y');

        return DB::transaction(function () use ($series, $year): string {
            $row = DB::table('counters')->where('series', $series)->where('year', $year)->lockForUpdate()->first();
            if ($row === null) {
                DB::table('counters')->insertOrIgnore(['series' => $series, 'year' => $year, 'value' => 0]);
                $row = DB::table('counters')->where('series', $series)->where('year', $year)->lockForUpdate()->first();
            }
            $value = (int) $row->value + 1;
            DB::table('counters')->where('id', $row->id)->update(['value' => $value]);

            return sprintf('GEA-%s-%d-%05d', $series, $year, $value);
        });
    }
}
