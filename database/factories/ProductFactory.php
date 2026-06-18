<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sub_category_id' => null, // optional
            'brand_id' => Brand::factory(),
            'unit_id' => Unit::factory(),
            'name' => fake()->words(3, true),
            'sku' => Str::upper(fake()->unique()->bothify('SKU-####')),
            'reorder_level' => 10,
            'is_active' => true,
        ];
    }

}
