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

    // ความสัมพันธ์: ประวัติการเปลี่ยนผู้รับผิดชอบ
    public function ownershipHistories(): HasMany
    {
        return $this->hasMany(InstrumentOwnershipHistory::class);
    }

    // Virtual Attribute สำหรับรับค่า Remark จาก Form
    public $ownership_remark = null;
    public $status_remark = null; // รับค่าเหตุผลการเปลี่ยนสถานะ

    /**
     * 🔥 Boot Method - ทำงานอัตโนมัติทุกครั้งที่ Save
     */
    protected static function boot()
    {
        parent::boot();
        
        // Event: ก่อนที่จะบันทึกข้อมูล (Create หรือ Update)
        static::saving(function ($instrument) {
            // 1. Logic เดิม: Auto Name from ToolType
            if ($instrument->tool_type_id) {
                $toolType = ToolType::find($instrument->tool_type_id);
                if ($toolType) {
                    $instrument->name = $toolType->code_type;
                }
            }

            // 🟢 Fix: ป้องกัน Error "Column not found: ownership_remark"
            // ย้ายค่าจาก attributes (ที่ fill มา) ไปใส่ public property แทน แล้วลบออกจาก query
            if (array_key_exists('ownership_remark', $instrument->attributes)) {
                $instrument->ownership_remark = $instrument->attributes['ownership_remark'];
                unset($instrument->attributes['ownership_remark']);
            }

            // 🟢 Fix: ป้องกัน Error สำหรับ status_remark (ถ้ามีการส่งค่ามา)
            if (array_key_exists('status_remark', $instrument->attributes)) {
                $instrument->status_remark = $instrument->attributes['status_remark'];
                unset($instrument->attributes['status_remark']);
            }
        });

        // Event: หลังจากบันทึกเสร็จแล้ว (Created หรือ Updated)
        // Event: หลังจากบันทึกเสร็จแล้ว (Created หรือ Updated)
        static::saved(function ($instrument) {
            // ตรวจสอบว่ามีการเปลี่ยน Owner, Department, หรือ Machine หรือไม่
            if ($instrument->wasChanged(['owner_id', 'department_id', 'machine_name'])) {
                
                // หาชื่อแผนกเดิม
                $oldDeptId = $instrument->getOriginal('department_id');
                $oldDeptName = null;
                if ($oldDeptId) {
                    $oldDept = Department::find($oldDeptId);
                    $oldDeptName = $oldDept ? $oldDept->name : null;
                }

                // หาชื่อแผนกใหม่
                $newDeptName = null;
                if ($instrument->department_id) {
                    // ถ้ายังไม่โหลด relation ให้โหลด/หาใหม่ (แต่ saved แล้ว relation อาจจะยังไม่อัปเดต ต้องระวัง)
                    // ใช้ find ชัวร์สุด
                    $newDept = Department::find($instrument->department_id);
                    $newDeptName = $newDept ? $newDept->name : null;
                }

                $instrument->ownershipHistories()->create([
                    // Owner
                    'old_owner_id' => $instrument->getOriginal('owner_id'),
                    'old_owner_name' => $instrument->getOriginal('owner_name'),
                    'owner_id' => $instrument->owner_id,
                    'owner_name' => $instrument->owner_name,
                    
                    // Department
                    'old_department_name' => $oldDeptName,
                    'department_name' => $newDeptName,

                    // Machine
                    'old_machine_name' => $instrument->getOriginal('machine_name'),
                    'machine_name' => $instrument->machine_name,

                    // Meta
                    'remark' => $instrument->ownership_remark,
                    'changed_by_user_id' =>  auth()->id(),
                ]);
            }

            // 🟢 ตรวจสอบว่ามีการเปลี่ยน Status หรือไม่
            if ($instrument->wasChanged('status')) {
                $instrument->statusHistories()->create([
                    'old_status' => $instrument->getOriginal('status'),
                    'new_status' => $instrument->status,
                    'reason' => $instrument->status_remark, // virtual attribute
                    'changed_by_user_id' => auth()->id(),
                    // 'changed_at' will be auto-set by created_at or we can set it explicitly if needed
                    'changed_at' => now(),
                ]);
            }
        });
    }
}