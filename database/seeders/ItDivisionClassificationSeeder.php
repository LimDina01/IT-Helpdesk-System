<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItDivisionClassificationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure IT-OPR and IT-PRD departments exist
        $itDept = Department::where('code', 'IT')->first();
        if ($itDept) {
            $itDept->update([
                'name' => 'IT Operations (IT-OPR)',
                'code' => 'IT-OPR',
            ]);
        } else {
            Department::firstOrCreate(
                ['code' => 'IT-OPR'],
                ['name' => 'IT Operations (IT-OPR)', 'is_active' => true]
            );
        }

        Department::firstOrCreate(
            ['code' => 'IT-PRD'],
            ['name' => 'IT Production (IT-PRD)', 'is_active' => true]
        );

        // 2. Map existing top-level parent categories
        $parentMap = [
            1  => ['code' => 'OPR-01', 'div' => 'opr'], // POS & Cashier Hardware
            7  => ['code' => 'OPR-01', 'div' => 'opr'], // Hardware & Office Equipment
            14 => ['code' => 'OPR-03', 'div' => 'opr'], // Network & Internet Connectivity
            20 => ['code' => 'OPR-02', 'div' => 'opr'], // Software & Applications
            27 => ['code' => 'PRD-01', 'div' => 'prd'], // Business Systems & POS
            34 => ['code' => 'OPR-08', 'div' => 'opr'], // Facility, CCTV & Phone
            39 => ['code' => 'OPR-06', 'div' => 'opr'], // Account & Access Issue
            45 => ['code' => 'OPR-06', 'div' => 'opr'], // Account & Access Requests
            51 => ['code' => 'OPR-01', 'div' => 'opr'], // Hardware & Equipment Requests
            56 => ['code' => 'OPR-17', 'div' => 'opr'], // Service Requests & Procurement
            61 => ['code' => 'OPR-08', 'div' => 'opr'], // Facility & Meeting Room Requests
        ];

        foreach ($parentMap as $id => $info) {
            TicketCategory::where('id', $id)->update([
                'code' => $info['code'],
                'it_division' => $info['div'],
            ]);
        }

        // 3. Map individual existing subcategories
        $subMap = [
            // POS & Cashier Hardware
            2  => ['code' => 'OPR-07', 'div' => 'opr'], // Thermal Receipt Printer Offline
            3  => ['code' => 'OPR-01', 'div' => 'opr'], // Barcode Scanner Not Reading
            4  => ['code' => 'OPR-01', 'div' => 'opr'], // Cash Drawer Jammed / Not Ejecting
            5  => ['code' => 'PRD-03', 'div' => 'prd'], // POS Touch Terminal Freezing
            6  => ['code' => 'OPR-01', 'div' => 'opr'], // RFID Reader Not Scanning

            // Hardware & Office Equipment
            8  => ['code' => 'OPR-01', 'div' => 'opr'], // Hardware Failure
            9  => ['code' => 'OPR-01', 'div' => 'opr'], // Desktop PC / Laptop Won't Boot
            10 => ['code' => 'OPR-01', 'div' => 'opr'], // Monitor Blank or Flickering
            11 => ['code' => 'OPR-07', 'div' => 'opr'], // Office Network Printer Paper Jam
            12 => ['code' => 'OPR-01', 'div' => 'opr'], // Mouse / Keyboard Replacement
            13 => ['code' => 'OPR-01', 'div' => 'opr'], // UPS / Power Backup Beeping

            // Network & Internet Connectivity
            15 => ['code' => 'OPR-03', 'div' => 'opr'], // Office Wi-Fi Disconnected
            16 => ['code' => 'OPR-03', 'div' => 'opr'], // Ethernet LAN Wall Port Inactive
            17 => ['code' => 'OPR-03', 'div' => 'opr'], // VPN Connection Refused
            18 => ['code' => 'OPR-03', 'div' => 'opr'], // Branch Main Internet Offline
            19 => ['code' => 'OPR-03', 'div' => 'opr'], // Network Cable Damaged

            // Software & Applications
            21 => ['code' => 'OPR-05', 'div' => 'opr'], // Outlook / Email Sending Error
            22 => ['code' => 'PRD-01', 'div' => 'prd'], // Accounting / ERP Software Crash
            23 => ['code' => 'OPR-02', 'div' => 'opr'], // Windows OS Activation Error
            24 => ['code' => 'OPR-02', 'div' => 'opr'], // Microsoft Office License Prompt
            25 => ['code' => 'OPR-10', 'div' => 'opr'], // Antivirus / Security Warning
            26 => ['code' => 'OPR-02', 'div' => 'opr'], // Browser / PDF Viewer Error

            // Business Systems & POS
            28 => ['code' => 'PRD-01', 'div' => 'prd'], // Business Central (BC) System Error
            29 => ['code' => 'PRD-01', 'div' => 'prd'], // BC Posting / Transaction Failed
            30 => ['code' => 'PRD-03', 'div' => 'prd'], // POS Software Crash / Freeze
            31 => ['code' => 'PRD-03', 'div' => 'prd'], // RFID / Warehouse System Sync
            32 => ['code' => 'PRD-04', 'div' => 'prd'], // Integration / API Sync Failure
            33 => ['code' => 'PRD-07', 'div' => 'prd'], // Power BI / Report Refresh Error

            // Facility, CCTV & Phone
            35 => ['code' => 'OPR-08', 'div' => 'opr'], // CCTV Camera Feed Offline
            36 => ['code' => 'OPR-09', 'div' => 'opr'], // IP Phone Not Ringing
            37 => ['code' => 'OPR-08', 'div' => 'opr'], // Meeting Room TV / Screen
            38 => ['code' => 'OPR-08', 'div' => 'opr'], // Door Access / Electronic Lock

            // Account & Access Issue
            40 => ['code' => 'OPR-06', 'div' => 'opr'], // Domain / Windows Password Locked
            41 => ['code' => 'PRD-02', 'div' => 'prd'], // Cannot Log In to BC / ERP
            42 => ['code' => 'OPR-04', 'div' => 'opr'], // Shared Network Drive Denied
            43 => ['code' => 'OPR-05', 'div' => 'opr'], // Microsoft 365 / Email Login Failed
            44 => ['code' => 'PRD-02', 'div' => 'prd'], // MFA / Two-Factor Issue

            // Account & Access Requests
            46 => ['code' => 'OPR-06', 'div' => 'opr'], // Domain / Windows Password Reset
            47 => ['code' => 'OPR-05', 'div' => 'opr'], // New Employee Email & Login
            48 => ['code' => 'OPR-04', 'div' => 'opr'], // Shared Network Drive Access
            49 => ['code' => 'OPR-02', 'div' => 'opr'], // Standard Software Installation
            50 => ['code' => 'PRD-02', 'div' => 'prd'], // ERP / BC Permission Set Request

            // Hardware & Equipment Requests
            52 => ['code' => 'OPR-01', 'div' => 'opr'], // Request Extra Monitor
            53 => ['code' => 'OPR-01', 'div' => 'opr'], // Request New Mouse / Keyboard
            54 => ['code' => 'OPR-07', 'div' => 'opr'], // Printer Setup on Workstation
            55 => ['code' => 'OPR-11', 'div' => 'opr'], // Office / Desk Relocation

            // Service Requests & Procurement
            57 => ['code' => 'OPR-01', 'div' => 'opr'], // Laptop / Desktop Procurement
            58 => ['code' => 'PRD-02', 'div' => 'prd'], // ERP / Accounting Advanced Permissions
            59 => ['code' => 'PRD-13', 'div' => 'prd'], // System Feature Enhancement
            60 => ['code' => 'OPR-17', 'div' => 'opr'], // Recommend IT Equipment Spec

            // Facility & Meeting Room Requests
            62 => ['code' => 'OPR-08', 'div' => 'opr'], // Meeting Room IT Setup
            63 => ['code' => 'OPR-08', 'div' => 'opr'], // CCTV Video Recording Review
            64 => ['code' => 'OPR-09', 'div' => 'opr'], // IP Telephone Extension Request
        ];

        foreach ($subMap as $id => $info) {
            TicketCategory::where('id', $id)->update([
                'code' => $info['code'],
                'it_division' => $info['div'],
            ]);
        }

        // 4. Backfill all existing tickets to match their category's it_division
        $tickets = Ticket::with('category')->get();
        foreach ($tickets as $t) {
            $div = $t->category?->it_division ?? 'opr';
            $t->update(['it_division' => $div]);
        }

        // 5. Add new standard categories from reference document that don't exist yet
        $newPrdCategories = [
            [
                'code' => 'PRD-05',
                'name' => 'Server & Infrastructure',
                'name_km' => 'ម៉ាស៊ីនបម្រើ & ហេដ្ឋារចនាសម្ព័ន្ធ',
                'description' => 'Physical server, VM, Hyper-V, SQL Server, and disaster recovery',
                'description_km' => 'ម៉ាស៊ីន Server មេ, VM, Hyper-V និងការគ្រប់គ្រង Server',
                'icon' => 'server',
                'icon_color' => 'indigo',
                'type' => 'incident_issue',
                'it_division' => 'prd',
                'subcategories' => [
                    ['name' => 'Production VM Down / Crashed', 'name_km' => 'ម៉ាស៊ីន VM បម្រើការគាំង ឬដាច់', 'type' => 'incident_issue', 'requires_approval' => false],
                    ['name' => 'Windows Server / Hyper-V Error', 'name_km' => 'បញ្ហាម៉ាស៊ីន Windows Server / Hyper-V', 'type' => 'incident_issue', 'requires_approval' => false],
                    ['name' => 'Provision New Virtual Server (VM)', 'name_km' => 'ស្នើសុំបង្កើតម៉ាស៊ីន VM ថ្មី', 'type' => 'service_request', 'requires_approval' => true],
                    ['name' => 'SSL Certificate Installation / Renewal', 'name_km' => 'ស្នើសុំដំឡើង/បន្តសុពលភាពវិញ្ញាបនបត្រ SSL', 'type' => 'service_request', 'requires_approval' => true],
                ],
            ],
            [
                'code' => 'PRD-06',
                'name' => 'Backup & Storage',
                'name_km' => 'ការបម្រុងទុកទិន្នន័យ & ទំហំផ្ទុក',
                'description' => 'Veeam backup, disaster recovery replication, and storage capacity',
                'description_km' => 'ប្រព័ន្ធបម្រុងទុក Veeam និងទំហំផ្ទុកទិន្នន័យ Server',
                'icon' => 'database',
                'icon_color' => 'purple',
                'type' => 'incident_issue',
                'it_division' => 'prd',
                'subcategories' => [
                    ['name' => 'Veeam Backup Failure Alert', 'name_km' => 'ការបម្រុងទុកទិន្នន័យ Veeam បរាជ័យ', 'type' => 'incident_issue', 'requires_approval' => false],
                    ['name' => 'Storage Drive 90%+ Capacity Warning', 'name_km' => 'ទំហំផ្ទុកទិន្នន័យជិតពេញ (លើស 90%)', 'type' => 'incident_issue', 'requires_approval' => false],
                    ['name' => 'Data / File Restoration Request', 'name_km' => 'ស្នើសុំស្រង់ទិន្នន័យដែលបានបម្រុងទុកមកវិញ', 'type' => 'service_request', 'requires_approval' => true],
                    ['name' => 'Expand Server Storage Capacity', 'name_km' => 'ស្នើសុំបន្ថែមទំហំផ្ទុក Storage', 'type' => 'service_request', 'requires_approval' => true],
                ],
            ],
            [
                'code' => 'PRD-08',
                'name' => 'Database & SQL',
                'name_km' => 'មូលដ្ឋានទិន្នន័យ & SQL Server',
                'description' => 'SQL Server, query deadlock, database performance, and data correction',
                'description_km' => 'ប្រព័ន្ធគ្រប់គ្រងទិន្នន័យ SQL Server និងការកែសម្រួលទិន្នន័យ',
                'icon' => 'code',
                'icon_color' => 'rose',
                'type' => 'incident_issue',
                'it_division' => 'prd',
                'subcategories' => [
                    ['name' => 'Database Query Deadlock / High CPU', 'name_km' => 'ប្រព័ន្ធទិន្នន័យគាំង ឬផ្ទុកទម្ងន់ខ្លាំង', 'type' => 'incident_issue', 'requires_approval' => false],
                    ['name' => 'Database Connection Failed', 'name_km' => 'មិនអាចភ្ជាប់ទៅកាន់ Database បាន', 'type' => 'incident_issue', 'requires_approval' => false],
                    ['name' => 'Execute Data Correction Script', 'name_km' => 'ស្នើសុំដំណើរការ Script កែសម្រួលទិន្នន័យ', 'type' => 'service_request', 'requires_approval' => true],
                    ['name' => 'Database Backup Copy for Testing / UAT', 'name_km' => 'ស្នើសុំចម្លង Database សម្រាប់ធ្វើតេស្ត UAT', 'type' => 'service_request', 'requires_approval' => true],
                ],
            ],
        ];

        foreach ($newPrdCategories as $catData) {
            $subcategories = $catData['subcategories'];
            unset($catData['subcategories']);

            $existing = TicketCategory::where('code', $catData['code'])->first();
            if (!$existing) {
                $parent = TicketCategory::create(array_merge($catData, [
                    'default_priority' => 'high',
                    'sla_critical_hours' => 2,
                    'sla_high_hours' => 4,
                    'sla_medium_hours' => 24,
                    'sla_low_hours' => 48,
                    'target_acknowledge_minutes' => 15,
                    'target_resolve_hours' => 4,
                    'is_active' => true,
                ]));

                foreach ($subcategories as $sub) {
                    TicketCategory::create([
                        'parent_id' => $parent->id,
                        'code' => $parent->code,
                        'name' => $sub['name'],
                        'name_km' => $sub['name_km'],
                        'type' => $sub['type'],
                        'it_division' => 'prd',
                        'requires_approval' => $sub['requires_approval'],
                        'default_priority' => 'high',
                        'sla_critical_hours' => 2,
                        'sla_high_hours' => 4,
                        'sla_medium_hours' => 24,
                        'sla_low_hours' => 48,
                        'target_acknowledge_minutes' => 15,
                        'target_resolve_hours' => 4,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}

