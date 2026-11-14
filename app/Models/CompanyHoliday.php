<?php

namespace App\Models;

use App\Services\BusinessHoursService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyHoliday extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function () {
            BusinessHoursService::clearHolidayCache();
        });
        static::deleted(function () {
            BusinessHoursService::clearHolidayCache();
        });
    }

    protected $fillable = [
        'name',
        'holiday_date',
        'is_recurring',
        'notes',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_recurring' => 'boolean',
    ];
}
