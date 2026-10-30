<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyHoliday extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Services\BusinessHoursService::clearHolidayCache();
        });
        static::deleted(function () {
            \App\Services\BusinessHoursService::clearHolidayCache();
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
