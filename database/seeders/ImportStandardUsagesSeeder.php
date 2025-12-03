<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportStandardUsagesSeeder extends Seeder
{
    private function cleanText($text)
    {
        if ($text === null) return null;
        if (is_numeric($text)) $text = (string) $text;
        $text = trim($text);
        return mb_substr($text, 0, 255);
    }

    public function run()
    {
        // 1. ดึงข้อมูล Type เก่า (ที่มี CodeNoMaster1-7)
        $oldTypes = DB::table('Type')->get(); // หรือ DB::connection('mysql_old')->table('Type')->get()

        // เตรียมตัวแปร Batch
        $batchData = [];
        $batchSize = 100;

        foreach ($oldTypes as $oldRow) {
            
            // 2. หา ID ของ Tool Type ในระบบใหม่
            if (empty($oldRow->CodeType)) continue;

            $toolType = DB::table('tool_types')
                          ->where('code_type', trim($oldRow->CodeType))
                          ->select('id')
                          ->first();

            if (!$toolType) continue; // ถ้าไม่เจอ Type นี้ในระบบใหม่ ให้ข้าม

            // 3. วนลูป Master 1 ถึง 7
            for ($i = 1; $i <= 7; $i++) {
                
                // ดึงรหัส Master และ Point จากคอลัมน์เดิม
                $masterCodeRaw = $oldRow->{'CodeNoMaster' . $i} ?? null;
                $pointRaw      = $oldRow->{'Point' . $i} ?? null;

                // ถ้าไม่มีรหัส Master ในช่องนี้ ก็ข้ามไป
                if (!$masterCodeRaw) continue;

                $cleanMasterCode = trim($masterCodeRaw);

                // 4. วิ่งไปหา ID ของ Master ในระบบใหม่
                $master = DB::table('masters')
                            ->where('master_code', $cleanMasterCode)
                            ->select('id')
                            ->first();

                // *** สำคัญ: ถ้าเจอ Master ใน DB ใหม่ ค่อยบันทึก ***
                // (ถ้าข้อมูลเก่ามี Master Code ที่เราไม่ได้ Import เข้ามา ก็จะข้ามไป ไม่ Error)
                if ($master) {
                    $batchData[] = [
                        'tool_type_id' => $toolType->id,
                        'master_id'    => $master->id,
                        'check_point'  => $this->cleanText($pointRaw),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                }
            }

            // บันทึกทีละก้อน (Batch Insert) เพื่อความเร็ว
            if (count($batchData) >= $batchSize) {
                DB::table('standard_usages')->insert($batchData);
                $batchData = [];
            }
        }

        // เก็บตกเศษที่เหลือ
        if (!empty($batchData)) {
            DB::table('standard_usages')->insert($batchData);
        }
    }
}