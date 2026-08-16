<?php

namespace Database\Factories;

use App\Models\ActivityItem;
use App\Models\ActivitySubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


class ActivityItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subcategory_id' => ActivitySubcategory::factory(),
            'code' => strtoupper(Str::random(6)),
            'label' => $this->faker->words(3, true),
            'is_active' => true,
            'is_other' => false,
            'version' => '1.0',
        ];
    }
}