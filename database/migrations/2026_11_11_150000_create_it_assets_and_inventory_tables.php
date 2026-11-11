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
        // 1. Asset Categories
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('icon', 20)->default('💻');
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Core IT Assets (Personal and Shared/Location based)
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 50)->unique(); // e.g. CBVH-PC-001, CBVH-MEET-002
            $table->string('name', 150); // e.g. "HQ Floor 3 - Meeting Room A Slide Screen"
            $table->foreignId('category_id')->constrained('asset_categories')->onDelete('restrict');
            $table->string('ownership_type', 30)->default('personal'); // 'personal' (assigned to person) or 'shared' (assigned to room/floor)
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->string('location_details', 150)->nullable(); // e.g. "Floor 3, Meeting Room A", "Workshop Bench 2"
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable()->index();
            $table->string('status', 30)->default('in_use'); // 'in_use', 'spare_stock', 'under_repair', 'decommissioned'
            $table->json('specs')->nullable(); // cpu, ram, storage, os, ip, etc.
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->string('vendor_supplier', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'department_id']);
            $table->index(['status', 'ownership_type']);
        });

        // 3. Components & Spare Parts Stock (RAM, SSD, PSU, etc.)
        Schema::create('asset_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->onDelete('set null'); // null = in IT spare storage
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null'); // storage location
            $table->string('component_type', 50); // 'ram', 'ssd', 'hdd', 'psu', 'gpu', 'display', 'cable', 'other'
            $table->string('brand_model', 150); // e.g. "Kingston Fury 8GB DDR4 3200MHz"
            $table->string('serial_number', 100)->nullable()->index();
            $table->string('capacity_or_specs', 100)->nullable(); // e.g. "8GB DDR4", "512GB NVMe", "550W Bronze"
            $table->string('status', 30)->default('in_stock'); // 'installed', 'in_stock', 'faulty', 'disposed'
            $table->dateTime('installed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['component_type', 'status']);
        });

        // 4. Ticket & Asset Linkage (Pivot)
        Schema::create('ticket_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->foreignId('attached_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'asset_id']);
        });

        // 5. Parts Replaced / Serviced During Ticket Resolution
        Schema::create('ticket_asset_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->foreignId('component_id')->nullable()->constrained('asset_components')->onDelete('set null');
            $table->string('action', 50)->default('replaced'); // 'replaced', 'installed', 'removed', 'serviced'
            $table->string('description', 255); // e.g. "Replaced 8GB DDR4 RAM stick from IT Spare Stock"
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // Seed initial categories
        $now = now();
        DB::table('asset_categories')->insert([
            ['name' => 'Workstations & Laptops', 'code' => 'WORKSTATION', 'icon' => '💻', 'description' => 'Desktop PCs, Laptops, MacBooks, and All-in-Ones', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'POS & Retail Equipment', 'code' => 'POS', 'icon' => '🛒', 'description' => 'Retail POS terminals, customer displays, barcode scanners, and cash drawers', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Printers & Imaging', 'code' => 'PRINTER', 'icon' => '🖨️', 'description' => 'Network multi-function printers, thermal receipt printers, barcode label printers', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Meeting & Display Equipment', 'code' => 'MEETING', 'icon' => '📽️', 'description' => 'Meeting room slide projectors, presentation screens, conference cams and speakers', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Network & Infrastructure', 'code' => 'NETWORK', 'icon' => '🌐', 'description' => 'Switches, routers, firewalls, wireless access points, UPS batteries, servers', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Spare Components & Parts', 'code' => 'COMPONENT', 'icon' => '⚙️', 'description' => 'Loose internal components: RAM sticks, SSDs, HDDs, power supplies, cables', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_asset_parts');
        Schema::dropIfExists('ticket_assets');
        Schema::dropIfExists('asset_components');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
