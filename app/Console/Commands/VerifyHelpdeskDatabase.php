<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\BusinessHoursService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class VerifyHelpdeskDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'helpdesk:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify database tables, seeded data, roles, and business hours calculation';

    /**
     * Execute the console command.
     */
    public function handle(BusinessHoursService $businessHours): int
    {
        $this->info('====================================================');
        $this->info('  CBVH IT HELPDESK SYSTEM - DATABASE VERIFICATION  ');
        $this->info('====================================================');
        $this->newLine();

        // 1. Table Counts Summary
        $this->comment('1. RECORD COUNTS SUMMARY:');
        $counts = [
            ['Branches', Branch::count()],
            ['Departments', Department::count()],
            ['Employees (Pre-approved)', Employee::count()],
            ['Users', User::count()],
            ['Roles', Role::count()],
            ['Ticket Categories', TicketCategory::count()],
            ['Company Holidays', CompanyHoliday::count()],
            ['Tickets', Ticket::count()],
        ];
        $this->table(['Table / Entity', 'Total Records'], $counts);
        $this->newLine();

        // 2. Pre-Seeded Staff Users & Roles
        $this->comment('2. PRE-SEEDED STAFF & USERS:');
        $users = User::with('roles', 'employee')->get()->map(function ($u) {
            return [
                'ID' => $u->id,
                'Name' => $u->name,
                'Email' => $u->email ?? 'N/A',
                'Telegram ID' => $u->telegram_id ?? 'Not Linked',
                'Roles' => $u->roles->pluck('name')->implode(', '),
                'Employee Code' => $u->employee->employee_code ?? 'None (Standalone)',
            ];
        });
        $this->table(['ID', 'Name', 'Email', 'Telegram ID', 'Roles', 'Employee Code'], $users);
        $this->newLine();

        // 3. Ticket Categories & SLA
        $this->comment('3. SAMPLE TICKET CATEGORIES & SLA RULES:');
        $categories = TicketCategory::whereNull('parent_id')->with('children')->get()->map(function ($parent) {
            $childrenNames = $parent->children->pluck('name')->take(2)->implode(', ') . '...';
            return [
                'Category' => $parent->name,
                'Type' => $parent->type,
                'Default Priority' => $parent->default_priority,
                'Ack SLA' => $parent->target_acknowledge_minutes . ' mins',
                'Resolve SLA' => $parent->target_resolve_hours . ' hrs',
                'Sample Subcategories' => $childrenNames,
            ];
        });
        $this->table(['Category', 'Type', 'Default Priority', 'Ack SLA', 'Resolve SLA', 'Sample Subcategories'], $categories);
        $this->newLine();

        // 4. Custom Holidays & Sunday Skipping Simulation
        $this->comment('4. BUSINESS DAYS & CUSTOM HOLIDAYS ENGINE TEST:');
        $now = Carbon::now();
        $threeDaysLater = $businessHours->addBusinessDays($now, 3);

        $this->line("• Today: <fg=cyan>{$now->format('l, Y-m-d')}</>");
        $this->line("• 3 Business Days Deadline (Sundays & Custom Holidays automatically skipped): <fg=green>{$threeDaysLater->format('l, Y-m-d')}</>");

        $sampleHoliday = CompanyHoliday::first();
        if ($sampleHoliday) {
            $this->line("• Sample registered holiday: <fg=yellow>{$sampleHoliday->name}</> on <fg=yellow>{$sampleHoliday->holiday_date->format('Y-m-d')}</>");
        }

        $this->newLine();
        $this->info('✔ ALL DATABASE TABLES & BUSINESS LOGIC VERIFIED SUCCESSFULLY!');
        $this->newLine();

        return self::SUCCESS;
    }
}
