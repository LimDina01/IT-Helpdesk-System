<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\SoftwareLicense;
use App\Models\SoftwareLicenseAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SoftwareLicenseSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dina = User::where('name', 'like', '%Dina%')->first();
        $chhayly = User::where('name', 'like', '%Chhayly%')->first();
        $bopha = User::where('name', 'like', '%Bopha%')->first();
        $admin = User::where('email', 'admin@cbvh.com.kh')->first();

        $dinaPc = Asset::where('asset_tag', 'CBVH-PC-0101')->first();
        $chhaylyLt = Asset::where('asset_tag', 'CBVH-LT-0042')->first();
        $bophaLt = Asset::where('asset_tag', 'CBVH-LT-0055')->first();

        // 1. Microsoft 365 Business Standard
        $m365 = SoftwareLicense::firstOrCreate(
            ['name' => 'Microsoft 365 Business Standard'],
            [
                'vendor_publisher' => 'Microsoft Corporation',
                'license_type' => 'subscription',
                'license_key' => 'https://admin.microsoft.com (CBVH Tenant)',
                'seats_total' => 50,
                'purchase_date' => Carbon::now()->subMonths(6)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(6)->toDateString(),
                'purchase_cost' => 7500.00,
                'billing_cycle' => 'annual',
                'status' => 'active',
                'notes' => 'Covers Outlook, Word, Excel, Teams, SharePoint, and 1TB OneDrive cloud storage per employee.',
            ]
        );

        // Assign some seats
        if ($dina) {
            SoftwareLicenseAssignment::firstOrCreate(
                ['software_license_id' => $m365->id, 'user_id' => $dina->id],
                ['asset_id' => $dinaPc?->id, 'assigned_date' => Carbon::now()->subMonths(5), 'notes' => 'Corporate email and Office 365 desktop apps']
            );
        }
        if ($chhayly) {
            SoftwareLicenseAssignment::firstOrCreate(
                ['software_license_id' => $m365->id, 'user_id' => $chhayly->id],
                ['asset_id' => $chhaylyLt?->id, 'assigned_date' => Carbon::now()->subMonths(5), 'notes' => 'Finance HOD mailbox and Excel macros']
            );
        }
        if ($bopha) {
            SoftwareLicenseAssignment::firstOrCreate(
                ['software_license_id' => $m365->id, 'user_id' => $bopha->id],
                ['asset_id' => $bophaLt?->id, 'assigned_date' => Carbon::now()->subMonths(4), 'notes' => 'Bak Touk branch staff']
            );
        }

        // 2. Adobe Creative Cloud All Apps
        $adobe = SoftwareLicense::firstOrCreate(
            ['name' => 'Adobe Creative Cloud All Apps'],
            [
                'vendor_publisher' => 'Adobe Systems',
                'license_type' => 'subscription',
                'license_key' => 'VIP-99410-ADOBE-CC',
                'seats_total' => 5,
                'purchase_date' => Carbon::now()->subMonths(10)->toDateString(),
                'expiry_date' => Carbon::now()->addDays(25)->toDateString(), // Expiring soon!
                'purchase_cost' => 4800.00,
                'billing_cycle' => 'annual',
                'status' => 'active',
                'notes' => 'Photoshop, Illustrator, InDesign, Premiere Pro for marketing and graphics design.',
            ]
        );

        if ($dina) {
            SoftwareLicenseAssignment::firstOrCreate(
                ['software_license_id' => $adobe->id, 'user_id' => $dina->id],
                ['asset_id' => $dinaPc?->id, 'assigned_date' => Carbon::now()->subMonths(9), 'notes' => 'Assigned to Senior Designer Dina Lim']
            );
        }

        // 3. Kaspersky Endpoint Security Cloud
        SoftwareLicense::firstOrCreate(
            ['name' => 'Kaspersky Endpoint Security Cloud'],
            [
                'vendor_publisher' => 'Kaspersky Lab',
                'license_type' => 'subscription',
                'license_key' => 'KASP-7721-CBVH-SEC',
                'seats_total' => 60,
                'purchase_date' => Carbon::now()->subMonths(3)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(9)->toDateString(),
                'purchase_cost' => 1800.00,
                'billing_cycle' => 'annual',
                'status' => 'active',
                'notes' => 'Centrally managed cloud antivirus & ransomware shield for all CBVH workstations and laptops.',
            ]
        );

        // 4. AutoCAD 2024 Commercial
        $autocad = SoftwareLicense::firstOrCreate(
            ['name' => 'AutoCAD 2024 Commercial'],
            [
                'vendor_publisher' => 'Autodesk',
                'license_type' => 'subscription',
                'license_key' => 'AUTOD-88192-3D-CAD',
                'seats_total' => 2,
                'purchase_date' => Carbon::now()->subMonths(8)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(4)->toDateString(),
                'purchase_cost' => 3900.00,
                'billing_cycle' => 'annual',
                'status' => 'active',
                'notes' => 'Used for showroom floor planning, counter architecture drawings, and jewelry 3D rendering.',
            ]
        );

        if ($dinaPc) {
            SoftwareLicenseAssignment::firstOrCreate(
                ['software_license_id' => $autocad->id, 'asset_id' => $dinaPc->id],
                ['user_id' => $dina?->id, 'assigned_date' => Carbon::now()->subMonths(8), 'notes' => 'Installed on HQ 2F Design Workstation']
            );
        }

        // 5. Windows 11 Pro OEM
        SoftwareLicense::firstOrCreate(
            ['name' => 'Windows 11 Pro OEM Workstation Licenses'],
            [
                'vendor_publisher' => 'Microsoft Corporation',
                'license_type' => 'perpetual',
                'license_key' => 'OEM-VOLUME-DIGITAL-LICENSE',
                'seats_total' => 25,
                'purchase_date' => Carbon::now()->subYears(1)->toDateString(),
                'expiry_date' => null, // Perpetual!
                'purchase_cost' => 3750.00,
                'billing_cycle' => 'one_time',
                'status' => 'active',
                'notes' => 'Perpetual OEM licenses bundled with Dell and HP office desktops.',
            ]
        );
    }
}
