<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportTraceabilitySeeder extends Seeder
{
    private function cleanText($text)
    {
        if (!$text) return null;
        return trim($text);
    }

    public function run()
    {
        // 1. ดึงข้อมูลจากตาราง MasterTraceAll เก่า
        $oldTraces = DB::table('MasterTraceAll')->get();

        $batchData = [];
        $batchSize = 100;

        foreach ($oldTraces as $oldRow) {
            
            // 2. หา ID ของ Master ตัวหลัก (ตัวลูก)
            $masterCode = $this->cleanText($oldRow->CodeNoMaster);
            if (!$masterCode) continue;

            $mainMaster = DB::table('masters')->where('master_code', $masterCode)->first();
            if (!$mainMaster) continue; // ถ้าไม่เจอตัวหลัก ก็ข้าม

            // 3. เตรียมข้อมูล Trace (มี 2 คอลัมน์คือ Trace1 และ Trace2)
            // เราจะจับยัดใส่ Array เพื่อวนลูปทีเดียว
            $traceList = [
                ['code' => $oldRow->CodeNoMasterTrace1, 'level' => 1],
                ['code' => $oldRow->CodeNoMasterTrace2, 'level' => 2],
            ];

            foreach ($traceList as $traceItem) {
                $refCode = $this->cleanText($traceItem['code']);

                // ถ้าช่องนี้ว่าง (เช่นไม่มี Trace2) ก็ข้าม
                if (!$refCode) continue;

                // 4. หา ID ของ Master ตัวแม่ (Reference)
                $refMaster = DB::table('masters')->where('master_code', $refCode)->first();

                if ($refMaster) {
                    // ถ้าเจอคู่แม่-ลูกครบ ให้บันทึก
                    $batchData[] = [
                        'master_id'     => $mainMaster->id,
                        'ref_master_id' => $refMaster->id,
                        'level'         => $traceItem['level'],
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

        // เก็บตก
        if (!empty($batchData)) {
            DB::table('traceability_chains')->insert($batchData);
        }
    }
}