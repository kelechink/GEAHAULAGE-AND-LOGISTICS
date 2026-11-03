<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class MakeAdminCommand extends Command
{
    protected $signature = 'gea:make-admin {name} {email} {--role=super_admin : Role to give the user}';

    protected $description = 'Create a back-office user (default: Super Admin). Prompts for the password.';

    public function handle(): int
    {
        $role = Role::tryFrom((string) $this->option('role'));
        if ($role === null) {
            $this->error('Unknown role. Use one of: '.implode(', ', array_keys(Role::options())));

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $password = (string) $this->secret('Password (min 12 characters)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'min:12']],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => $password,
        ]);
        $user->assignRole($role->value);

        $this->info("Created {$role->label()} {$email}.");

        return self::SUCCESS;
    }
}
