<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Text;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Text>
 */
class TextFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'edit_original_transcript' => fake()->sentence(),
            'edit_normalized_transcript' => fake()->sentence(),
            'edit_tokenized_transcript' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the text belongs to the primary dataset file.
     */
    public function main(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_id' => config('dataset.main_file_id'),
            'is_main' => true,
        ]);
    }
}
