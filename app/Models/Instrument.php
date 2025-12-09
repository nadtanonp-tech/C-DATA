<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Department;


class Instrument extends Model
{
    use HasFactory;

    protected $guarded = [];

    // 🟢 แปลงวันที่ให้เป็น Carbon Object อัตโนมัติ (จะสะดวกเวลาคำนวณวันหมดอายุ)
    protected $casts = [
        'receive_date'  => 'date',
        'next_cal_date' => 'date',
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
}