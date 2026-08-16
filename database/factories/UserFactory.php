<?php

namespace Database\Factories;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'organisation_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['nep_admin', 'nep_coordinator', 'member_org']),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        // Test fixtures routinely create users via
        // User::factory()->create(['role' => 'nep_admin' | 'nep_coordinator' | 'member_org'])
        // without going through the app's seeder. Mirror what a real
        // deployment always has — the built-in role + its real permission
        // set (see RolePermissionSeeder::ensureBuiltInRole()) — so
        // factory-made users keep working under the permission-based
        // middleware. This is fixture bootstrapping, not an authorization
        // shortcut: the actual checks (User::hasPermission()/isSuperAdmin())
        // still only ever read these Role/Permission rows.
        return $this->afterCreating(function (\App\Models\User $user) {
            $role = RolePermissionSeeder::ensureBuiltInRole($user->role);
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}