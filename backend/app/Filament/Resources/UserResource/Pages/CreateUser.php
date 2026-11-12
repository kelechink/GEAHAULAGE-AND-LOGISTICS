<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Domain\Auth\StaffInvite;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private bool $invite = false;

    /** No password typed: give the account a random one and email the person a link to choose their own. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            $data['password'] = Str::random(48);
            $this->invite = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;
        $user->unsetRelation('roles');
        if ($this->invite && $user->is_active && $user->canAccessPanel(filament()->getPanel('admin'))) {
            app(StaffInvite::class)->send($user, auth()->user());
            Notification::make()->success()->title("Invitation emailed to {$user->email}.")->send();
        }
    }
}
