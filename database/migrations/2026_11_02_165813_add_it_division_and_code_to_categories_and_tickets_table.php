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
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('parent_id')->index();
            $table->string('it_division', 10)->default('opr')->after('type')->index(); // 'opr' (IT-OPR) or 'prd' (IT-PRD)
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('it_division', 10)->default('opr')->after('category_id')->index(); // 'opr' or 'prd'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('it_division');
        });

        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropColumn(['code', 'it_division']);
        });
    }
};
