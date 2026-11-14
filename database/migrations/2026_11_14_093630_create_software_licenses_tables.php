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
        // 1. Software & Digital Licenses (SaaS, OEM, Subscriptions)
        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150); // e.g. "Microsoft 365 Business Standard", "Adobe Creative Cloud"
            $table->string('vendor_publisher', 100)->nullable(); // e.g. "Microsoft", "Adobe", "Autodesk"
            $table->string('license_type', 50)->default('subscription'); // 'subscription', 'perpetual', 'volume', 'oem', 'free'
            $table->string('license_key', 255)->nullable(); // License key / activation code / admin URL
            $table->unsignedInteger('seats_total')->default(1); // Total licenses purchased
            $table->date('purchase_date')->nullable();
            $table->date('expiry_date')->nullable(); // Subscription renewal date
            $table->decimal('purchase_cost', 12, 2)->nullable(); // Financial cost (masked for junior tech)
            $table->string('billing_cycle', 30)->default('annual'); // 'annual', 'monthly', 'one_time'
            $table->string('status', 30)->default('active'); // 'active', 'expiring_soon', 'expired', 'cancelled'
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'expiry_date']);
        });

        // 2. Software License Seat Assignments (User / Machine)
        Schema::create('software_license_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_license_id')->constrained('software_licenses')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Assigned employee
            $table->foreignId('asset_id')->nullable()->constrained('assets')->onDelete('set null'); // Installed machine
            $table->date('assigned_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['software_license_id', 'user_id']);
            $table->index(['software_license_id', 'asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('software_license_assignments');
        Schema::dropIfExists('software_licenses');
    }
};
