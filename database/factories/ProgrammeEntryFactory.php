<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgrammeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'programme_name' => fake()->sentence(3),
            'start_year' => fake()->numberBetween(2015, 2024),
            'end_year' => fake()->optional(0.7)->numberBetween(2025, 2030),
            'ongoing' => fake()->boolean(20),
            'fte_staff' => fake()->randomFloat(2, 0, 100),
            'indirect_beneficiaries' => fake()->numberBetween(0, 10000),
            'direct_beneficiaries' => fake()->numberBetween(0, 10000),
            'method' => fake()->optional()->text(),
            'verified_date' => fake()->optional()->date(),
            'is_submitted' => false,
        ];
    }
}
