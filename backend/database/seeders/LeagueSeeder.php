<?php

namespace Database\Seeders;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LeagueSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (!$user) {
            $user = User::factory()->create();
        }

        // 1. Public upcoming league
        $publicUpcoming = League::factory()->public()->upcoming()->create([
            'name'       => 'Demo Public League',
            'created_by' => $user->id,
        ]);

        LeagueMember::create([
            'league_id'       => $publicUpcoming->id,
            'user_id'         => $user->id,
            'initial_capital' => $publicUpcoming->buy_in,
            'joined_at'       => now(),
        ]);

        // 2. Private upcoming league with hashed password
        $privateUpcoming = League::factory()->private()->upcoming()->create([
            'name'       => 'Demo Private League',
            'password'   => Hash::make('secret123'),
            'created_by' => $user->id,
        ]);

        LeagueMember::create([
            'league_id'       => $privateUpcoming->id,
            'user_id'         => $user->id,
            'initial_capital' => $privateUpcoming->buy_in,
            'joined_at'       => now(),
        ]);

        // 3. Public active league
        $publicActive = League::factory()->public()->active()->create([
            'name'       => 'Demo Active League',
            'created_by' => $user->id,
        ]);

        LeagueMember::create([
            'league_id'       => $publicActive->id,
            'user_id'         => $user->id,
            'initial_capital' => $publicActive->buy_in,
            'joined_at'       => now(),
        ]);
    }
}
