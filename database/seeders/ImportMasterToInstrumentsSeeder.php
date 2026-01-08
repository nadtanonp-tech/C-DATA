<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportMasterToInstrumentsSeeder extends Seeder
{
    private function cleanText($text)
    {
        if ($text === null) return null;
        if (is_numeric($text)) $text = (string) $text;
        $text = trim($text);
        if ($text === '' || $text === '-') return null;
        return mb_substr($text, 0, 255);
    }

    public function run()
    {
        // 1. ดึงข้อมูลจากตาราง MasterAll2
        $masterAll2Records = DB::table('MasterAll2')->get();

        $created = 0;
        $skipped = 0;

        foreach ($masterAll2Records as $row) {
            
            // 2. ดึงรหัส Master (CodeNoMaster1)
            $masterCode = $this->cleanText($row->CodeNoMaster1 ?? null);
            
            // ถ้าไม่มีรหัส Master ให้ข้าม
            if (!$masterCode) {
                $skipped++;
                continue;
            }

            // 3. เช็คว่ามี Instrument นี้อยู่แล้วหรือไม่
            $existingInstrument = DB::table('instruments')
                ->where('code_no', $masterCode)
                ->exists();

            if ($existingInstrument) {
                $skipped++;
                continue; // ข้ามถ้ามีอยู่แล้ว
            }

            // 4. หา tool_type_id จาก CodeNoType
            $codeType = $this->cleanText($row->CodeNoType ?? null);
            $toolTypeId = null;

            if ($codeType) {
                $toolType = DB::table('tool_types')
                    ->where('code_type', $codeType)
                    ->select('id')
                    ->first();
                
                if ($toolType) {
                    $toolTypeId = $toolType->id;
                }
            }

            // 5. สร้าง Instrument ใหม่ด้วยค่า Default
            DB::table('instruments')->insert([
                'code_no'         => $masterCode,
                'tool_type_id'    => $toolTypeId,
                'name'            => $codeType,           // ชื่อ = code_type
                'status'          => 'ใช้งาน',           // Default
                'equip_type'      => 'Master',           // Default (เพราะเป็น Master)
                'cal_place'       => 'Internal',         // Default
                'cal_freq_months' => 12,                 // Default
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $created++;
        }

        $this->command->info("✅ สร้าง Instruments ใหม่: {$created} รายการ");
        $this->command->info("⏭️ ข้าม (มีอยู่แล้ว/ไม่มีรหัส): {$skipped} รายการ");
    }
}
