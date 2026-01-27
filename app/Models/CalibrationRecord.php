<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalibrationRecord extends Model
{
    use HasFactory;

    // อนุญาตให้แก้ไขได้ทุกฟิลด์
    protected $guarded = [];

    // กำหนดชื่อตารางให้ตรงกับ Migration (เพราะไม่ได้ใช้ชื่อ default: calibration_records)
    protected $table = 'calibration_logs';

    // 🔥 บรรทัดนี้สำคัญที่สุด: บอกว่า calibration_data และ environment คือ JSON (Array)
    protected $casts = [
        'calibration_data' => 'array', 
        'environment' => 'array', 
        'cal_date' => 'date',
        'next_cal_date' => 'date',
    ];

    // ความสัมพันธ์กลับไปหาเครื่องมือ
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    /**
     * 🔗 เชื่อมกับ User (ผู้สอบเทียบ)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'cal_by');
    }

    /**
     * 🔗 เชื่อมกับ Purchasing Record (สำหรับ External Cal)
     */
    public function purchasingRecord(): BelongsTo
    {
        return $this->belongsTo(PurchasingRecord::class, 'purchasing_record_id');
    }

    /**
     * 🔥 Boot Method - ทำงานอัตโนมัติเมื่อสร้างระเบียนใหม่
     */
    protected static function boot()
    {
        parent::boot();
        
        // Event: เมื่อกำลังจะสร้างระเบียนใหม่ (ก่อน Save)
        static::creating(function ($record) {
            // ถ้ายังไม่มีการตั้งค่า cal_place ให้ตั้งเป็น Internal อัตโนมัติ
            if (empty($record->cal_place)) {
                $record->cal_place = 'Internal';
            }
        });
    }
}