<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalSerPlSeeder extends Seeder
{
    public function run()
    {
        // 1. ดึงข้อมูลจากตารางเก่า (CALSerPlThrPlSerPlFor)
        $oldLogs = DB::table('CALSerPlThrPlSerPlFor')->get();

        $batchData = [];
        $batchSize = 50; // ลดจำนวนลงหน่อยเพราะ JSON กินที่

        foreach ($oldLogs as $row) {

            // 2. หา ID เครื่องมือ
            $instrument = DB::table('instruments')
                            ->where('code_no', trim($row->CodeNo))
                            ->select('id')
                            ->first();

            if (!$instrument) continue; // ถ้าไม่เจอเครื่องมือ ข้าม

            // 3. ปั้นก้อน JSON (Mapping Field เก่า -> โครงสร้างใหม่)
            $calData = [
                'calibration_type' => 'SerrationPlugGauge', // 🔥 เพิ่ม Type ลง JSON
                'Major' => [
                    '1-1' => $row->{'Major1-1'},
                    '1-2' => $row->{'Major1-2'},
                    '2-1' => $row->{'Major2-1'},
                    '2-2' => $row->{'Major2-2'},
                    'Avg' => $row->AvgMajor,
                    'Judge' => $row->JudgeMajor,
                    'Grade' => $row->GradeMajor,
                ],
                'Pitch' => [
                    '1-1' => $row->{'Pitch1-1'},
                    '1-2' => $row->{'Pitch1-2'},
                    '2-1' => $row->{'Pitch2-1'},
                    '2-2' => $row->{'Pitch2-2'},
                    'Avg' => $row->AvgPitch,
                    'Judge' => $row->JudgePitch,
                    'Grade' => $row->GradePitch,
                ],
                'MasterUsed' => [
                    '1' => $row->CALMaster1,
                    '2' => $row->CALMaster2,
                ]
            ];

            // 4. จัดการวันที่
            $calDate = $this->parseDate($row->CalDate);
            $dueDate = $this->parseDate($row->DueDate);

            // 5. เตรียมข้อมูลบันทึก
            $batchData[] = [
                'instrument_id' => $instrument->id,
                'calibration_type' => 'SerrationPlugGauge', // 🔥 เพิ่ม Type ลง Column
                'cal_date'      => $calDate,
                'next_cal_date' => $dueDate,
                'cal_place'     => 'Internal', // เดาว่าเป็น Internal เพราะมีข้อมูลละเอียด

                // แปลง Array เป็น JSON
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),

                'environment'   => json_encode(['humidity' => $row->Humidity, 'temperature' => $row->Temp], JSON_UNESCAPED_UNICODE),
                'result_status' => trim($row->Total), // ผลรวม (Pass/Fail)
                'remark'        => trim($row->RemarkC),

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