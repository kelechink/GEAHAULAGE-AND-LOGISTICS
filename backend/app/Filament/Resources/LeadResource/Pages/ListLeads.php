<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadResource\Pages;

use App\Domain\Crm\LeadCaptureService;
use App\Enums\LeadSource;
use App\Enums\Permission;
use App\Filament\Resources\LeadResource;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // PRD 9.2: phone and walk-in enquiries logged in a 3-field quick-add.
            Action::make('quickAdd')
                ->label('Quick add lead')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => auth()->user()?->can(Permission::CrmManage->value) ?? false)
                ->form([
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\TextInput::make('phone')->tel()->required()->regex('/^[+\d][\d\s\-()]{6,19}$/'),
                    Forms\Components\Select::make('source')->required()->default(LeadSource::Phone->value)->options([
                        LeadSource::Phone->value => 'Phone',
                        LeadSource::WalkIn->value => 'Walk-in',
                        LeadSource::WhatsappClick->value => 'WhatsApp',
                        LeadSource::Referral->value => 'Referral',
                    ]),
                    Forms\Components\Textarea::make('note')->label('What do they need?')->rows(2),
                ])
                ->action(function (array $data): void {
                    $lead = app(LeadCaptureService::class)->quickAdd($data['name'], $data['phone'], LeadSource::from($data['source']), $data['note'] ?? null);
                    Notification::make()->title('Lead added')->body('Follow-up assigned to '.($lead->owner->name ?? 'nobody yet').'.')->success()->send();
                }),
        ];
    }
}
