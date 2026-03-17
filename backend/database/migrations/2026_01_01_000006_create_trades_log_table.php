<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('ticker', 10);
            $table->string('action');
            $table->decimal('quantity', 12, 6);
            $table->decimal('price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['league_id', 'user_id']);
            $table->index(['league_id', 'executed_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE trades_log ADD CONSTRAINT trades_log_action_check CHECK (action IN ('BUY', 'SELL'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trades_log');
    }
};
