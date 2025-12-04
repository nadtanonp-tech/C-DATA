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

            // ⛔ จุดแก้ปัญหา: ถ้าไม่มีวันที่ ให้ข้ามไปเลย
            if ($planMonth === null) {
                continue; 
            }

            // 2. เตรียมข้อมูล
            $batchData[] = [
                'plan_month'   => $planMonth,
                'code_type'    => trim($row->Type ?? ''),
                'department'   => trim($row->Department ?? ''),
                'status'       => trim($row->Status ?? ''),
                
                'plan_count'   => (int) ($row->Plan ?? 0),
                'cal_count'    => (int) ($row->Cal ?? 0),
                'remain_count' => (int) ($row->Remain ?? 0),
                
                'level_a'      => (int) ($row->A ?? 0),
                'level_b'      => (int) ($row->B ?? 0),
                'level_c'      => (int) ($row->C ?? 0),
                
                'remark'       => trim($row->Remark ?? ''),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            // Batch Insert
            if (count($batchData) >= $batchSize) {
                DB::table('monthly_plans')->insert($batchData);
                $batchData = [];
            }
        }

        // เก็บตกเศษที่เหลือ
        if (!empty($batchData)) {
            DB::table('monthly_plans')->insert($batchData);
        }
    }
}