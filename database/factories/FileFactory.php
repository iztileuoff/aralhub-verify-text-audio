<?php

namespace Database\Factories;

use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filename' => fake()->unique()->lexify('??????????').'.tsv',
            'path' => 'files/'.fake()->unique()->lexify('??????????').'.tsv',
            'mime_type' => 'text/tab-separated-values',
            'size' => fake()->numberBetween(100, 10000),
            'status' => File::STATUS_COMPLETED,
        ];
    }
}
