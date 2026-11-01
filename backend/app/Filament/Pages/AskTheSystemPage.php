<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Ai\AskTheSystem;
use App\Enums\Permission;
use Filament\Pages\Page;

/** Plain-language questions answered with whitelisted reports (PRD 12.5). Management only. */
class AskTheSystemPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Assistant';

    protected static ?string $navigationLabel = 'Ask the system';

    protected static ?string $title = 'Ask the system';

    protected static ?string $slug = 'ask';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.ask-the-system';

    public string $question = '';

    /** @var array{title: string, columns: list<string>, rows: list<list<string>>, note: string|null}|null */
    public ?array $answer = null;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u !== null && $u->can(Permission::ReportsView->value) && $u->can(Permission::AiManage->value);
    }

    public function ask(AskTheSystem $system): void
    {
        $this->validate(['question' => ['required', 'string', 'max:300']]);
        $this->answer = $system->ask($this->question);
    }

    public function example(string $q, AskTheSystem $system): void
    {
        $this->question = $q;
        $this->ask($system);
    }
}
