<?php

namespace App\Observers;

use App\Models\Instrument;
use App\Models\Master;

class InstrumentObserver
{
    /**
     * Handle the Instrument "created" event.
     */
    public function created(Instrument $instrument): void
    {
        //
    }

    /**
     * Handle the Instrument "updated" event.
     */
    public function updated(Instrument $instrument): void
    {
        //
    }

    /**
     * Handle the Instrument "deleted" event.
     */
    public function deleted(Instrument $instrument): void
    {
        Master::where('master_code', $instrument->code_no)->delete();
    }

    /**
     * Handle the Instrument "restored" event.
     */
    public function restored(Instrument $instrument): void
    {
        //
    }

    /**
     * Handle the Instrument "force deleted" event.
     */
    public function forceDeleted(Instrument $instrument): void
    {
        //
    }

    public function saved(Instrument $instrument): void
    {
        // 1. เช็คว่าเป็น Master หรือไม่?
        if ($instrument->equip_type === 'Master') {
            
            // 🚀 ดึงข้อมูลจาก Type แม่ (ผ่าน Relation)
            $toolType = $instrument->toolType; 

            // ตรวจสอบว่ามี Type ไหม (กัน Error)
            $masterName = $toolType ? $toolType->name : ($instrument->name ?? 'Unknown');
            $masterSize = $toolType ? $toolType->size : ($instrument->range_spec ?? '-');

            // 2. สร้างหรืออัปเดต Master
            Master::updateOrCreate(
                ['master_code' => $instrument->code_no], 
                [
                    'name'           => $masterName, // ✅ ใช้ชื่อจาก Tool Type
                    'size'           => $masterSize, // ✅ ใช้ขนาดจาก Tool Type
                    
                    'serial_no'      => $instrument->serial_no,
                    'cal_place'      => $instrument->cal_place,
                    'due_date'       => $instrument->next_cal_date,
                    'last_cal_date'  => $instrument->receive_date,
                ]
            );
            
        } else {
            // 3. ถ้าไม่ใช่ Master ให้ลบออก
            Master::where('master_code', $instrument->code_no)->delete();
        }
    }
}
