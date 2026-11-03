<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Import\DataImporter;
use Illuminate\Console\Command;

class ImportDataCommand extends Command
{
    protected $signature = 'gea:import {type : customers, trucks, drivers or places} {file : path to a .csv or .xlsx file} {--commit : write the rows (without it, only checks the file)}';

    protected $description = 'Imports customers, trucks, drivers or routes/checkpoints from CSV or Excel. Checks first; add --commit to save.';

    public function handle(DataImporter $importer): int
    {
        $r = $importer->import((string) $this->argument('type'), (string) $this->argument('file'), (bool) $this->option('commit'));
        $this->line("{$r['rows']} rows: {$r['created']} new, {$r['updated']} to update.");
        foreach ($r['errors'] as $e) {
            $this->error($e);
        }
        if ($r['errors'] !== []) {
            $this->warn('Nothing was saved. Fix the rows above and run again.');

            return self::FAILURE;
        }
        $this->info($r['committed'] ? 'Saved.' : 'File looks good. Run again with --commit to save.');

        return self::SUCCESS;
    }
}
