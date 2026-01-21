<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchasingRecord extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'pr_date' => 'date',
        'send_date' => 'date',
        'receive_date' => 'date',
        'expected_return_date' => 'date',
        'estimated_price' => 'decimal:2',
        'net_price' => 'decimal:2',
    ];

    /**
     * 🔗 เชื่อมกับ Instrument
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    /**
     * 🔗 เชื่อมกับ Calibration Log (ผลสอบเทียบ)
     */
    public function calibrationLog(): BelongsTo
    {
        return $this->belongsTo(CalibrationRecord::class, 'calibration_log_id');
    }

    /**
     * 🔄 Relation ย้อนกลับ - ถ้า Cal Result เชื่อมมาที่นี่
     */
    public function calibrationResult(): HasOne
    {
        return $this->hasOne(CalibrationRecord::class, 'purchasing_record_id');
    }
}
