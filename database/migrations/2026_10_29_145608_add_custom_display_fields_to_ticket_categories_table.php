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
            $table->string('description', 255)->nullable()->after('name_km');
            $table->string('description_km', 255)->nullable()->after('description');
            $table->string('icon', 50)->nullable()->after('description_km');
            $table->string('icon_url', 255)->nullable()->after('icon');
            $table->string('icon_color', 50)->nullable()->after('icon_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'description_km', 'icon', 'icon_url', 'icon_color']);
        });
    }
};
