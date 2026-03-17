<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_snapshots', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropIndex(['league_id', 'user_id', 'captured_at']);
            }

            $table->unique(
                ['league_id', 'user_id', 'captured_at'],
                'portfolio_snapshots_league_user_hour_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_snapshots', function (Blueprint $table) {
            $table->dropUnique('portfolio_snapshots_league_user_hour_unique');

            if (DB::getDriverName() !== 'sqlite') {
                $table->index(['league_id', 'user_id', 'captured_at']);
            }
        });
    }
};
