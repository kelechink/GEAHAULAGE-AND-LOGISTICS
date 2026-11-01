<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local and staging seed. Never run in production: it creates demo users with a known password.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, WebsiteContentSeeder::class, PlacesSeeder::class]);

        $demoUsers = [
            Role::SuperAdmin->value => ['FigureWeb Admin', 'admin@example.test'],
            Role::Management->value => ['Godwin Atueyi (demo)', 'md@example.test'],
            Role::Operations->value => ['Ops Officer (demo)', 'ops@example.test'],
            Role::Sales->value => ['Sales Officer (demo)', 'sales@example.test'],
            Role::Finance->value => ['Accounts Officer (demo)', 'finance@example.test'],
            Role::Driver->value => ['Driver (demo)', 'driver@example.test'],
            Role::Customer->value => ['Customer (demo)', 'customer@example.test'],
        ];

        foreach ($demoUsers as $role => [$name, $email]) {
            User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'email_verified_at' => now()],
            )->syncRoles([$role]);
        }

        User::query()->firstOrCreate(
            ['email' => 'sales2@example.test'],
            ['name' => 'Sales Officer 2 (demo)', 'password' => 'password', 'email_verified_at' => now()],
        )->syncRoles([Role::Sales->value]);

        $this->call([DemoCrmSeeder::class, DemoOperationsSeeder::class]);
    }
}
