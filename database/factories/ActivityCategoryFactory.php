<?php

namespace Database\Factories;

use App\Models\ActivityCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


class ActivityCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(6)),
            'label' => $this->faker->words(2, true),
            'is_active' => true,
            'version' => '1.0',
        ];
    }
}