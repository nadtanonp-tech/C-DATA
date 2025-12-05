<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportMonthlyPlansSeeder extends Seeder
{
    public function run()
    {
        $oldPages = DB::table('Page')->get(); 
        $batchData = [];
        $batchSize = 100;

        foreach ($oldPages as $row) {
            
            // 1. แปลงวันที่
            $planMonth = null;
            try {
                if (!empty($row->Month)) {
                    $planMonth = Carbon::parse($row->Month)->format('Y-m-d');
                }
            } catch (\Exception $e) {
                $planMonth = null;
            }

            if ($planMonth === null) continue; 

            // 2. 🔴 หา tool_type_id จาก code_type
            $toolTypeId = null;
            if (!empty($row->Type)) {
                $toolType = DB::table('tool_types')
                              ->where('code_type', trim($row->Type))
                              ->select('id')
                              ->first();
                
                if ($toolType) {
                    $toolTypeId = $toolType->id;
                }
            }

            // 3. เตรียมข้อมูล
            $batchData[] = [
                'plan_month'       => $planMonth,
                
                // ใส่ ID ที่หามาได้
                'tool_type_id'     => $toolTypeId,
                'code_type_legacy' => trim($row->Type ?? ''), // เก็บชื่อเดิมไว้ดู
                
                'department'       => trim($row->Department ?? ''),
                'status'           => trim($row->Status ?? ''),
                
                'plan_count'       => (int) ($row->Plan ?? 0),
                'cal_count'        => (int) ($row->Cal ?? 0),
                'remain_count'     => (int) ($row->Remain ?? 0),
                
                'level_a'          => (int) ($row->A ?? 0),
                'level_b'          => (int) ($row->B ?? 0),
                'level_c'          => (int) ($row->C ?? 0),
                
                'remark'           => trim($row->Remark ?? ''),
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            if (count($batchData) >= $batchSize) {
                DB::table('monthly_plans')->insert($batchData);
                $batchData = [];
            }
        }

        if (!empty($batchData)) {
            DB::table('monthly_plans')->insert($batchData);
        }
    }
}