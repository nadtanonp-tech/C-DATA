<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 🚀 Model สำหรับ View ที่เก็บ record ล่าสุดของแต่ละ instrument
 * ใช้แทนการ query whereNotExists ที่ช้า
 */
class LatestCalibrationLog extends Model
{
    // ใช้ View แทน Table
    protected $table = 'latest_calibration_logs';

    // View ไม่มี timestamps
    public $timestamps = false;

    protected $casts = [
        'cal_date' => 'date',
        'next_cal_date' => 'date',
        'calibration_data' => 'array',
    ];

    /**
     * ความสัมพันธ์กับ Instrument
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
