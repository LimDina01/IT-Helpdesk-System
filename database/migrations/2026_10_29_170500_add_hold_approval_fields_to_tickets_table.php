<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('hold_requested_category', 100)->nullable()->after('blocked_reason');
            $table->text('hold_requested_reason')->nullable()->after('hold_requested_category');
            $table->timestamp('hold_requested_at')->nullable()->after('hold_requested_reason');
            $table->foreignId('hold_requested_by_id')->nullable()->after('hold_requested_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['hold_requested_by_id']);
            $table->dropColumn([
                'hold_requested_category',
                'hold_requested_reason',
                'hold_requested_at',
                'hold_requested_by_id',
            ]);
        });
    }
};

