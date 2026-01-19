<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ToolType;
use App\Models\Department;

class Instrument extends Model
{
    use HasFactory;

    protected $guarded = [];

    // 🟢 แปลงวันที่ให้เป็น Carbon Object อัตโนมัติ (จะสะดวกเวลาคำนวณวันหมดอายุ)
    protected $casts = [
        'receive_date'   => 'date',
        'next_cal_date'  => 'date',
        'criteria_unit'  => 'array',  // 🔥 เพิ่ม criteria_unit เป็น JSON
    ];

    // ความสัมพันธ์: เครื่องมือนี้ เป็นของ Type ไหน?
    public function toolType(): BelongsTo
    {
        // 'tool_type_id' คือชื่อฟิลด์ Foreign Key ในตาราง instruments
        return $this->belongsTo(ToolType::class, 'tool_type_id');
    }

    // ความสัมพันธ์: ประวัติการยืม
    public function borrows(): HasMany
    {
        return $this->hasMany(Borrow::class);
    }

    // ความสัมพันธ์: ประวัติการสอบเทียบ
    public function calibrationLogs(): HasMany
    {
        return $this->hasMany(CalibrationLog::class);
    }

    // ความสัมพันธ์: ของเครื่องมือเป็นของแผนกไหน?
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // ความสัมพันธ์: ประวัติการเปลี่ยนสถานะ
    public function statusHistories(): HasMany
    {
        return $this->hasMany(InstrumentStatusHistory::class);
    }

    /**
     * 🔥 Boot Method - ทำงานอัตโนมัติทุกครั้งที่ Save
     */
    protected static function boot()
    {
        parent::boot();
        
        // Event: ก่อนที่จะบันทึกข้อมูล (Create หรือ Update)
        static::saving(function ($instrument) {
            // ถ้ามีการเลือก tool_type_id
            if ($instrument->tool_type_id) {
                // ดึงข้อมูล ToolType มา
                $toolType = ToolType::find($instrument->tool_type_id);
                
                // ถ้าเจอข้อมูล
                if ($toolType) {
                    // บังคับให้ name = code_type ของ tool_type
                    $instrument->name = $toolType->code_type;
                }
            }
        });
    }
}