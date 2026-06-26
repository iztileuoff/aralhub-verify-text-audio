<?php

namespace Database\Factories;

use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => RoleEnum::EDITOR->value,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '998'.fake()->unique()->numerify('#########'),
            'password' => 'password',
            'gender' => fake()->randomElement(GenderEnum::cases())->value,
            'age' => fake()->numberBetween(18, 60),
            'is_verified' => true,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => RoleEnum::ADMIN->value,
        ]);
    }

    /**
     * Indicate that the user is not verified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_verified' => false,
        ]);
    }

    /**
     * Indicate that the user is not active.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
