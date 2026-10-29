<?php

namespace App\Services;

use App\Models\CompanyHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BusinessHoursService
{
    /**
     * CBVH Standard Operating Hours:
     * Timezone: Asia/Phnom_Penh (UTC+7)
     * Monday through Saturday: 08:00 AM to 05:00 PM (17:00)
     * Sunday: Rest Day (Off-clock)
     * Public Holidays: Custom official holidays (Off-clock)
     */
    public const TIMEZONE = 'Asia/Phnom_Penh';
    public const WORK_START_HOUR = 8;
    public const WORK_START_MINUTE = 0;
    public const WORK_END_HOUR = 17;
    public const WORK_END_MINUTE = 0;

    /**
     * Check if a given date is a working day (Monday-Saturday, excluding Sundays and custom holidays).
     */
    public function isWorkingDay(CarbonInterface $date): bool
    {
        $d = Carbon::instance($date)->setTimezone(self::TIMEZONE);

        // 1. Sundays are non-working days
        if ($d->isSunday()) {
            return false;
        }

        // 2. Check custom company holidays table (Database-agnostic)
        $dateString = $d->format('Y-m-d');
        $monthDay = $d->format('m-d');

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
     * Check if a given timestamp is within active banking working hours.
     */
    public function isWorkingTime(?CarbonInterface $time = null): bool
    {
        $current = $time ? Carbon::instance($time)->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);

        if (!$this->isWorkingDay($current)) {
            return false;
        }

        $workStart = $current->copy()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
        $workEnd = $current->copy()->setTime(self::WORK_END_HOUR, self::WORK_END_MINUTE, 0);

        return $current->greaterThanOrEqualTo($workStart) && $current->lessThan($workEnd);
    }

    /**
     * Calculate active working minutes elapsed between two timestamps.
     * Automatically excludes off-hours (17:00-08:00), Sundays, and company holidays.
     */
    public function getWorkingMinutesBetween(CarbonInterface $startDate, CarbonInterface $endDate): int
    {
        $start = Carbon::instance($startDate)->setTimezone(self::TIMEZONE);
        $end = Carbon::instance($endDate)->setTimezone(self::TIMEZONE);

        if ($start->greaterThanOrEqualTo($end)) {
            return 0;
        }

        $totalMinutes = 0;
        $current = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($current->lessThanOrEqualTo($endDay)) {
            if ($this->isWorkingDay($current)) {
                $workStart = $current->copy()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
                $workEnd = $current->copy()->setTime(self::WORK_END_HOUR, self::WORK_END_MINUTE, 0);

                // Effective start is the later of $start or $workStart
                $effectiveStart = $start->greaterThan($workStart) ? $start->copy() : $workStart->copy();
                // Effective end is the earlier of $end or $workEnd
                $effectiveEnd = $end->lessThan($workEnd) ? $end->copy() : $workEnd->copy();

                if ($effectiveStart->lessThan($effectiveEnd)) {
                    $totalMinutes += $effectiveStart->diffInMinutes($effectiveEnd);
                }
            }

            $current->addDay();
        }

        return (int) $totalMinutes;
    }

    /**
     * Calculate active working hours elapsed between two timestamps.
     */
    public function getWorkingHoursBetween(CarbonInterface $startDate, CarbonInterface $endDate): float
    {
        return round($this->getWorkingMinutesBetween($startDate, $endDate) / 60, 2);
    }

    /**
     * Add N active working minutes to a start date, skipping off-hours, Sundays, and holidays.
     * Used to calculate the exact future SLA target deadline.
     */
    public function addWorkingMinutes(CarbonInterface $startDate, int $minutes): Carbon
    {
        $current = Carbon::instance($startDate)->setTimezone(self::TIMEZONE);
        if ($minutes <= 0) {
            return $current;
        }

        $remainingMinutes = $minutes;

        while ($remainingMinutes > 0) {
            // If current day is not a working day, fast forward to next day at 08:00
            if (!$this->isWorkingDay($current)) {
                $current->addDay()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
                continue;
            }

            $workStart = $current->copy()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
            $workEnd = $current->copy()->setTime(self::WORK_END_HOUR, self::WORK_END_MINUTE, 0);

            // If current time is before work start, move to work start
            if ($current->lessThan($workStart)) {
                $current = $workStart->copy();
            }

            // If current time is at or after work end, move to next day at work start
            if ($current->greaterThanOrEqualTo($workEnd)) {
                $current->addDay()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
                continue;
            }

            // Minutes available today from $current until $workEnd
            $availableToday = $current->diffInMinutes($workEnd);

            if ($remainingMinutes <= $availableToday) {
                $current->addMinutes($remainingMinutes);
                $remainingMinutes = 0;
            } else {
                $remainingMinutes -= $availableToday;
                // Move to next day at work start
                $current->addDay()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
            }
        }

        return $current;
    }

    /**
     * Add N active working hours to a start date.
     */
    public function addWorkingHours(CarbonInterface $startDate, float|int $hours): Carbon
    {
        return $this->addWorkingMinutes($startDate, (int) round($hours * 60));
    }

    /**
     * Add N business days to a starting date, skipping non-working days.
     */
    public function addBusinessDays(CarbonInterface $startDate, int $days): Carbon
    {
        $current = Carbon::instance($startDate)->setTimezone(self::TIMEZONE);
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

        return Carbon::now(self::TIMEZONE)->greaterThan($deadline);
    }
}
