<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalPlugSeeder extends Seeder
{
    public function run()
    {
        // 1. ดึงข้อมูลจากตารางเก่า
        $oldLogs = DB::table('CALPlug')->get();

        $batchData = [];
        $batchSize = 50; 

        foreach ($oldLogs as $row) {
            
            // 2. หา ID เครื่องมือ
            $instrument = DB::table('instruments')
                            ->where('code_no', trim($row->CodeNo))
                            ->select('id')
                            ->first();

            if (!$instrument) continue; 

            // 3. ปั้น JSON (จัดกลุ่ม GO และ NOGO)
            // สังเกตการใช้ $row->{'ชื่อฟิลด์'} สำหรับชื่อที่มีเครื่องหมายลบ
            $calData = [
                'Type' => 'Plug Gauge',
                'GO' => [
                    'Readings' => [
                        '1-1' => $row->{'GO1-1'},
                        '1-2' => $row->{'GO1-2'},
                        '1-3' => $row->{'GO1-3'},
                        '2-1' => $row->{'GO2-1'},
                        '2-2' => $row->{'GO2-2'},
                        '2-3' => $row->{'GO2-3'},
                    ],
                    'Avg'   => $row->AvgGO,
                    'Judge' => $row->JudgeGO,
                    'Grade' => $row->GradeGO,
                ],
                'NOGO' => [
                    'Readings' => [
                        '1-1' => $row->{'NOGO1-1'},
                        '1-2' => $row->{'NOGO1-2'},
                        '2-1' => $row->{'NOGO2-1'},
                        '2-2' => $row->{'NOGO2-2'},
                    ],
                    'Avg'   => $row->AvgNOGO,
                    'Judge' => $row->JudgeNOGO,
                    'Grade' => $row->GradeNOGO,
                ],
                'MastersUsed' => array_filter([
                    $row->CALMaster1,
                    $row->CALMaster2
                ])
            ];

            // 4. เตรียมข้อมูลบันทึก
            $batchData[] = [
                'instrument_id' => $instrument->id,
                'cal_date'      => $this->parseDate($row->CalDate),
                'next_cal_date' => $this->parseDate($row->DueDate),
                'cal_by'        => trim($row->Section),
                'cal_place'     => 'Internal',
                
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                
                'environment'   => "Temp: {$row->Temp} / Humid: {$row->Humidity}",
                'result_status' => trim($row->Total), // ผลรวม (Pass/Fail)
                'remark'        => trim($row->RemarkC),
                'grade_result'  => trim($row->Grade), // เก็บเกรดรวมไว้ด้วยถ้ามีคอลัมน์รองรับ (หรือเก็บใน JSON ก็ได้)
                'legacy_source_table' => 'CALPlug',
                
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            if (count($batchData) >= $batchSize) {
                DB::table('calibration_logs')->insert($batchData);
                $batchData = [];
            }
        }

        if (!empty($batchData)) {
            DB::table('calibration_logs')->insert($batchData);
        }
    }

    private function parseDate($dateVal)
    {
        if (!$dateVal) return null;
        try {
            return Carbon::parse($dateVal)->format('Y-m-d');
        } catch (\Exception $e) { return null; }
    }
}