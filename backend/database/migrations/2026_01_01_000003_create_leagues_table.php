<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('type');
            $table->decimal('buy_in', 12, 2);
            $table->integer('max_participants')->default(20);
            $table->string('status');
            $table->string('invite_code', 20)->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('status');
            $table->index('invite_code');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE leagues ADD CONSTRAINT leagues_type_check CHECK (type IN ('sponsored', 'private'))");
            DB::statement("ALTER TABLE leagues ADD CONSTRAINT leagues_status_check CHECK (status IN ('upcoming', 'active', 'finished'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
