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
     * Lunch Break: 12:00 PM to 01:00 PM (13:00) - SLA Frozen
     * Active Working Hours: 8 hours per day (4h Morning + 4h Afternoon)
     * Sunday: Rest Day (Off-clock / 24h Frozen)
     * Public Holidays: Custom official holidays (Off-clock / Frozen)
     */
    public const TIMEZONE = 'Asia/Phnom_Penh';
    public const WORK_START_HOUR = 8;
    public const WORK_START_MINUTE = 0;
    public const LUNCH_START_HOUR = 12;
    public const LUNCH_START_MINUTE = 0;
    public const LUNCH_END_HOUR = 13;
    public const LUNCH_END_MINUTE = 0;
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
     * Check if a given timestamp is during the 12:00 PM - 01:00 PM lunch break.
     */
    public function isLunchBreak(?CarbonInterface $time = null): bool
    {
        $current = $time ? Carbon::instance($time)->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);

        if (!$this->isWorkingDay($current)) {
            return false;
        }

        $lunchStart = $current->copy()->setTime(self::LUNCH_START_HOUR, self::LUNCH_START_MINUTE, 0);
        $lunchEnd = $current->copy()->setTime(self::LUNCH_END_HOUR, self::LUNCH_END_MINUTE, 0);

        return $current->greaterThanOrEqualTo($lunchStart) && $current->lessThan($lunchEnd);
    }

    /**
     * Check if a given timestamp is within active working hours (08:00-12:00 or 13:00-17:00).
     */
    public function isWorkingTime(?CarbonInterface $time = null): bool
    {
        $current = $time ? Carbon::instance($time)->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);

        if (!$this->isWorkingDay($current)) {
            return false;
        }

        $morningStart = $current->copy()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
        $morningEnd = $current->copy()->setTime(self::LUNCH_START_HOUR, self::LUNCH_START_MINUTE, 0);
        $afternoonStart = $current->copy()->setTime(self::LUNCH_END_HOUR, self::LUNCH_END_MINUTE, 0);
        $afternoonEnd = $current->copy()->setTime(self::WORK_END_HOUR, self::WORK_END_MINUTE, 0);

        $isMorning = $current->greaterThanOrEqualTo($morningStart) && $current->lessThan($morningEnd);
        $isAfternoon = $current->greaterThanOrEqualTo($afternoonStart) && $current->lessThan($afternoonEnd);

        return $isMorning || $isAfternoon;
    }

    /**
     * Calculate active working minutes elapsed between two timestamps.
     * Automatically excludes off-hours (17:00-08:00), lunch break (12:00-13:00), Sundays, and company holidays.
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
                $windows = [
                    // Morning shift: 08:00 - 12:00
                    [
                        $current->copy()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0),
                        $current->copy()->setTime(self::LUNCH_START_HOUR, self::LUNCH_START_MINUTE, 0),
                    ],
                    // Afternoon shift: 13:00 - 17:00
                    [
                        $current->copy()->setTime(self::LUNCH_END_HOUR, self::LUNCH_END_MINUTE, 0),
                        $current->copy()->setTime(self::WORK_END_HOUR, self::WORK_END_MINUTE, 0),
                    ],
                ];

                foreach ($windows as [$windowStart, $windowEnd]) {
                    $effectiveStart = $start->greaterThan($windowStart) ? $start->copy() : $windowStart->copy();
                    $effectiveEnd = $end->lessThan($windowEnd) ? $end->copy() : $windowEnd->copy();

                    if ($effectiveStart->lessThan($effectiveEnd)) {
                        $totalMinutes += $effectiveStart->diffInMinutes($effectiveEnd);
                    }
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
     * Add N active working minutes to a start date, skipping off-hours, lunch break (12:00-13:00), Sundays, and holidays.
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

            $morningStart = $current->copy()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
            $morningEnd = $current->copy()->setTime(self::LUNCH_START_HOUR, self::LUNCH_START_MINUTE, 0);
            $afternoonStart = $current->copy()->setTime(self::LUNCH_END_HOUR, self::LUNCH_END_MINUTE, 0);
            $afternoonEnd = $current->copy()->setTime(self::WORK_END_HOUR, self::WORK_END_MINUTE, 0);

            // 1. If before morning start, move to 08:00
            if ($current->lessThan($morningStart)) {
                $current = $morningStart->copy();
            }

            // 2. If inside morning window [08:00, 12:00)
            if ($current->lessThan($morningEnd)) {
                $available = $current->diffInMinutes($morningEnd);
                if ($remainingMinutes <= $available) {
                    $current->addMinutes($remainingMinutes);
                    $remainingMinutes = 0;
                    break;
                }
                $remainingMinutes -= $available;
                // Jump to afternoon start at 13:00 (skipping 12:00-13:00 lunch)
                $current = $afternoonStart->copy();
            }

            // 3. If during lunch break [12:00, 13:00), jump to 13:00
            if ($current->lessThan($afternoonStart)) {
                $current = $afternoonStart->copy();
            }

            // 4. If inside afternoon window [13:00, 17:00)
            if ($current->lessThan($afternoonEnd)) {
                $available = $current->diffInMinutes($afternoonEnd);
                if ($remainingMinutes <= $available) {
                    $current->addMinutes($remainingMinutes);
                    $remainingMinutes = 0;
                    break;
                }
                $remainingMinutes -= $available;
                // Move to next day at 08:00
                $current->addDay()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
                continue;
            }

            // 5. If at or after 17:00, move to next day at 08:00
            $current->addDay()->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE, 0);
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
