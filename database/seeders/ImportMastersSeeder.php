<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportMastersSeeder extends Seeder
{
    /**
     * ฟังก์ชัน cleanText แบบเดิม
     */
    private function cleanText($text)
    {
        if ($text === null) return null;
        if (is_numeric($text)) $text = (string) $text;
        $text = trim($text);
        return mb_substr($text, 0, 255);
    }

    public function run()
    {
        // 🔥 ลบข้อมูลเก่าก่อน Import
        $this->command->warn('⚠️ กำลังลบข้อมูล masters เก่า...');
        DB::table('masters')->delete();
        $this->command->info('🗑️ ลบข้อมูล masters เก่าเรียบร้อยแล้ว');

        // 1. ดึงข้อมูลจากตาราง Master เก่า
        // (ชื่อตาราง 'Master' ตามที่คุณเคยบอกไว้)
        $oldMasters = DB::table('Master')->get(); 

        foreach ($oldMasters as $oldRow) {
            
            // เตรียมข้อมูล
            $masterCode = $this->cleanText($oldRow->CodeNoMaster ?? null);
            $name       = $this->cleanText($oldRow->NameMaster ?? null);

            // ถ้าไม่มี Code หรือ Name ให้ข้าม (Data ต้องมี key)
            if (!$masterCode) continue;
            if (!$name) $name = 'Unknown Master';

            // 2. บันทึกลงตาราง masters ใหม่
            DB::table('masters')->insert([
                'master_code'    => $masterCode,
                'name'           => $name,
                'size'           => $this->cleanText($oldRow->SizeMaster ?? null),
                'serial_no'      => $this->cleanText($oldRow->SerialMaster ?? null),
                
                // วันที่ (ถ้า DB เก่าเป็น Date อยู่แล้วก็ใส่ได้เลย ถ้าเป็น String อาจต้องแปลง)
                'last_cal_date'  => $oldRow->UpdateDate ?? null, 
                
                'cal_place'      => $this->cleanText($oldRow->PlaceCALNow ?? null),
                'certificate_no' => $this->cleanText($oldRow->CerNo ?? null),
                
                // Tracability ของเดิมน่าจะเป็นชื่อไฟล์ PDF
                'trace_file'     => $this->cleanText($oldRow->Tracability ?? null),

                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}