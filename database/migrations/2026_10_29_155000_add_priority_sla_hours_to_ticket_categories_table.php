<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->unsignedInteger('sla_critical_hours')->default(4)->after('default_priority');
            $table->unsignedInteger('sla_high_hours')->default(8)->after('sla_critical_hours');
            $table->unsignedInteger('sla_medium_hours')->default(24)->after('sla_high_hours');
            $table->unsignedInteger('sla_low_hours')->default(48)->after('sla_medium_hours');
        });

        // Backfill existing rows based on their target_resolve_hours
        $categories = DB::table('ticket_categories')->get();
        foreach ($categories as $cat) {
            $base = $cat->target_resolve_hours ?: 24;
            $crit = max(1, (int) round($base * 0.25));
            $high = max(2, (int) round($base * 0.5));
            $med = $base;
            $low = max($base, (int) round($base * 2));

            DB::table('ticket_categories')
                ->where('id', $cat->id)
                ->update([
                    'sla_critical_hours' => $crit,
                    'sla_high_hours' => $high,
                    'sla_medium_hours' => $med,
                    'sla_low_hours' => $low,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropColumn([
                'sla_critical_hours',
                'sla_high_hours',
                'sla_medium_hours',
                'sla_low_hours',
            ]);
        });
    }
};
