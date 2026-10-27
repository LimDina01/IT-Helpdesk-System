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
            $roleModels[$r['name']] = Role::create($r);
        }

        // 2. Branches
        $branches = [
            ['name' => 'Phnom Penh Head Office', 'code' => 'HQ-PP'],
            ['name' => 'Toul Kork Branch', 'code' => 'TK-01'],
            ['name' => 'Downtown Branch', 'code' => 'DT-02'],
            ['name' => 'Siem Reap Regional Office', 'code' => 'SR-01'],
        ];
        $branchModels = [];
        foreach ($branches as $b) {
            $branchModels[$b['code']] = Branch::create($b);
        }

        // 3. Departments
        $departments = [
            ['name' => 'Executive & Management', 'code' => 'MGT'],
            ['name' => 'Information Technology', 'code' => 'IT'],
            ['name' => 'Finance & Accounting', 'code' => 'FIN'],
            ['name' => 'Operations & Logistics', 'code' => 'OPS'],
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Sales & Customer Service', 'code' => 'SALES'],
        ];
        $deptModels = [];
        foreach ($departments as $d) {
            $deptModels[$d['code']] = Department::create($d);
        }

        // 4. Pre-approved Employees Roster
        $employeesData = [
            ['employee_code' => 'EMP-1001', 'full_name' => 'Sokha Chan', 'email' => 'sokha.chan@cbvh.com.kh', 'phone' => '+855 12 111 222', 'branch' => 'HQ-PP', 'dept' => 'OPS'],
            ['employee_code' => 'EMP-1002', 'full_name' => 'Bopha Keo', 'email' => 'bopha.keo@cbvh.com.kh', 'phone' => '+855 12 333 444', 'branch' => 'TK-01', 'dept' => 'FIN'],
            ['employee_code' => 'EMP-1003', 'full_name' => 'Vicheka Long', 'email' => 'vicheka.long@cbvh.com.kh', 'phone' => '+855 12 555 666', 'branch' => 'DT-02', 'dept' => 'SALES'],
            ['employee_code' => 'EMP-1004', 'full_name' => 'Rathana Chea', 'email' => 'rathana.chea@cbvh.com.kh', 'phone' => '+855 12 777 888', 'branch' => 'HQ-PP', 'dept' => 'HR'],
            ['employee_code' => 'EMP-1005', 'full_name' => 'Dara Sam', 'email' => 'dara.sam@cbvh.com.kh', 'phone' => '+855 12 999 000', 'branch' => 'HQ-PP', 'dept' => 'IT'],
            ['employee_code' => 'EMP-1006', 'full_name' => 'Kosal Meng', 'email' => 'kosal.meng@cbvh.com.kh', 'phone' => '+855 12 222 333', 'branch' => 'HQ-PP', 'dept' => 'IT'],
            ['employee_code' => 'EMP-1007', 'full_name' => 'Vannak Ouk', 'email' => 'vannak.ouk@cbvh.com.kh', 'phone' => '+855 12 444 555', 'branch' => 'HQ-PP', 'dept' => 'IT'],
            ['employee_code' => 'EMP-1008', 'full_name' => 'Chantha Som', 'email' => 'hod.ops@cbvh.com.kh', 'phone' => '+855 12 777 999', 'branch' => 'HQ-PP', 'dept' => 'OPS'],
        ];

        $empModels = [];
        foreach ($employeesData as $e) {
            $empModels[$e['employee_code']] = Employee::create([
                'employee_code' => $e['employee_code'],
                'full_name' => $e['full_name'],
                'email' => $e['email'],
                'phone' => $e['phone'],
                'branch_id' => $branchModels[$e['branch']]->id,
                'department_id' => $deptModels[$e['dept']]->id,
                'is_active' => true,
            ]);
        }

        // 5. Staff Users
        $defaultPassword = Hash::make('Password123!');

        // IT HOD & System Admin (Process 2 Approver: IT Manager)
        $hodUser = User::create([
            'employee_id' => $empModels['EMP-1007']->id,
            'name' => 'Vannak Ouk (HOD / Admin)',
            'email' => 'admin@cbvh.com.kh',
            'password' => $defaultPassword,
            'is_active' => true,
        ]);
        $hodUser->roles()->attach([$roleModels['system_admin']->id, $roleModels['hod']->id]);

        // Operations Department HOD (Process 1 Approver: Requester Department HOD)
        $opsHodUser = User::create([
            'employee_id' => $empModels['EMP-1008']->id,
            'name' => 'Chantha Som (Operations HOD)',
            'email' => 'hod.ops@cbvh.com.kh',
            'password' => $defaultPassword,
            'is_active' => true,
        ]);
        $opsHodUser->roles()->attach([$roleModels['hod']->id]);

        // Help Desk Specialist
        $helpdeskUser = User::create([
            'employee_id' => $empModels['EMP-1006']->id,
            'name' => 'Kosal Meng (Help Desk)',
            'email' => 'helpdesk@cbvh.com.kh',
            'password' => $defaultPassword,
            'is_active' => true,
        ]);
        $helpdeskUser->roles()->attach([$roleModels['helpdesk']->id]);

        // Technician 1 (Hardware / POS specialist)
        $tech1User = User::create([
            'employee_id' => $empModels['EMP-1005']->id,
            'name' => 'Dara Sam (IT Technician)',
            'email' => 'tech1@cbvh.com.kh',
            'password' => $defaultPassword,
            'is_active' => true,
        ]);
        $tech1User->roles()->attach([$roleModels['technician']->id]);

        // Technician 2 (Multi-role: Technician + Help Desk backup)
        $tech2User = User::create([
            'employee_id' => null, // Standalone staff account
            'name' => 'Sopheap Nuon (Senior Tech & Triage)',
            'email' => 'tech2@cbvh.com.kh',
            'password' => $defaultPassword,
            'is_active' => true,
        ]);
        $tech2User->roles()->attach([$roleModels['technician']->id, $roleModels['helpdesk']->id]);

        // Requester (Already linked employee for testing TMA)
        $requesterUser = User::create([
            'employee_id' => $empModels['EMP-1001']->id,
            'telegram_id' => 123456789,
            'telegram_username' => 'sokhachan_demo',
            'name' => 'Sokha Chan',
            'email' => 'sokha.chan@cbvh.com.kh',
            'password' => $defaultPassword,
            'is_active' => true,
        ]);
        $requesterUser->roles()->attach([$roleModels['requester']->id]);

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
            CompanyHoliday::create($h);
        }

        // 7. Ticket Categories
        $categories = [
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
                    'Office / Desk Relocation (Floor Move)',
                    'ERP / Accounting Advanced Permissions',
                    'System Feature Enhancement Request',
                ],
            ],
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
                ],
            ],
        ];

        foreach ($categories as $cat) {
            $children = $cat['children'];
            unset($cat['children']);
            $parent = TicketCategory::create($cat);

            foreach ($children as $childName) {
                TicketCategory::create([
                    'parent_id' => $parent->id,
                    'name' => $childName,
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
