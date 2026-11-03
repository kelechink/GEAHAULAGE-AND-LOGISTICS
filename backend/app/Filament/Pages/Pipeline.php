<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Crm\LeadPipeline;
use App\Enums\LeadStage;
use App\Enums\Permission;
use App\Models\Crm\Lead;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Kanban board by stage (PRD 9.2). Cards can be dragged between columns or moved with their stage buttons.
 */
class Pipeline extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Pipeline';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.pipeline';

    public bool $mineOnly = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::CrmView->value) ?? false;
    }

    public function canManage(): bool
    {
        return auth()->user()?->can(Permission::CrmManage->value) ?? false;
    }

    /**
     * Won and lost show only the last 30 days so the board stays readable.
     *
     * @return array<string, array{label: string, leads: Collection<int, Lead>, total: string}>
     */
    public function getColumns(): array
    {
        $leads = Lead::query()
            ->with(['contact', 'owner'])
            ->when($this->mineOnly, fn ($q) => $q->where('owner_user_id', auth()->id()))
            ->where(fn ($q) => $q->whereNotIn('stage', ['won', 'lost'])->orWhere('updated_at', '>=', now()->subDays(30)))
            ->orderBy('next_follow_up_at')
            ->get()
            ->groupBy(fn (Lead $l): string => $l->stage->value);

        $columns = [];
        foreach (LeadStage::cases() as $stage) {
            /** @var Collection<int, Lead> $items */
            $items = $leads->get($stage->value, collect());
            $columns[$stage->value] = [
                'label' => $stage->label(),
                'leads' => $items,
                'total' => Money::kobo((int) $items->sum('estimated_value_kobo'))->format(),
            ];
        }

        return $columns;
    }

    public function moveLead(int $leadId, string $stage): void
    {
        abort_unless($this->canManage(), 403);
        $to = LeadStage::tryFrom($stage);
        if ($to === null) {
            return;
        }

        if ($to === LeadStage::Lost) {
            $this->mountAction('markLost', ['lead' => $leadId]);

            return;
        }

        $lead = Lead::query()->findOrFail($leadId);
        app(LeadPipeline::class)->move($lead, $to, auth()->user());
        Notification::make()->title($lead->contact->name.' moved to '.$to->label())->success()->send();
    }

    public function markLostAction(): Action
    {
        return Action::make('markLost')
            ->modalHeading('Mark lead as lost')
            ->form([Forms\Components\TextInput::make('reason')->label('Why was it lost?')->required()->maxLength(255)])
            ->action(function (array $data, array $arguments): void {
                abort_unless($this->canManage(), 403);
                $lead = Lead::query()->findOrFail($arguments['lead']);
                app(LeadPipeline::class)->move($lead, LeadStage::Lost, auth()->user(), $data['reason']);
                Notification::make()->title('Marked as lost')->send();
            });
    }
}
