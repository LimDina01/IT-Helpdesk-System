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
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('ticket_categories')->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('type', ['service_request', 'incident_issue'])->default('incident_issue');
            $table->enum('default_priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->unsignedInteger('target_acknowledge_minutes')->default(30);
            $table->unsignedInteger('target_resolve_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_categories');
    }
};
