<?php

namespace Tests\Unit;

use App\Models\CompanyHoliday;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\BusinessHoursService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessHoursServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessHoursService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusinessHoursService();
    }

    public function test_monday_through_saturday_are_working_days_and_sunday_is_rest_day(): void
    {
        // 2026-10-26 is Monday
        $monday = Carbon::parse('2026-10-26 10:00:00');
        // 2026-10-31 is Saturday
        $saturday = Carbon::parse('2026-10-31 10:00:00');
        // 2026-10-25 is Sunday
        $sunday = Carbon::parse('2026-10-25 10:00:00');

        $this->assertTrue($this->service->isWorkingDay($monday));
        $this->assertTrue($this->service->isWorkingDay($saturday));
        $this->assertFalse($this->service->isWorkingDay($sunday));
    }

    public function test_company_holidays_are_automatically_excluded(): void
    {
        CompanyHoliday::create([
            'name' => 'Pchum Ben Day',
            'holiday_date' => '2026-10-28',
            'is_recurring' => false,
        ]);

        $holidayWednesday = Carbon::parse('2026-10-28 10:00:00');
        $normalThursday = Carbon::parse('2026-10-29 10:00:00');

        $this->assertFalse($this->service->isWorkingDay($holidayWednesday));
        $this->assertTrue($this->service->isWorkingDay($normalThursday));
    }

    public function test_working_hours_window_is_8am_to_5pm(): void
    {
        $duringWorkingHours = Carbon::parse('2026-10-26 14:00:00'); // Mon 2 PM
        $beforeWorkingHours = Carbon::parse('2026-10-26 07:30:00'); // Mon 7:30 AM
        $afterWorkingHours = Carbon::parse('2026-10-26 18:00:00');  // Mon 6 PM
        $sundayNoon = Carbon::parse('2026-10-25 12:00:00');         // Sun 12 PM

        $this->assertTrue($this->service->isWorkingTime($duringWorkingHours));
        $this->assertFalse($this->service->isWorkingTime($beforeWorkingHours));
        $this->assertFalse($this->service->isWorkingTime($afterWorkingHours));
        $this->assertFalse($this->service->isWorkingTime($sundayNoon));
    }

    public function test_saturday_evening_to_monday_morning_working_minutes(): void
    {
        // Saturday 4:30 PM (30 minutes before 5:00 PM close)
        $saturday430pm = Carbon::parse('2026-10-24 16:30:00');
        // Monday 9:30 AM (1 hour 30 mins after 8:00 AM open)
        $monday930am = Carbon::parse('2026-10-26 09:30:00');

        $workingMinutes = $this->service->getWorkingMinutesBetween($saturday430pm, $monday930am);

        // Sat: 30m, Sun: 0m, Mon: 90m => Total = 120m (2 hours)
        $this->assertEquals(120, $workingMinutes);
        $this->assertEquals(2.0, $this->service->getWorkingHoursBetween($saturday430pm, $monday930am));
    }

    public function test_saturday_ticket_deadline_crosses_weekend_correctly(): void
    {
        // Saturday 4:30 PM with 8 hours (480 minutes) SLA target
        $saturday430pm = Carbon::parse('2026-10-24 16:30:00');
        $deadline = $this->service->addWorkingMinutes($saturday430pm, 480);

        // Saturday uses 30 mins (until 17:00), remaining 450 mins (7.5 hours) on Monday
        // Monday 08:00 to 12:00 = 4h (240 mins). Lunch 12:00-13:00 (paused).
        // Monday 13:00 to 16:30 = 3.5h (210 mins) => Monday 16:30 (4:30 PM)
        $this->assertEquals('2026-10-26 16:30:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_lunch_break_is_excluded_from_working_minutes(): void
    {
        // Monday 11:30 AM to Monday 01:30 PM (2 wall-clock hours, includes 12:00-13:00 lunch)
        $monday1130am = Carbon::parse('2026-10-26 11:30:00');
        $monday130pm = Carbon::parse('2026-10-26 13:30:00');

        $workingMinutes = $this->service->getWorkingMinutesBetween($monday1130am, $monday130pm);

        // Morning: 30 mins (11:30 - 12:00). Lunch: 0 mins. Afternoon: 30 mins (13:00 - 13:30) => 60 mins (1 hour)
        $this->assertEquals(60, $workingMinutes);
        $this->assertEquals(1.0, $this->service->getWorkingHoursBetween($monday1130am, $monday130pm));
    }

    public function test_lunch_break_shifts_sla_deadline_by_one_hour(): void
    {
        // Ticket submitted Monday at 11:00 AM with a 2-hour (120 mins) SLA target
        $monday11am = Carbon::parse('2026-10-26 11:00:00');
        $deadline = $this->service->addWorkingMinutes($monday11am, 120);

        // 11:00 to 12:00 = 1 hour (60 mins). Lunch 12:00 to 13:00 paused.
        // 13:00 to 14:00 = 1 hour (60 mins) => Deadline should be 14:00 (2:00 PM)
        $this->assertEquals('2026-10-26 14:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_is_lunch_break_detection(): void
    {
        $duringLunch = Carbon::parse('2026-10-26 12:30:00'); // Mon 12:30 PM
        $beforeLunch = Carbon::parse('2026-10-26 11:59:00'); // Mon 11:59 AM
        $afterLunch = Carbon::parse('2026-10-26 13:00:00');  // Mon 1:00 PM
        $sundayLunch = Carbon::parse('2026-10-25 12:30:00'); // Sun 12:30 PM (rest day)

        $this->assertTrue($this->service->isLunchBreak($duringLunch));
        $this->assertFalse($this->service->isLunchBreak($beforeLunch));
        $this->assertFalse($this->service->isLunchBreak($afterLunch));
        $this->assertFalse($this->service->isLunchBreak($sundayLunch));
    }

    public function test_ticket_model_uses_business_hours_and_calculates_remaining_hours(): void
    {
        $category = TicketCategory::create([
            'name' => 'Printer Hardware Issue',
            'code' => 'PRINT-01',
            'type' => 'incident_issue',
            'target_acknowledge_minutes' => 30,
            'target_resolve_hours' => 8,
            'is_active' => true,
        ]);

        // Mock test time to Monday 2026-10-26 10:00 AM
        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00'));

        // Ticket created Saturday 2026-10-24 at 16:30 (4:30 PM)
        $ticket = new Ticket();
        $ticket->category_id = $category->id;
        $ticket->created_at = Carbon::parse('2026-10-24 16:30:00');
        $ticket->status = 'in_progress';

        // Set relationship
        $ticket->setRelation('category', $category);

        // Sat: 30 mins, Sun: 0 mins, Mon 08:00 to 10:00: 120 mins => Total = 150 mins active (2.5 hours)
        $this->assertEquals(150, $ticket->getActiveMinutesElapsed());
        $this->assertEquals(2, $ticket->getActiveHoursElapsed());

        // Target: 8h - 2h elapsed = 6h remaining
        $this->assertEquals(6, $ticket->getSlaRemainingHours());
        $this->assertFalse($ticket->isSlaOverdue());

        // Target deadline: Monday 2026-10-26 at 16:30 (4:30 PM, factoring in 12:00-13:00 lunch)
        $this->assertEquals('2026-10-26 16:30:00', $ticket->getSlaDeadline()->format('Y-m-d H:i:s'));

        Carbon::setTestNow(null);
    }
}
