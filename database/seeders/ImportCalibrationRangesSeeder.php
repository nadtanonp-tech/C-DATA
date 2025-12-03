<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportCalibrationRangesSeeder extends Seeder
{
    /**
     * ฟังก์ชัน cleanText แบบเดียวกับที่คุณเขียน
     */
    private function cleanText($text)
    {
        if ($text === null) return null;
        if (is_numeric($text)) $text = (string) $text;
        $text = trim($text);
        // ตัดเหลือ 255 พอสำหรับ range/criteria
        return mb_substr($text, 0, 255); 
    }

    public function run()
    {
        // 1. ดึงข้อมูลจาก DB เก่า
        $oldTypes = DB::table('Type')->get(); // หรือ DB::connection('mysql_old')->table('Type')->get()

        foreach ($oldTypes as $oldRow) {
            
            // 2. หา ID ใหม่ จากตาราง tool_types ที่เพิ่งลงข้อมูลไป
            $codeTypeRaw = $oldRow->CodeType;
            if (!$codeTypeRaw) continue;

            $newToolType = DB::table('tool_types')
                            ->where('code_type', trim($codeTypeRaw))
                            ->first();

            if (!$newToolType) continue; // ถ้าไม่เจอแม่ ก็ข้ามลูกไป

            // 3. วนลูปเก็บ Range 1-15
            for ($i = 1; $i <= 15; $i++) {
                
                $rangeVal     = $this->cleanText($oldRow->{'Range' . $i} ?? null);
                $criteriaMain = $this->cleanText($oldRow->{'Criteria' . $i} ?? null);
                $criteriaSub  = $this->cleanText($oldRow->{'Criteria' . $i . '-' . $i} ?? null);
                $unit         = $this->cleanText($oldRow->{'Unit' . $i} ?? null);

                // ถ้าว่างทุกช่อง ให้ข้าม (ไม่บันทึกแถวว่าง)
                if (!$rangeVal && !$criteriaMain && !$criteriaSub && !$unit) {
                    continue;
                }

                // 4. บันทึกลงตารางลูก
                DB::table('calibration_ranges')->insert([
                    'tool_type_id'  => $newToolType->id,
                    'sequence'      => $i,
                    'range_value'   => $rangeVal,
                    'criteria_main' => $criteriaMain,
                    'criteria_sub'  => $criteriaSub,
                    'unit'          => $unit,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }
}