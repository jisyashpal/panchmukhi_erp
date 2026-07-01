<?php

namespace Database\Factories;

use App\Enums\UserApprovalStatus;
use App\Models\Role;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => fn () => Role::where('slug', 'plant_in_charge')->value('id'),
            'plant_id' => Plant::factory(),
            'is_active' => true,
            'approval_status' => UserApprovalStatus::Approved,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role_id' => fn () => Role::where('slug', Role::SLUG_SUPER_ADMIN)->value('id'),
            'plant_id' => null,
        ]);
    }

    public function hoDataOperator(): static
    {
        return $this->state(fn () => [
            'role_id' => fn () => Role::where('slug', Role::SLUG_HO_DATA_OPERATOR)->value('id'),
            'plant_id' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
