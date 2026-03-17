<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email'        => fake()->unique()->safeEmail(),
            'username'     => fake()->unique()->userName(),
            'display_name' => fake()->name(),
            'password'     => Hash::make('password'),
            'avatar_url'   => null,
        ];
    }
}
