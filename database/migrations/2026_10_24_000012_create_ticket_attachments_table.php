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
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('ticket_messages')->nullOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('original_filename', 255);
            $table->string('storage_path', 500); // Private relative path in storage/app/private
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->timestamps();

            $table->index(['ticket_id', 'message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
