<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalCalPlan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'plan_month' => 'date',
        'cal_date' => 'date',
        'next_cal_date' => 'date',
    ];

    /**
     * ความสัมพันธ์กับ Instrument
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    /**
     * ความสัมพันธ์กับ CalibrationRecord (calibration_logs)
     */
    public function calibrationLog(): BelongsTo
    {
        return $this->belongsTo(CalibrationRecord::class, 'calibration_log_id');
    }
}
