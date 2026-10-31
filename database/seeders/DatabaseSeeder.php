<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles
        $roles = [
            ['name' => 'requester', 'label' => 'Requester (Employee)', 'description' => 'Submits tickets and confirms resolutions via TMA'],
            ['name' => 'helpdesk', 'label' => 'Help Desk Specialist', 'description' => 'Triages new tickets, requests clarification, and assigns technicians'],
            ['name' => 'technician', 'label' => 'IT Technician', 'description' => 'Accepts and resolves assigned hardware, software, and network incidents'],
            ['name' => 'hod', 'label' => 'Head of Department', 'description' => 'Oversees operational queues, escalations, and technician workloads'],
            ['name' => 'system_admin', 'label' => 'System Administrator', 'description' => 'Manages users, roles, categories, and system configuration'],
        ];

        $roleModels = [];
        foreach ($roles as $r) {
            $roleModels[$r['name']] = Role::firstOrCreate(['name' => $r['name']], $r);
        }

        // 2. Branches
        $branches = [
            ['name' => 'CBVH Head Office Phnom Penh', 'code' => 'HQ-PP'],
            ['name' => 'Bak Touk Branch Phnom Penh', 'code' => 'BT-PP'],
            ['name' => 'Battambang Branch', 'code' => 'BTB-01'],
            ['name' => 'Vanhong – Siem Reap', 'code' => 'VH-SR'],
        ];
        $branchModels = [];
        foreach ($branches as $b) {
            $branchModels[$b['code']] = Branch::firstOrCreate(['code' => $b['code']], $b);
        }

        // 3. Departments
        $departments = [
            ['name' => 'Executive & Management', 'code' => 'MGT'],
            ['name' => 'Information Technology', 'code' => 'IT'],
            ['name' => 'Finance & Accounting', 'code' => 'FIN'],
            ['name' => 'Operations & Logistics', 'code' => 'OPS'],
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Sales & Customer Service', 'code' => 'SALES'],
            ['name' => 'Credit & Lending', 'code' => 'LOAN'],
        ];
        $deptModels = [];
        foreach ($departments as $d) {
            $deptModels[$d['code']] = Department::firstOrCreate(['code' => $d['code']], $d);
        }

        // 4. Pre-approved Employees Roster (TMA Bank Employees)
        $defaultPassword = Hash::make('Password123!');
        $employeesData = [
            ['employee_code' => 'EMP-1001', 'full_name' => 'Sokha Chan', 'email' => 'sokha.chan@cbvh.com.kh', 'phone' => '+855 12 111 222', 'branch' => 'HQ-PP', 'dept' => 'OPS', 'telegram_id' => 123456789, 'telegram_username' => 'sokhachan_demo', 'is_hod' => false],
            ['employee_code' => 'EMP-1002', 'full_name' => 'Bopha Keo', 'email' => 'bopha.keo@cbvh.com.kh', 'phone' => '+855 12 333 444', 'branch' => 'BT-PP', 'dept' => 'FIN', 'is_hod' => false],
            ['employee_code' => 'EMP-1003', 'full_name' => 'Vicheka Long', 'email' => 'vicheka.long@cbvh.com.kh', 'phone' => '+855 12 555 666', 'branch' => 'BTB-01', 'dept' => 'SALES', 'is_hod' => false],
            ['employee_code' => 'EMP-1004', 'full_name' => 'Rathana Chea', 'email' => 'rathana.chea@cbvh.com.kh', 'phone' => '+855 12 777 888', 'branch' => 'HQ-PP', 'dept' => 'HR', 'is_hod' => false],
            ['employee_code' => 'EMP-1005', 'full_name' => 'Seyha Pich', 'email' => 'seyha.pich@cbvh.com.kh', 'phone' => '+855 12 999 111', 'branch' => 'VH-SR', 'dept' => 'MGT', 'is_hod' => false],
            ['employee_code' => 'EMP-1006', 'full_name' => 'Sreyneang Mao', 'email' => 'sreyneang.mao@cbvh.com.kh', 'phone' => '+855 12 222 555', 'branch' => 'BT-PP', 'dept' => 'LOAN', 'is_hod' => false],
            ['employee_code' => 'EMP-1007', 'full_name' => 'Piseth Heng', 'email' => 'piseth.heng@cbvh.com.kh', 'phone' => '+855 12 444 888', 'branch' => 'VH-SR', 'dept' => 'SALES', 'is_hod' => false],
            ['employee_code' => 'EMP-1008', 'full_name' => 'Chantha Som (Operations HOD)', 'email' => 'hod.ops@cbvh.com.kh', 'phone' => '+855 12 777 999', 'branch' => 'HQ-PP', 'dept' => 'OPS', 'is_hod' => true],
            ['employee_code' => 'EMP-1009', 'full_name' => 'Chhayly Tep (Finance HOD)', 'email' => 'hod.fin@cbvh.com.kh', 'phone' => '+855 12 888 333', 'branch' => 'HQ-PP', 'dept' => 'FIN', 'is_hod' => true],
            ['employee_code' => 'EMP-1010', 'full_name' => 'Vireak Morm (Battambang Sales HOD)', 'email' => 'hod.btb@cbvh.com.kh', 'phone' => '+855 12 666 777', 'branch' => 'BTB-01', 'dept' => 'SALES', 'is_hod' => true],
        ];

        $empModels = [];
        foreach ($employeesData as $e) {
            $emp = Employee::firstOrCreate(
                ['employee_code' => $e['employee_code']],
                [
                    'full_name' => $e['full_name'],
                    'email' => $e['email'],
                    'phone' => $e['phone'],
                    'branch_id' => $branchModels[$e['branch']]->id,
                    'department_id' => $deptModels[$e['dept']]->id,
                    'is_active' => true,
                ]
            );
            $empModels[$e['employee_code']] = $emp;
        }

        // 5. Staff Users (IT Operations & Management Portal Team)
        // IT HOD & System Admin (User ID 1: Primary Testing Admin)
        $hodUser = User::firstOrCreate(
            ['email' => 'admin@cbvh.com.kh'],
            [
                'employee_id' => null, // Standalone IT Account
                'name' => 'Vannak Ouk (HOD / Admin)',
                'password' => $defaultPassword,
                'can_access_portal' => true,
                'is_active' => true,
            ]
        );
        $hodUser->roles()->syncWithoutDetaching([$roleModels['system_admin']->id, $roleModels['hod']->id]);

        // Senior Admin
        $dinaUser = User::firstOrCreate(
            ['email' => 'dina.lim@cbvh66.com'],
            [
                'employee_id' => null, // Standalone IT Account
                'name' => 'Dina LIM (Senior Admin)',
                'password' => $defaultPassword,
                'can_access_portal' => true,
                'is_active' => true,
            ]
        );
        $dinaUser->roles()->syncWithoutDetaching([$roleModels['system_admin']->id, $roleModels['hod']->id]);

        // Help Desk Specialist
        $helpdeskUser = User::firstOrCreate(
            ['email' => 'helpdesk@cbvh.com.kh'],
            [
                'employee_id' => null, // Standalone IT Account
                'name' => 'Kosal Meng (Help Desk)',
                'password' => $defaultPassword,
                'can_access_portal' => true,
                'is_active' => true,
            ]
        );
        $helpdeskUser->roles()->syncWithoutDetaching([$roleModels['helpdesk']->id]);

        // Technician 1 (Hardware / POS specialist)
        $tech1User = User::firstOrCreate(
            ['email' => 'tech1@cbvh.com.kh'],
            [
                'employee_id' => null, // Standalone IT Account
                'name' => 'Dara Sam (IT Technician)',
                'password' => $defaultPassword,
                'can_access_portal' => true,
                'is_active' => true,
            ]
        );
        $tech1User->roles()->syncWithoutDetaching([$roleModels['technician']->id]);

        // Technician 2 (Multi-role: Technician + Help Desk backup)
        $tech2User = User::firstOrCreate(
            ['email' => 'tech2@cbvh.com.kh'],
            [
                'employee_id' => null, // Standalone staff account
                'name' => 'Sopheap Nuon (Senior Tech & Triage)',
                'password' => $defaultPassword,
                'can_access_portal' => true,
                'is_active' => true,
            ]
        );
        $tech2User->roles()->syncWithoutDetaching([$roleModels['technician']->id, $roleModels['helpdesk']->id]);

        // 6. TMA Bank Employees & Department Approvers (Users)
        foreach ($employeesData as $e) {
            $user = User::firstOrCreate(
                ['email' => $e['email']],
                [
                    'employee_id' => $empModels[$e['employee_code']]->id,
                    'name' => $e['full_name'],
                    'telegram_id' => $e['telegram_id'] ?? null,
                    'telegram_username' => $e['telegram_username'] ?? null,
                    'password' => $defaultPassword,
                    'can_access_portal' => (bool) ($e['is_hod'] ?? false),
                    'is_active' => true,
                ]
            );

            $rolesToAttach = [$roleModels['requester']->id];
            if (!empty($e['is_hod'])) {
                $rolesToAttach[] = $roleModels['hod']->id;
            }
            $user->roles()->syncWithoutDetaching($rolesToAttach);
        }

        // 6. Company Holidays (Excluding Sundays which are handled by rule)
        $holidays = [
            ['name' => "New Year's Day", 'holiday_date' => '2026-01-01', 'is_recurring' => true, 'notes' => 'International New Year'],
            ['name' => "Victory Day", 'holiday_date' => '2026-01-07', 'is_recurring' => true, 'notes' => 'Victory over Genocide Day'],
            ['name' => "International Women's Day", 'holiday_date' => '2026-03-08', 'is_recurring' => true, 'notes' => "Women's Day"],
            ['name' => "Khmer New Year Day 1", 'holiday_date' => '2026-04-14', 'is_recurring' => false, 'notes' => 'Traditional New Year'],
            ['name' => "Khmer New Year Day 2", 'holiday_date' => '2026-04-15', 'is_recurring' => false, 'notes' => 'Traditional New Year'],
            ['name' => "Khmer New Year Day 3", 'holiday_date' => '2026-04-16', 'is_recurring' => false, 'notes' => 'Traditional New Year'],
            ['name' => "King's Birthday", 'holiday_date' => '2026-05-14', 'is_recurring' => true, 'notes' => "His Majesty's Birthday"],
            ['name' => "Pchum Ben Day 1", 'holiday_date' => '2026-10-10', 'is_recurring' => false, 'notes' => 'Ancestors Day'],
            ['name' => "Pchum Ben Day 2", 'holiday_date' => '2026-10-11', 'is_recurring' => false, 'notes' => 'Ancestors Day'],
            ['name' => "Pchum Ben Day 3", 'holiday_date' => '2026-10-12', 'is_recurring' => false, 'notes' => 'Ancestors Day'],
            ['name' => "Water Festival Day 1", 'holiday_date' => '2026-11-23', 'is_recurring' => false, 'notes' => 'Bon Om Touk'],
            ['name' => "Water Festival Day 2", 'holiday_date' => '2026-11-24', 'is_recurring' => false, 'notes' => 'Bon Om Touk'],
            ['name' => "Water Festival Day 3", 'holiday_date' => '2026-11-25', 'is_recurring' => false, 'notes' => 'Bon Om Touk'],
        ];

        foreach ($holidays as $h) {
            CompanyHoliday::firstOrCreate(['holiday_date' => $h['holiday_date'], 'name' => $h['name']], $h);
        }

        // 7. Ticket Categories
        $categories = [
            // --- Incident / Issue Categories (Report Issue View) ---
            [
                'name' => 'POS & Cashier Hardware',
                'type' => 'incident_issue',
                'requires_approval' => false,
                'default_priority' => 'high',
                'target_acknowledge_minutes' => 15,
                'target_resolve_hours' => 4,
                'children' => [
                    'Thermal Receipt Printer Offline',
                    'Barcode Scanner Not Reading',
                    'Cash Drawer Jammed / Not Ejecting',
                    'POS Touch Terminal Freezing',
                    'RFID Reader Not Scanning',
                ],
            ],
            [
                'name' => 'Hardware & Office Equipment',
                'type' => 'incident_issue',
                'requires_approval' => false,
                'default_priority' => 'medium',
                'target_acknowledge_minutes' => 30,
                'target_resolve_hours' => 24,
                'children' => [
                    'Hardware Failure',
                    'Desktop PC / Laptop Won\'t Boot',
                    'Monitor Blank or Flickering',
                    'Office Network Printer Paper Jam',
                    'Mouse / Keyboard Replacement',
                    'UPS / Power Backup Beeping or Offline',
                ],
            ],
            [
                'name' => 'Network & Internet Connectivity',
                'type' => 'incident_issue',
                'requires_approval' => false,
                'default_priority' => 'high',
                'target_acknowledge_minutes' => 20,
                'target_resolve_hours' => 8,
                'children' => [
                    'Office Wi-Fi Disconnected',
                    'Ethernet LAN Wall Port Inactive',
                    'VPN Connection Refused',
                    'Branch Main Internet Offline (Critical)',
                    'Network Cable Damaged / Replaced',
                ],
            ],
            [
                'name' => 'Software & Applications',
                'type' => 'incident_issue',
                'requires_approval' => false,
                'default_priority' => 'medium',
                'target_acknowledge_minutes' => 30,
                'target_resolve_hours' => 24,
                'children' => [
                    'Outlook / Email Sending Error',
                    'Accounting / ERP Software Crash',
                    'Windows OS Activation Error',
                    'Microsoft Office License Prompt',
                    'Antivirus / Security Warning Pop-up',
                    'Browser / PDF Viewer Error',
                ],
            ],
            [
                'name' => 'Business Systems & POS',
                'type' => 'incident_issue',
                'requires_approval' => false,
                'default_priority' => 'high',
                'target_acknowledge_minutes' => 20,
                'target_resolve_hours' => 8,
                'children' => [
                    'Business Central (BC) System Error / Timeout',
                    'BC Posting / Transaction Failed',
                    'POS Software Crash / Freeze',
                    'RFID / Warehouse System Sync Error',
                    'Integration / API Synchronization Failure',
                    'Power BI / Report Refresh Error',
                ],
            ],
            [
                'name' => 'Facility, CCTV & Phone',
                'type' => 'incident_issue',
                'requires_approval' => false,
                'default_priority' => 'medium',
                'target_acknowledge_minutes' => 45,
                'target_resolve_hours' => 24,
                'children' => [
                    'CCTV Camera Feed Offline / Black Screen',
                    'IP Phone / Telephone Line Not Ringing',
                    'Meeting Room TV / Screen Projection Issue',
                    'Door Access / Electronic Lock Issue',
                ],
            ],
            [
                'name' => 'Account & Access Issue',
                'type' => 'incident_issue',
                'requires_approval' => false,
                'default_priority' => 'high',
                'target_acknowledge_minutes' => 20,
                'target_resolve_hours' => 4,
                'children' => [
                    'Domain / Windows Password Locked Out',
                    'Cannot Log In to Business Central / ERP',
                    'Shared Network Drive / Folder Permission Denied',
                    'Microsoft 365 / Email Login Failed',
                    'MFA / Two-Factor Authentication Issue',
                ],
            ],

            // --- Service Request Categories (New Service Request View) ---
            [
                'name' => 'Account & Access Requests',
                'type' => 'service_request',
                'requires_approval' => false,
                'default_priority' => 'low',
                'target_acknowledge_minutes' => 60,
                'target_resolve_hours' => 48,
                'children' => [
                    'Domain / Windows Password Reset',
                    'New Employee Email & Login Creation',
                    'Shared Network Drive Folder Access',
                    'Standard Software Installation Request',
                    'ERP / Business Central Permission Set Request',
                ],
            ],
            [
                'name' => 'Hardware & Equipment Requests',
                'type' => 'service_request',
                'requires_approval' => true,
                'default_priority' => 'medium',
                'target_acknowledge_minutes' => 60,
                'target_resolve_hours' => 48,
                'children' => [
                    'Request Extra Monitor / Display',
                    'Request New Mouse / Keyboard / Cable',
                    'Printer Setup on Workstation',
                    'Office / Desk Relocation (Floor Move)',
                ],
            ],
            [
                'name' => 'Service Requests & Procurement',
                'type' => 'service_request',
                'requires_approval' => true,
                'default_priority' => 'medium',
                'target_acknowledge_minutes' => 60,
                'target_resolve_hours' => 72,
                'children' => [
                    'Laptop / Desktop Procurement Request',
                    'ERP / Accounting Advanced Permissions',
                    'System Feature Enhancement Request',
                    'Recommend IT Equipment Spec',
                ],
            ],
            [
                'name' => 'Facility & Meeting Room Requests',
                'type' => 'service_request',
                'requires_approval' => false,
                'default_priority' => 'low',
                'target_acknowledge_minutes' => 60,
                'target_resolve_hours' => 48,
                'children' => [
                    'Meeting Room IT Setup & Video Conference Support',
                    'CCTV Video Recording Review Request',
                    'IP Telephone Extension Request',
                ],
            ],
        ];

        foreach ($categories as $cat) {
            $children = $cat['children'];
            unset($cat['children']);
            if (!isset($cat['name_km'])) {
                $cat['name_km'] = match($cat['name']) {
                    'POS & Cashier Hardware' => 'ប្រព័ន្ធ POS & ឧបករណ៍គិតលុយ',
                    'Hardware & Office Equipment' => 'កុំព្យូទ័រ & ឧបករណ៍ការិយាល័យ',
                    'Network & Internet Connectivity' => 'ប្រព័ន្ធបណ្តាញ & អ៊ីនធឺណិត',
                    'Software & Applications' => 'កម្មវិធីកុំព្យូទ័រ & ប្រព័ន្ធប្រតិបត្តិការ',
                    'Account & Access Requests' => 'ការស្នើសុំគណនី & សិទ្ធិចូលប្រព័ន្ធ',
                    'Account & Access' => 'គណនី & សិទ្ធិចូលប្រព័ន្ធ',
                    'Hardware & Equipment' => 'កុំព្យូទ័រ & ឧបករណ៍បច្ចេកវិទ្យា',
                    'Software & Application' => 'កម្មវិធីកុំព្យូទ័រ & អាជ្ញាប័ណ្ណ',
                    'Email & Collaboration' => 'អ៊ីមែល & Microsoft 365',
                    'Network & Connectivity' => 'ប្រព័ន្ធបណ្តាញ & ការតភ្ជាប់',
                    'Business Systems' => 'ប្រព័ន្ធគ្រប់គ្រងអាជីវកម្ម (ERP)',
                    'Facility & Other' => 'សេវា IT ផ្សេងៗ & បន្ទប់ប្រជុំ',
                    'Business Systems & POS' => 'ប្រព័ន្ធអាជីវកម្ម & POS',
                    'Facility, CCTV & Phone' => 'បន្ទប់ប្រជុំ, CCTV & ទូរស័ព្ទ',
                    'Account & Access Issue' => 'បញ្ហាគណនី & សិទ្ធិចូលប្រព័ន្ធ',
                    'Email & Collaboration Issue' => 'បញ្ហាអ៊ីមែល & ការទំនាក់ទំនង',
                    'Service Requests & Procurement' => 'សំណើស្នើសុំឧបករណ៍ & អាជ្ញាប័ណ្ណ',
                    'Facility & Meeting Room Requests' => 'ការស្នើសុំបន្ទប់ប្រជុំ & សេវាទូទៅ',
                    default => null,
                };
            }
            $parent = TicketCategory::firstOrCreate(['name' => $cat['name'], 'parent_id' => null], $cat);

            foreach ($children as $childName) {
                TicketCategory::firstOrCreate(
                    ['parent_id' => $parent->id, 'name' => $childName],
                    [
                    'parent_id' => $parent->id,
                    'name' => $childName,
                    'name_km' => match($childName) {
                        'Thermal Receipt Printer Offline' => 'ម៉ាស៊ីនព្រីនវិក្កយបត្រកម្តៅដាច់ការតភ្ជាប់',
                        'Barcode Scanner Not Reading' => 'ម៉ាស៊ីនស្កេនបាកូដមិនដំណើរការ',
                        'Cash Drawer Jammed / Not Ejecting' => 'ថតដាក់ប្រាក់គាំង / មិនលោតចេញ',
                        'POS Touch Terminal Freezing' => 'អេក្រង់ Touch ម៉ាស៊ីន POS គាំង',
                        'POS Cashier Sync Error' => 'បញ្ហាធ្វើសមកាលកម្មទិន្នន័យម៉ាស៊ីន POS',
                        "Desktop PC / Laptop Won't Boot" => 'កុំព្យូទ័រលើតុ / យួរដៃ បើកមិនចេញ',
                        'Monitor Blank or Flickering' => 'អេក្រង់ងងឹត ឬញាក់',
                        'Office Network Printer Paper Jam' => 'ម៉ាស៊ីនព្រីនបណ្តាញការិយាល័យជាប់ក្រដាស',
                        'Mouse / Keyboard Replacement' => 'ប្តូរកណ្តុរ (Mouse) ឬក្តារចុច (Keyboard)',
                        'Laptop Procurement' => 'ការស្នើសុំទិញកុំព្យូទ័រយួរដៃ (Laptop)',
                        'Desktop Setup' => 'រៀបចំដំឡើងកុំព្យូទ័រលើតុ (Desktop)',
                        'Monitor / Display' => 'ស្នើសុំអេក្រង់កុំព្យូទ័រ',
                        'Printer / Scanner Request' => 'ស្នើសុំម៉ាស៊ីនព្រីន / ស្កេន',
                        'Network Device / Cable' => 'ស្នើសុំឧបករណ៍បណ្តាញ ឬខ្សែបណ្តាញ',
                        'Request Extra Monitor / Display' => 'ស្នើសុំបន្ថែមអេក្រង់កុំព្យូទ័រ',
                        'Request New Mouse / Keyboard / Cable' => 'ស្នើសុំកណ្តុរ / ក្តារចុច / ខ្សែតភ្ជាប់ថ្មី',
                        'Printer Setup on Workstation' => 'ដំឡើងម៉ាស៊ីនព្រីនលើកុំព្យូទ័រការងារ',
                        'Office / Desk Relocation (Floor Move)' => 'ការផ្លាស់ប្តូរតុធ្វើការ / ទីតាំងការិយាល័យ',
                        'Laptop / Desktop Procurement Request' => 'ការស្នើសុំទិញកុំព្យូទ័រយួរដៃ / លើតុ',
                        'Recommend IT Equipment Spec' => 'ការស្នើសុំការណែនាំលក្ខណៈបច្ចេកទេសបរិក្ខារ IT',
                        'Office Wi-Fi Disconnected' => 'ដាច់ការតភ្ជាប់ Wi-Fi ការិយាល័យ',
                        'Ethernet LAN Wall Port Inactive' => 'រន្ធដោតខ្សែ LAN មិនដំណើរការ',
                        'VPN Connection Refused' => 'ភ្ជាប់ប្រព័ន្ធ VPN មិនចូល',
                        'Branch Main Internet Offline (Critical)' => 'ដាច់អ៊ីនធឺណិតមេនៅសាខា (បន្ទាន់)',
                        'Wi-Fi Access / Guest Account' => 'ការចូលប្រើ Wi-Fi / គណនីភ្ញៀវ',
                        'VPN Remote Access Request' => 'ការស្នើសុំចូលប្រើ VPN ពីចម្ងាយ',
                        'Static IP / Port Allocation' => 'ការស្នើសុំកំណត់លេខ IP ថេរ ឬរន្ធ Port',
                        'Outlook / Email Sending Error' => 'បញ្ហាផ្ញើអ៊ីមែល ឬកម្មវិធី Outlook',
                        'Accounting / ERP Software Crash' => 'កម្មវិធីគណនេយ្យ / ERP គាំង',
                        'Windows OS Activation Error' => 'បញ្ហាអាជ្ញាប័ណ្ណ Windows OS',
                        'Microsoft Office License Prompt' => 'ទាមទារអាជ្ញាប័ណ្ណ Microsoft Office',
                        'Software Installation' => 'ការដំឡើងកម្មវិធី',
                        'License Request' => 'ការស្នើសុំអាជ្ញាប័ណ្ណកម្មវិធី',
                        'Application Access' => 'ការស្នើសុំសិទ្ធិចូលកម្មវិធី',
                        'ERP / Accounting Setup' => 'ការដំឡើងប្រព័ន្ធ ERP / គណនេយ្យ',
                        'Domain / Windows Password Reset' => 'កំណត់ពាក្យសម្ងាត់ Windows ឡើងវិញ',
                        'New Employee Email & Login Creation' => 'បង្កើតអ៊ីមែល និងគណនីបុគ្គលិកថ្មី',
                        'Shared Network Drive Folder Access' => 'ស្នើសុំសិទ្ធិចូលថតឯកសារ Shared Folder',
                        'Standard Software Installation Request' => 'ស្នើសុំដំឡើងកម្មវិធីស្តង់ដារ',
                        'ERP / Business Central Permission Set Request' => 'ស្នើសុំកំណត់សិទ្ធិប្រើប្រាស់ Business Central',
                        'New User Account' => 'បង្កើតគណនីអ្នកប្រើប្រាស់ថ្មី',
                        'System & Application Access' => 'សិទ្ធិចូលប្រើប្រព័ន្ធ និងកម្មវិធី',
                        'Permission / Role Change' => 'ផ្លាស់ប្តូរសិទ្ធិ ឬតួនាទី',
                        'Password Reset' => 'កំណត់ពាក្យសម្ងាត់ឡើងវិញ',
                        'Account Locked / Password Expired' => 'គណនីជាប់សោ ឬហួសសុពលភាពពាក្យសម្ងាត់',
                        'Email Account Creation' => 'ការបង្កើតគណនីអ៊ីមែល',
                        'Microsoft 365 License' => 'ការស្នើសុំអាជ្ញាប័ណ្ណ Microsoft 365',
                        'Teams & SharePoint Access' => 'សិទ្ធិចូលប្រើ Teams និង SharePoint',
                        'Shared Mailbox Access' => 'សិទ្ធិចូលប្រើប្រអប់សំបុត្ររួម (Shared Mailbox)',
                        'Cannot Send / Receive Email' => 'មិនអាចផ្ញើ ឬទទួលអ៊ីមែលបាន',
                        'Business Central Access' => 'សិទ្ធិចូលប្រើ Business Central',
                        'POS System Setup' => 'ការដំឡើងប្រព័ន្ធលក់ POS',
                        'RFID / Scanner Integration' => 'ការភ្ជាប់ប្រព័ន្ធ RFID / ម៉ាស៊ីនស្កេន',
                        'Humica HR Access' => 'សិទ្ធិចូលប្រើប្រព័ន្ធ Humica HR',
                        'Bio-Start Biometrics' => 'ប្រព័ន្ធស្កេនក្រយៅដៃ Bio-Start',
                        'Business Central Error' => 'បញ្ហាប្រព័ន្ធ Business Central',
                        'RFID Reader Malfunction' => 'បញ្ហាម៉ាស៊ីនអាន RFID',
                        'Humica / Payroll System Down' => 'ប្រព័ន្ធ Humica / បើកប្រាក់ខែដាច់',
                        'Business Central (BC) System Error / Timeout' => 'ប្រព័ន្ធ Business Central (BC) មានបញ្ហា / Timeout',
                        'BC Posting / Transaction Failed' => 'ប្រតិបត្តិការ BC Posting បរាជ័យ',
                        'POS Software Crash / Freeze' => 'កម្មវិធី POS គាំង / មិនដំណើរការ',
                        'RFID / Warehouse System Sync Error' => 'បញ្ហា Sync ប្រព័ន្ធ RFID / ឃ្លាំងទំនិញ',
                        'Integration / API Synchronization Failure' => 'បញ្ហាភ្ជាប់ API ឬសមកាលកម្មទិន្នន័យ',
                        'Power BI / Report Refresh Error' => 'បញ្ហា Refresh របាយការណ៍ Power BI',
                        'ERP / Accounting Advanced Permissions' => 'សិទ្ធិកម្រិតខ្ពស់លើប្រព័ន្ធ ERP / គណនេយ្យ',
                        'System Feature Enhancement Request' => 'ការស្នើសុំកែលម្អមុខងារប្រព័ន្ធ',
                        'Meeting Room AV / Projector' => 'ឧបករណ៍ប្រជុំ / ម៉ាស៊ីនបញ្ចាំងស្លាយ Projector',
                        'CCTV Footage Request' => 'ការស្នើសុំឆែកវីដេអូកាមេរ៉ាសុវត្ថិភាព CCTV',
                        'Office IP Phone Extension' => 'ការស្នើសុំលេខទូរស័ព្ទ IP ផ្ទៃក្នុង',
                        'CCTV Camera Offline' => 'កាមេរ៉ាសុវត្ថិភាព CCTV ដាច់',
                        'Desk Phone / Extension Down' => 'ទូរស័ព្ទលើតុ / លេខផ្ទៃក្នុងដាច់',
                        'Meeting Room Screen Issue' => 'បញ្ហាអេក្រង់បន្ទប់ប្រជុំ',
                        'CCTV Camera Feed Offline / Black Screen' => 'កាមេរ៉ា CCTV ដាច់ / អេក្រង់ខ្មៅ',
                        'IP Phone / Telephone Line Not Ringing' => 'ទូរស័ព្ទ IP / ខ្សែទូរស័ព្ទមិនរោទ៍',
                        'Meeting Room TV / Screen Projection Issue' => 'បញ្ហាបញ្ចាំងរូបភាពទូរទស្សន៍ / អេក្រង់បន្ទប់ប្រជុំ',
                        'Door Access / Electronic Lock Issue' => 'បញ្ហាសោទ្វារអេឡិចត្រូនិច / ស្កេនទ្វារ',
                        'Meeting Room IT Setup & Video Conference Support' => 'រៀបចំប្រព័ន្ធ IT បន្ទប់ប្រជុំ និងសន្និសីទវីដេអូ',
                        'CCTV Video Recording Review Request' => 'ការស្នើសុំត្រួតពិនិត្យវីដេអូកាមេរ៉ាសុវត្ថិភាព',
                        'IP Telephone Extension Request' => 'ការស្នើសុំលេខទូរស័ព្ទ IP ផ្ទៃក្នុងថ្មី',
                        'Other' => 'ផ្សេងៗ',
                        'Other / Unlisted' => 'ផ្សេងៗ / មិនមានក្នុងបញ្ជី',
                        default => null,
                    },
                    'type' => $parent->type,
                    'requires_approval' => (bool) ($parent->requires_approval ?? false),
                    'default_priority' => $parent->default_priority,
                    'target_acknowledge_minutes' => $parent->target_acknowledge_minutes,
                    'target_resolve_hours' => $parent->target_resolve_hours,
                    'is_active' => true,
                ]);
            }
        }
    }
}
