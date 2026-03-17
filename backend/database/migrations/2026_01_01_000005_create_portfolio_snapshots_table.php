<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_value', 12, 2)->nullable();
            $table->decimal('cash_available', 12, 2)->nullable();
            $table->jsonb('positions');
            $table->integer('rank')->nullable();
            $table->decimal('return_pct', 8, 4)->nullable();
            $table->timestampTz('captured_at');
            $table->timestamps();

            $table->index(['league_id', 'user_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_snapshots');
    }
};
