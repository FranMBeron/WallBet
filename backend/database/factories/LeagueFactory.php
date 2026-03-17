<?php

namespace Database\Factories;

use App\Enums\LeagueStatus;
use App\Enums\LeagueType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\League>
 */
class LeagueFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+7 days');
        $endsAt   = fake()->dateTimeBetween('+8 days', '+30 days');

        return [
            'name'             => fake()->words(3, true),
            'description'      => fake()->optional()->sentence(),
            'type'             => LeagueType::Sponsored,
            'buy_in'           => fake()->randomElement([50, 100, 200, 500]),
            'max_participants' => fake()->numberBetween(4, 20),
            'status'           => LeagueStatus::Upcoming,
            'invite_code'      => strtoupper(Str::random(8)),
            'password'         => null,
            'is_public'        => true,
            'starts_at'        => $startsAt,
            'ends_at'          => $endsAt,
            'created_by'       => User::factory(),
        ];
    }

    /** State: league has upcoming status */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'    => LeagueStatus::Upcoming,
            'starts_at' => fake()->dateTimeBetween('+1 day', '+7 days'),
            'ends_at'   => fake()->dateTimeBetween('+8 days', '+30 days'),
        ]);
    }

    /** State: league has active status */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'    => LeagueStatus::Active,
            'starts_at' => fake()->dateTimeBetween('-7 days', '-1 day'),
            'ends_at'   => fake()->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }

    /** State: league has finished status */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'    => LeagueStatus::Finished,
            'starts_at' => fake()->dateTimeBetween('-30 days', '-8 days'),
            'ends_at'   => fake()->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }

    /** State: league is publicly visible */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
            'password'  => null,
        ]);
    }

    /** State: league is private with a hashed password */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'      => LeagueType::Private,
            'is_public' => false,
            'password'  => Hash::make('secret123'),
        ]);
    }
}
