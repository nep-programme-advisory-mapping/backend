<?php

namespace Database\Factories;

use App\Models\ActivityCategory;
use App\Models\ActivitySubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


class ActivitySubcategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => ActivityCategory::factory(),
            'code' => strtoupper(Str::random(6)),
            'label' => $this->faker->words(3, true),
            'is_active' => true,
            'version' => '1.0',
        ];
    }
}