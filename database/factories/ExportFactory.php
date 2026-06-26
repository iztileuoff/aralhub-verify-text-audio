<?php

namespace Database\Factories;

use App\Models\Export;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'filename' => fake()->unique()->lexify('??????????').'.tsv',
            'exported_count' => 0,
        ];
    }
}
