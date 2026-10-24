<?php

namespace App\Services;

use App\Models\CompanyHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BusinessHoursService
{
    /**
     * Check if a given date is a working day (excluding Sundays and custom holidays).
     */
    public function isWorkingDay(CarbonInterface $date): bool
    {
        // 1. Sundays are non-working days
        if ($date->isSunday()) {
            return false;
        }

        // 2. Check custom company holidays table (Database-agnostic)
        $dateString = $date->format('Y-m-d');
        $monthDay = $date->format('m-d');

        $holidays = CompanyHoliday::all();

        foreach ($holidays as $holiday) {
            $holidayCarbon = Carbon::parse($holiday->holiday_date);
            if ($holidayCarbon->format('Y-m-d') === $dateString) {
                return false;
            }
            if ($holiday->is_recurring && $holidayCarbon->format('m-d') === $monthDay) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add N business days to a starting date, skipping non-working days.
     */
    public function addBusinessDays(CarbonInterface $startDate, int $days): Carbon
    {
        $current = Carbon::instance($startDate);
        $added = 0;

        while ($added < $days) {
            $current->addDay();
            if ($this->isWorkingDay($current)) {
                $added++;
            }
        }

        return $current;
    }

    /**
     * Calculate the auto-close deadline (default 3 business days / 72 hours).
     */
    public function calculateAutoCloseDeadline(CarbonInterface $resolvedAt, int $businessDays = 3): Carbon
    {
        return $this->addBusinessDays($resolvedAt, $businessDays);
    }

    /**
     * Determine if a deadline has passed.
     */
    public function isOverdue(?CarbonInterface $deadline): bool
    {
        if (!$deadline) {
            return false;
        }

        return Carbon::now()->greaterThan($deadline);
    }
}
