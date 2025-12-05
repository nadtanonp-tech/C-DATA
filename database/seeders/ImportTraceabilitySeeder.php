<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportTraceabilitySeeder extends Seeder
{
    private function cleanText($text)
    {
        if ($text === null) return null;
        $text = trim($text);
        if ($text === '' || $text === '-') return null; // ดักจับค่าว่าง และขีดแดช
        return mb_substr($text, 0, 255);
    }

    public function run()
    {
        // 1. ดึงข้อมูลจากตาราง MasterTraceAll
        $oldTraces = DB::table('MasterTraceAll')->get();

        $batchData = [];
        $batchSize = 100;

        foreach ($oldTraces as $oldRow) {
            
            // 2. หา ID ของ Master ตัวหลัก (ตัวถูกวัด)
            $masterCode = $this->cleanText($oldRow->CodeNoMaster);
            if (!$masterCode) continue;

            $mainMaster = DB::table('masters')->where('master_code', $masterCode)->first();
            
            // ถ้าไม่เจอตัวหลักในระบบใหม่ ก็ข้ามไป (เพราะไม่รู้จะผูกกับใคร)
            if (!$mainMaster) continue; 

            // 3. เตรียมข้อมูล Trace (จับ Trace1 และ Trace2 มาเช็คทีละตัว)
            $traceList = [
                $oldRow->CodeNoMasterTrace1,
                $oldRow->CodeNoMasterTrace2
            ];

            foreach ($traceList as $rawTraceCode) {
                // ทำความสะอาดรหัส (ถ้าเป็นค่าว่าง หรือ Null ฟังก์ชัน cleanText จะส่งกลับเป็น null)
                $refCode = $this->cleanText($rawTraceCode);

                // *** จุดสำคัญ: ถ้าเป็นค่าว่าง ให้ข้าม Loop นี้ไปเลย (ไม่บันทึก) ***
                if (!$refCode) continue;

                // 4. หา ID ของ Master ตัวแม่ (Reference)
                $refMaster = DB::table('masters')->where('master_code', $refCode)->first();

                if ($refMaster) {
                    // ถ้าเจอคู่แม่-ลูกครบ ให้บันทึก
                    $batchData[] = [
                        'master_id'     => $mainMaster->id,
                        'ref_master_id' => $refMaster->id,
                        'level'         => 1, // Default level
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }
            }

            // Batch Insert
            if (count($batchData) >= $batchSize) {
                DB::table('traceability_chains')->insert($batchData);
                $batchData = [];
            }
        }

        // เก็บตกเศษที่เหลือ
        if (!empty($batchData)) {
            DB::table('traceability_chains')->insert($batchData);
        }
    }
}