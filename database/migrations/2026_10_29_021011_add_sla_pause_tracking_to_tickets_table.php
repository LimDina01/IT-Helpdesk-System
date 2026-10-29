<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('blocked_reason');
            $table->unsignedInteger('paused_minutes')->default(0)->after('blocked_at');
        });

        // Backfill any tickets currently marked as blocked
        DB::table('tickets')
            ->where('status', 'blocked')
            ->whereNull('blocked_at')
            ->update([
                'blocked_at' => DB::raw('updated_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'paused_minutes']);
        });
    }
};
