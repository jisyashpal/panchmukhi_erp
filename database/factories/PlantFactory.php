<?php

namespace Database\Factories;

use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plant>
 */
class PlantFactory extends Factory
{
    protected $model = Plant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '')).fake()->unique()->numberBetween(1, 999),
            'type' => fake()->randomElement(['plant', 'mine']),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
