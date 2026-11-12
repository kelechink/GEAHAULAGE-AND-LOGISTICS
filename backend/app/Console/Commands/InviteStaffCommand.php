<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auth\StaffInvite;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class InviteStaffCommand extends Command
{
    protected $signature = 'gea:invite-staff {email} {name?} {--role=* : Back-office role(s), e.g. --role=operations --role=finance} {--phone=} {--resend : Re-send the invitation to an existing user}';

    protected $description = 'Create a back-office user and email them a branded link to choose their own password.';

    public function handle(StaffInvite $invite): int
    {
        try {
            if ($this->option('resend')) {
                $user = User::query()->where('email', strtolower((string) $this->argument('email')))->firstOrFail();
                $invite->send($user);
                $this->info("Invitation re-sent to {$user->email}.");

                return self::SUCCESS;
            }

            $roles = [];
            foreach ((array) $this->option('role') as $value) {
                $role = Role::tryFrom((string) $value);
                if ($role === null) {
                    $this->error("Unknown role {$value}. Use one of: ".implode(', ', array_keys(Role::options())));

                    return self::FAILURE;
                }
                $roles[] = $role;
            }
            $user = $invite->create((string) $this->argument('name'), (string) $this->argument('email'), $roles, $this->option('phone') ?: null);
            $this->info("Created {$user->email} and sent the invitation.");

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
