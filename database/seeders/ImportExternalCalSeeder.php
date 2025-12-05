<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportExternalCalSeeder extends Seeder
{
    public function run()
    {
        // 1. ดึงข้อมูลจากตารางเก่า (ExternalCAL)
        // ตรวจสอบชื่อ connection ให้ถูกต้อง (ถ้าใช้ mysql_old ก็ใส่เพิ่มไป)
        $oldLogs = DB::table('ExternalCAL')->get();

        $batchData = [];
        $batchSize = 50; // บันทึกทีละ 50 แถว

        foreach ($oldLogs as $row) { // แก้จาก $oldData เป็น $oldLogs
            
            // 2. หา ID เครื่องมือ
            // ใช้ trim เพื่อป้องกันวรรคหน้าหลังที่อาจทำให้หาไม่เจอ
            $instrument = DB::table('instruments')
                            ->where('code_no', trim($row->CodeNo))
                            ->select('id')
                            ->first();

            // ถ้าไม่เจอเครื่องมือ ให้ข้ามแถวนี้ไป
            if (!$instrument) continue;

            // 3. ปั้น JSON สำหรับผลสอบเทียบภายนอก
            $calData = [
                'Type' => 'External Calibration',
                'CertificateNo' => $row->CerNo,
                'TraceReference' => $row->TracePlace,
                'Readings' => [
                    // กรองค่าที่เป็น Null ออกเพื่อให้ JSON สะอาด (Optional)
                    array_filter(['Size' => $row->Size1, 'ErrorMax' => $row->ErrorMax1, 'Serial' => $row->SerialNo1]),
                    array_filter(['Size' => $row->Size2, 'ErrorMax' => $row->ErrorMax2]),
                    array_filter(['Size' => $row->Size3, 'ErrorMax' => $row->ErrorMax3]),
                    array_filter(['Size' => $row->Size4, 'ErrorMax' => $row->ErrorMax4]),
                    array_filter(['Size' => $row->Size5, 'ErrorMax' => $row->ErrorMax5]),
                ],
                'ErrorMaxNow' => $row->ErrorMaxNow,
                'Indices' => [
                    'IndexA' => $row->IndexA,
                    'IndexB' => $row->IndexB,
                    'IndexC' => $row->IndexC,
                ]
            ];

            // 4. เตรียมข้อมูลลง Array (ยังไม่ Insert ทันที)
            $batchData[] = [
                'instrument_id' => $instrument->id,
                
                'cal_date'      => $this->parseDate($row->CalDate),
                'next_cal_date' => $this->parseDate($row->DueDate),
                
                'cal_by'        => 'Vendor', 
                'cal_place'     => 'External', 
                
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                'result_status' => $row->Result, 
                'remark'        => $row->Remark,
                'legacy_source_table' => 'ExternalCAL',
                
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            // 5. ถ้าครบ 50 แถว ให้บันทึกทีเดียว
            if (count($batchData) >= $batchSize) {
                DB::table('calibration_logs')->insert($batchData);
                $batchData = []; // ล้างถังรอรอบใหม่
            }
        }

        // 6. เก็บตกเศษที่เหลือ (ที่ยังไม่ครบ 50)
        if (!empty($batchData)) {
            DB::table('calibration_logs')->insert($batchData);
        }
    }

    // ฟังก์ชันแปลงวันที่
    private function parseDate($dateVal)
    {
        if (!$dateVal) return null;
        try {
            return Carbon::parse($dateVal)->format('Y-m-d');
        } catch (\Exception $e) { return null; }
    }
}