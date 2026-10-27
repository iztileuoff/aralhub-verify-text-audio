<?php

namespace Database\Factories;

use App\Models\CallLandmark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallLandmark>
 */
class CallLandmarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->lexify('??????'));

        return [
            'name' => $name,
            'normalized_name' => CallLandmark::normalize($name),
        ];
    }
}
