<?php

namespace Tests\Feature;

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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeded_data_exists(): void
    {
        $this->assertGreaterThan(0, Role::count());
        $this->assertGreaterThan(0, Branch::count());
        $this->assertGreaterThan(0, Department::count());
        $this->assertGreaterThan(0, Employee::count());
        $this->assertGreaterThan(0, User::count());
        $this->assertGreaterThan(0, TicketCategory::count());
        $this->assertGreaterThan(0, CompanyHoliday::count());
    }

    public function test_multi_role_user_permissions(): void
    {
        $tech2 = User::where('email', 'tech2@cbvh.com.kh')->first();
        $this->assertNotNull($tech2);
        $this->assertTrue($tech2->hasRole('technician'));
        $this->assertTrue($tech2->hasRole('helpdesk'));
        $this->assertFalse($tech2->hasRole('hod'));
    }

    public function test_ticket_creation_and_code_generation(): void
    {
        $requester = User::where('email', 'sokha.chan@cbvh.com.kh')->first();
        $branch = Branch::first();
        $category = TicketCategory::whereNotNull('parent_id')->first();

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'requester_id' => $requester->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'status' => 'submitted',
            'priority' => 'medium',
            'subject' => 'Printer offline in sales floor',
            'description' => 'Thermal receipt printer is not receiving power.',
        ]);

        $this->assertStringStartsWith('IT-', $ticket->ticket_code);
        $this->assertTrue($ticket->isSubmitted());
        $this->assertEquals($requester->id, $ticket->requester->id);
    }

    public function test_business_hours_service_skips_sundays_and_holidays(): void
    {
        $service = new BusinessHoursService();

        // 2026-10-25 is a Sunday
        $sunday = Carbon::parse('2026-10-25');
        $this->assertFalse($service->isWorkingDay($sunday));

        // 2026-10-26 is a Monday (working day)
        $monday = Carbon::parse('2026-10-26');
        $this->assertTrue($service->isWorkingDay($monday));

        // 2026-01-01 is New Year's Day (registered holiday)
        $holiday = Carbon::parse('2026-01-01');
        $this->assertFalse($service->isWorkingDay($holiday));
    }
}
