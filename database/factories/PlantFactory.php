<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '')).fake()->unique()->numberBetween(1, 999),
            'type' => fake()->randomElement(['site', 'plant']),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
