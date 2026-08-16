<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;


class EducationLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'level_name' => $this->faker->randomElement([
                'Pre-school', 'Primary', 'Lower Secondary', 'Upper Secondary', 'Tertiary',
            ]),
        ];
    }
}