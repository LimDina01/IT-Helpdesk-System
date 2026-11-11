<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetComponent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAsset;
use App\Models\TicketAssetPart;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItAssetSampleSeeder extends Seeder
{
    /**
     * Run the sample asset seeder.
     */
    public function run(): void
    {
        // 1. Lookups
        $hqBranch = Branch::where('code', 'HQ-PP')->first();
        $btBranch = Branch::where('code', 'BT-PP')->first();
        $btbBranch = Branch::where('code', 'BTB-01')->first();
        $srBranch = Branch::where('code', 'VH-SR')->first();

        $finDept = Department::where('code', 'FIN')->first();
        $salesDept = Department::where('code', 'SALES')->first();
        $mgtDept = Department::where('code', 'MGT')->first();
        $itOprDept = Department::where('code', 'IT-OPR')->first() ?? Department::where('code', 'IT')->first();

        $dinaUser = User::where('email', 'dina.lim@cbvh.com.kh')->first() ?? User::where('email', 'dina.lim@cbvh66.com')->first();
        $adminUser = User::where('email', 'admin@cbvh.com.kh')->first() ?? User::first();
        $bophaUser = User::where('email', 'bopha.keo@cbvh.com.kh')->first();
        $finHodUser = User::where('email', 'hod.fin@cbvh.com.kh')->first();
        $helpdeskUser = User::where('email', 'helpdesk@cbvh.com.kh')->first();

        $catWorkstation = AssetCategory::where('code', 'WORKSTATION')->first();
        $catPos = AssetCategory::where('code', 'POS')->first();
        $catPrinter = AssetCategory::where('code', 'PRINTER')->first();
        $catMeeting = AssetCategory::where('code', 'MEETING')->first();
        $catNetwork = AssetCategory::where('code', 'NETWORK')->first();

        // 2. Personal Hardware Assets (Assigned to Staff)
        $pcDina = Asset::updateOrCreate(
            ['asset_tag' => 'CBVH-PC-0101'],
            [
                'name' => 'HQ Design Workstation (Dina Lim)',
                'category_id' => $catWorkstation->id,
                'ownership_type' => 'personal',
                'branch_id' => $hqBranch?->id,
                'department_id' => $mgtDept?->id ?? $finDept?->id,
                'location_details' => 'HQ Floor 2, Creative & Operations Suite',
                'assigned_user_id' => $dinaUser?->id,
                'brand' => 'Dell',
                'model' => 'OptiPlex 7010 Tower',
                'serial_number' => 'DL-7010-884219',
                'status' => 'in_use',
                'specs' => [
                    'cpu' => 'Intel Core i7-13700 (16-Core)',
                    'ram' => '32GB DDR5 5600MHz (2x 16GB)',
                    'storage' => '1TB Samsung 980 Pro NVMe SSD',
                    'os' => 'Windows 11 Pro 64-bit',
                    'ip_address' => '192.168.10.45',
                    'mac_address' => '00:1A:2B:3C:4D:5E',
                ],
                'purchase_date' => '2024-03-15',
                'purchase_cost' => 1250.00,
                'warranty_expiry' => '2027-03-15',
                'vendor_supplier' => 'Anana Computer Cambodia',
                'notes' => 'High-performance workstation configured for graphic design, CAD jewelry rendering, and system management.',
            ]
        );

        $laptopFinHod = Asset::updateOrCreate(
            ['asset_tag' => 'CBVH-LT-0042'],
            [
                'name' => 'Finance Director Laptop (Chhayly)',
                'category_id' => $catWorkstation->id,
                'ownership_type' => 'personal',
                'branch_id' => $hqBranch?->id,
                'department_id' => $finDept?->id,
                'location_details' => 'HQ Floor 2, Finance Office',
                'assigned_user_id' => $finHodUser?->id,
                'brand' => 'Lenovo',
                'model' => 'ThinkPad T14s Gen 4',
                'serial_number' => 'PF-498X01',
                'status' => 'in_use',
                'specs' => [
                    'cpu' => 'Intel Core i5-1335U',
                    'ram' => '16GB LPDDR5',
                    'storage' => '512GB NVMe SSD',
                    'os' => 'Windows 11 Pro',
                    'ip_address' => '192.168.10.82',
                ],
                'purchase_date' => '2024-01-10',
                'purchase_cost' => 1120.00,
                'warranty_expiry' => '2027-01-10',
                'vendor_supplier' => 'Chantrea Technology',
                'notes' => 'Executive laptop with BitLocker encryption and ERP financial module access.',
            ]
        );

        $pcHelpdesk = Asset::updateOrCreate(
            ['asset_tag' => 'CBVH-PC-0008'],
            [
                'name' => 'IT Helpdesk Support PC (Kosal)',
                'category_id' => $catWorkstation->id,
                'ownership_type' => 'personal',
                'branch_id' => $hqBranch?->id,
                'department_id' => $itOprDept?->id,
                'location_details' => 'HQ Floor 1, IT Service Desk Counter',
                'assigned_user_id' => $helpdeskUser?->id,
                'brand' => 'HP',
                'model' => 'ProDesk 400 G7 MT',
                'serial_number' => 'CZC-123498',
                'status' => 'in_use',
                'specs' => [
                    'cpu' => 'Intel Core i5-10500',
                    'ram' => '16GB DDR4',
                    'storage' => '512GB Kingston NVMe SSD',
                    'os' => 'Windows 11 Pro',
                    'ip_address' => '192.168.10.15',
                ],
                'purchase_date' => '2023-08-20',
                'purchase_cost' => 690.00,
                'warranty_expiry' => '2026-08-20',
                'vendor_supplier' => 'Anana Computer Cambodia',
            ]
        );

        // 3. Shared / Location-Based Hardware Assets (Meeting rooms, printers, branches, POS)
        $sharedProjector = Asset::updateOrCreate(
            ['asset_tag' => 'CBVH-DISP-0001'],
            [
                'name' => 'HQ 3F — Executive Meeting Room A Slide Projector',
                'category_id' => $catMeeting->id,
                'ownership_type' => 'shared',
                'branch_id' => $hqBranch?->id,
                'department_id' => $mgtDept?->id,
                'location_details' => 'HQ Floor 3, Meeting Room A (Boardroom)',
                'assigned_user_id' => null,
                'brand' => 'Epson',
                'model' => 'EB-2250U Full HD 5000-Lumen Projector',
                'serial_number' => 'EP-2250-99042',
                'status' => 'in_use',
                'specs' => [
                    'resolution' => '1920x1200 WUXGA Full HD',
                    'brightness' => '5000 ANSI Lumens',
                    'inputs' => 'Dual HDMI / Wireless Screen Mirroring / VGA',
                    'ip_address' => '192.168.10.210',
                    'mount' => 'Ceiling Mount with Motorized 120" Screen',
                ],
                'purchase_date' => '2023-11-05',
                'purchase_cost' => 1480.00,
                'warranty_expiry' => '2025-11-05',
                'vendor_supplier' => 'Epson Cambodia Authorized Distributor',
                'notes' => 'Dedicated for board meetings, slide presentations, and company town halls. Remote is kept on the podium.',
            ]
        );

        $sharedTvMeetingB = Asset::updateOrCreate(
            ['asset_tag' => 'CBVH-DISP-0002'],
            [
                'name' => 'HQ 2F — Meeting Room B 75" Presentation Display',
                'category_id' => $catMeeting->id,
                'ownership_type' => 'shared',
                'branch_id' => $hqBranch?->id,
                'department_id' => null,
                'location_details' => 'HQ Floor 2, Meeting Room B',
                'assigned_user_id' => null,
                'brand' => 'LG',
                'model' => '75UR8050 4K UHD Commercial Display',
                'serial_number' => 'LG-75U-44120',
                'status' => 'in_use',
                'specs' => [
                    'screen_size' => '75-inch 4K UHD 3840x2160',
                    'inputs' => '3x HDMI, Apple AirPlay 2, Chromecast built-in',
                    'accessories' => 'Logitech MeetUp 4K Conference Cam & Speakerphone',
                    'ip_address' => '192.168.10.212',
                ],
                'purchase_date' => '2024-02-18',
                'purchase_cost' => 1350.00,
                'warranty_expiry' => '2026-02-18',
                'vendor_supplier' => 'Kfour Group Cambodia',
                'notes' => 'Used for team sprint meetings, Google Meet video conferences, and slides.',
            ]
        );

        $sharedPrinter = Asset::updateOrCreate(
            ['asset_tag' => 'CBVH-PRN-0001'],
            [
                'name' => 'HQ 2F — Finance & Operations Shared Network Copier',
                'category_id' => $catPrinter->id,
                'ownership_type' => 'shared',
                'branch_id' => $hqBranch?->id,
                'department_id' => $finDept?->id,
                'location_details' => 'HQ Floor 2, Central Corridor near Finance Office',
                'assigned_user_id' => null,
                'brand' => 'Canon',
                'model' => 'imageRUNNER ADVANCE DX C3826i',
                'serial_number' => 'CN-DX3826-5541',
                'status' => 'in_use',
                'specs' => [
                    'type' => 'Color Multifunction Laser Printer (A3 / A4)',
                    'speed' => '26 ppm Color & Black/White',
                    'ip_address' => '192.168.10.220',
                    'mac_address' => '00:1E:8F:23:AA:01',
                    'functions' => 'Print / Copy / Scan to Email / Department ID Code',
                ],
                'purchase_date' => '2023-05-12',
                'purchase_cost' => 2850.00,
                'warranty_expiry' => '2026-05-12',
                'vendor_supplier' => 'Royal Cambodia Co., Ltd.',
                'notes' => 'Shared heavy-duty floor copier. Toner model: NPG-84 (Cyan, Magenta, Yellow, Black).',
            ]
        );

        $posBtb = Asset::updateOrCreate(
            ['asset_tag' => 'CBVH-POS-0010'],
            [
                'name' => 'Battambang Branch — Cashier Counter 1 POS Terminal',
                'category_id' => $catPos->id,
                'ownership_type' => 'shared',
                'branch_id' => $btbBranch?->id ?? $hqBranch?->id,
                'department_id' => $salesDept?->id,
                'location_details' => 'Battambang Showroom, Counter 1 (Gold Section)',
                'assigned_user_id' => null,
                'brand' => 'Sunmi',
                'model' => 'D2s PLUS Dual-Screen Smart POS',
                'serial_number' => 'SN-D2S-10924',
                'status' => 'in_use',
                'specs' => [
                    'main_screen' => '15.6" FHD Capacitive Touchscreen',
                    'customer_screen' => '10.1" Customer Facing Display',
                    'built_in_printer' => '80mm Seiko Thermal Receipt Printer with Auto-cutter',
                    'ip_address' => '192.168.30.15',
                ],
                'purchase_date' => '2024-04-01',
                'purchase_cost' => 720.00,
                'warranty_expiry' => '2025-04-01',
                'vendor_supplier' => 'Smart POS Solutions Cambodia',
                'notes' => 'Connected to retail sales billing software and thermal receipt printing.',
            ]
        );

        $coreSwitch = Asset::updateOrCreate(
            ['asset_tag' => 'CBVH-NET-0003'],
            [
                'name' => 'Siem Reap Branch — 24-Port Gigabit PoE+ Core Switch',
                'category_id' => $catNetwork->id,
                'ownership_type' => 'shared',
                'branch_id' => $srBranch?->id ?? $hqBranch?->id,
                'department_id' => null,
                'location_details' => 'Server Rack Cabinet, 1st Floor Back Office',
                'assigned_user_id' => null,
                'brand' => 'Cisco',
                'model' => 'Catalyst 1000-24P-4G-L (PoE 195W)',
                'serial_number' => 'FOC-248109',
                'status' => 'in_use',
                'specs' => [
                    'ports' => '24x 10/100/1000 Gigabit PoE+ ports',
                    'uplinks' => '4x 1G SFP Fiber Uplinks',
                    'management_ip' => '192.168.40.2',
                    'firmware' => 'Cisco IOS 15.2(7)E7',
                ],
                'purchase_date' => '2023-09-10',
                'purchase_cost' => 960.00,
                'warranty_expiry' => '2026-09-10',
                'vendor_supplier' => 'First Cambodia Co., Ltd.',
                'notes' => 'Powers CCTV cameras, branch VoIP phones, and Aruba Wi-Fi Access Points.',
            ]
        );

        // 4. Installed Components inside Assets
        AssetComponent::updateOrCreate(
            ['serial_number' => 'S980P-1TB-998811'],
            [
                'asset_id' => $pcDina->id,
                'branch_id' => $hqBranch?->id,
                'component_type' => 'ssd',
                'brand_model' => 'Samsung 980 PRO 1TB PCIe 4.0 NVMe M.2 SSD',
                'capacity_or_specs' => '1TB NVMe Read: 7000MB/s',
                'status' => 'installed',
                'installed_at' => '2024-03-15 10:00:00',
                'notes' => 'Factory installed primary OS drive.',
            ]
        );

        AssetComponent::updateOrCreate(
            ['serial_number' => 'KF-D5-16GB-A01'],
            [
                'asset_id' => $pcDina->id,
                'branch_id' => $hqBranch?->id,
                'component_type' => 'ram',
                'brand_model' => 'Kingston Fury Beast 16GB DDR5 5600MHz (Slot 1)',
                'capacity_or_specs' => '16GB DDR5 5600MHz CL36',
                'status' => 'installed',
                'installed_at' => '2024-03-15 10:00:00',
            ]
        );

        AssetComponent::updateOrCreate(
            ['serial_number' => 'KF-D5-16GB-A02'],
            [
                'asset_id' => $pcDina->id,
                'branch_id' => $hqBranch?->id,
                'component_type' => 'ram',
                'brand_model' => 'Kingston Fury Beast 16GB DDR5 5600MHz (Slot 2)',
                'capacity_or_specs' => '16GB DDR5 5600MHz CL36',
                'status' => 'installed',
                'installed_at' => '2024-03-15 10:00:00',
            ]
        );

        // 5. Spare Parts & Components Stock (Loose stock sitting in IT Storage Cabinet)
        $spareParts = [
            [
                'serial_number' => 'KF-D4-8GB-SP01',
                'component_type' => 'ram',
                'brand_model' => 'Kingston Fury Beast 8GB DDR4 3200MHz',
                'capacity_or_specs' => '8GB DDR4 3200MHz',
                'status' => 'in_stock',
                'notes' => 'Brand new sealed in anti-static bag. IT Storage Shelf 1.',
            ],
            [
                'serial_number' => 'KF-D4-8GB-SP02',
                'component_type' => 'ram',
                'brand_model' => 'Kingston Fury Beast 8GB DDR4 3200MHz',
                'capacity_or_specs' => '8GB DDR4 3200MHz',
                'status' => 'in_stock',
                'notes' => 'Brand new sealed. IT Storage Shelf 1.',
            ],
            [
                'serial_number' => 'COR-D4-16GB-SP01',
                'component_type' => 'ram',
                'brand_model' => 'Corsair Vengeance LPX 16GB DDR4 3200MHz',
                'capacity_or_specs' => '16GB DDR4 3200MHz',
                'status' => 'in_stock',
                'notes' => 'Spare RAM for workstation upgrades.',
            ],
            [
                'serial_number' => 'KC600-512GB-SP01',
                'component_type' => 'ssd',
                'brand_model' => 'Kingston KC600 512GB 2.5" SATA SSD',
                'capacity_or_specs' => '512GB 2.5-inch SATA III',
                'status' => 'in_stock',
                'notes' => 'For upgrading slow cashier HDDs to SSD.',
            ],
            [
                'serial_number' => 'S980-500GB-SP01',
                'component_type' => 'ssd',
                'brand_model' => 'Samsung 980 500GB PCIe 3.0 NVMe M.2 SSD',
                'capacity_or_specs' => '500GB NVMe M.2 2280',
                'status' => 'in_stock',
                'notes' => 'Fast replacement drive for office laptops.',
            ],
            [
                'serial_number' => 'CM-550W-SP01',
                'component_type' => 'psu',
                'brand_model' => 'Cooler Master MWE 550W 80 Plus Bronze PSU',
                'capacity_or_specs' => '550 Watt ATX Power Supply',
                'status' => 'in_stock',
                'notes' => 'Spare replacement for blown PC power supplies.',
            ],
            [
                'serial_number' => 'EP-LAMP-ELPLP96',
                'component_type' => 'display',
                'brand_model' => 'Epson ELPLP96 Replacement Lamp Bulb',
                'capacity_or_specs' => '230W UHE Projector Lamp (EB-2250U compatible)',
                'status' => 'in_stock',
                'notes' => 'Backup lamp kept in IT cabinet for Meeting Room A projector.',
            ],
            [
                'serial_number' => 'DEFECT-RAM-0099',
                'component_type' => 'ram',
                'brand_model' => 'Kingston ValueRAM 8GB DDR4 2666MHz (Defective)',
                'capacity_or_specs' => '8GB DDR4',
                'status' => 'faulty',
                'notes' => 'Extracted from old PC. Fails MemTest86 with 120+ bitflip errors. Marked for disposal/RMA.',
            ],
        ];

        foreach ($spareParts as $sp) {
            AssetComponent::updateOrCreate(
                ['serial_number' => $sp['serial_number']],
                array_merge($sp, [
                    'asset_id' => null,
                    'branch_id' => $hqBranch?->id,
                ])
            );
        }

        // 6. Link Real Tickets to Equipment & Create Maintenance History
        // A. Link Ticket 5 ("Error Printer") to the Canon Floor Printer!
        $ticket5 = Ticket::find(5);
        if ($ticket5) {
            TicketAsset::firstOrCreate(
                ['ticket_id' => $ticket5->id, 'asset_id' => $sharedPrinter->id],
                ['attached_by_user_id' => $adminUser?->id, 'notes' => 'Identified as paper feed roller jam on Tray 2']
            );

            TicketAssetPart::firstOrCreate(
                ['ticket_id' => $ticket5->id, 'asset_id' => $sharedPrinter->id, 'description' => 'Cleaned Tray 2 pick-up roller and cleared paper jam fragments'],
                ['action' => 'serviced', 'recorded_by_user_id' => $adminUser?->id]
            );
        }

        // B. Create a past service ticket for Dina's PC to showcase the "Asset Medical Record"
        $pastTicket = Ticket::updateOrCreate(
            ['ticket_code' => 'IT-202611-0002'],
            [
                'ticket_type' => 'incident',
                'category_id' => TicketCategory::first()?->id ?? 1,
                'branch_id' => $hqBranch?->id ?? 1,
                'requester_id' => $dinaUser?->id ?? $adminUser?->id,
                'subject' => 'Photoshop freezing and memory low warning on design PC',
                'description' => 'When opening 300DPI jewelry render files, Windows displays Memory Low warning and closes apps unexpectedly.',
                'status' => 'resolved_confirmed',
                'priority' => 'high',
                'current_technician_id' => $helpdeskUser?->id ?? $adminUser?->id,
                'resolved_at' => now()->subDays(3),
            ]
        );

        TicketAsset::firstOrCreate(
            ['ticket_id' => $pastTicket->id, 'asset_id' => $pcDina->id],
            ['attached_by_user_id' => $helpdeskUser?->id ?? $adminUser?->id, 'notes' => 'Diagnosed memory error under load']
        );

        TicketAssetPart::firstOrCreate(
            [
                'ticket_id' => $pastTicket->id,
                'asset_id' => $pcDina->id,
                'description' => 'Replaced faulty 8GB RAM and upgraded with 2x 16GB Kingston Fury Beast DDR5 modules from IT spare stock',
            ],
            [
                'action' => 'replaced',
                'recorded_by_user_id' => $helpdeskUser?->id ?? $adminUser?->id,
            ]
        );
    }
}
