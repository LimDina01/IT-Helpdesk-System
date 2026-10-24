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
        Schema::create('company_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('holiday_date')->unique();
            $table->boolean('is_recurring')->default(false);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            
            $table->index('holiday_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_holidays');
    }
};
