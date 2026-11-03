<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Import\DataImporter;
use App\Enums\Permission;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Go-live data import (PRD 17, Phase 7). Check the file first; save only when every row is valid.
 *
 * @property Form $form
 */
class ImportData extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Import data';

    protected static ?string $title = 'Import data';

    protected static ?string $slug = 'import';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.pages.import-data';

    /** @var array<string, mixed> */
    public array $data = ['type' => 'customers'];

    /** @var array{rows: int, created: int, updated: int, errors: list<string>, committed: bool}|null */
    public ?array $report = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Select::make('type')->label('What are you importing?')->options(DataImporter::TYPES)->required()->live()
                ->afterStateUpdated(fn () => $this->report = null),
            Forms\Components\FileUpload::make('file')->label('CSV or Excel file')->disk('local')->directory('imports')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                ->maxSize(5120)->required()->preserveFilenames()
                ->helperText('First row must be the column names. Download a template to see them.'),
        ]);
    }

    public function check(DataImporter $importer): void
    {
        $this->run($importer, false);
    }

    public function save(DataImporter $importer): void
    {
        $this->run($importer, true);
        if ($this->report['committed'] ?? false) {
            Notification::make()->success()->title("Imported: {$this->report['created']} new, {$this->report['updated']} updated.")->send();
        }
    }

    private function run(DataImporter $importer, bool $commit): void
    {
        $state = $this->form->getState();
        $file = is_array($state['file'] ?? null) ? reset($state['file']) : ($state['file'] ?? null);
        if (! is_string($file) || $file === '') {
            Notification::make()->danger()->title('Choose a file first.')->send();

            return;
        }
        try {
            $this->report = $importer->import((string) $state['type'], Storage::disk('local')->path($file), $commit);
        } catch (RuntimeException $e) {
            $this->report = null;
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')->label('Download template')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn (): StreamedResponse => self::template((string) ($this->data['type'] ?? 'customers'))),
        ];
    }

    public static function template(string $type): StreamedResponse
    {
        $cols = DataImporter::templates()[$type] ?? DataImporter::templates()['customers'];
        $example = DataImporter::examples()[$type] ?? [];

        return response()->streamDownload(function () use ($cols, $example): void {
            $out = fopen('php://output', 'w');
            if ($out !== false) {
                fputcsv($out, $cols);
                fputcsv($out, $example);
                fclose($out);
            }
        }, "gea-{$type}-template.csv", ['Content-Type' => 'text/csv']);
    }
}
