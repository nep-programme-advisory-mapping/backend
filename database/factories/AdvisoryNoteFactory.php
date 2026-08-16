<?php

namespace Database\Factories;

use App\Models\AdvisoryNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvisoryNote>
 */
class AdvisoryNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submitting_party' => fake()->company(),
            'document_name' => fake()->sentence(3),
            'analysis_scope' => fake()->randomElement(['full map', 'geographic subset', 'thematic subset']),
            'analysis_scope_detail' => null,
            'status' => fake()->randomElement(['pending', 'in_review', 'completed']),
            'submitted_at' => now(),
        ];
    }
}