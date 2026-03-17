<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('app.demo_mode')) {
            $this->call(DemoModeSeeder::class);
        }
    }
}
