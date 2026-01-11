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
                'cal_place'     => 'External', 
                
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                'result_status' => $row->Result, 
                'remark'        => $row->Remark,
                
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
    // ฟังก์ชันสำหรับแกะวันที่ไทยจาก Remark
    private function parseThaiDateFromRemark($remark)
    {
        if (empty($remark)) return null;

        // 1. ใช้ Regex ค้นหาแพทเทิร์น "Next Cal : 31 ต.ค. 2569"
        // คำอธิบาย Regex:
        // Next Cal\s*:\s* -> หาคำว่า Next Cal : (ยอมให้มีช่องว่างได้)
        // (\d{1,2})        -> เก็บวันที่ (1-2 หลัก) ไว้ในตัวแปรที่ 1
        // \s+              -> ช่องว่าง
        // ([^\s]+)         -> เก็บชื่อเดือน (ต.ค.) ไว้ในตัวแปรที่ 2
        // \s+              -> ช่องว่าง
        // (\d{4})          -> เก็บปี (2569) ไว้ในตัวแปรที่ 3
        if (preg_match('/Next Cal\s*:\s*(\d{1,2})\s+([^\s]+)\s+(\d{4})/u', $remark, $matches)) {
            
            $day = $matches[1];
            $thaiMonth = $matches[2];
            $thaiYear = $matches[3];

            // 2. แปลงเดือนไทยเป็นตัวเลข
            $months = [
                'ม.ค.' => '01', 'ก.พ.' => '02', 'มี.ค.' => '03', 'เม.ย.' => '04', 'พ.ค.' => '05', 'มิ.ย.' => '06',
                'ก.ค.' => '07', 'ส.ค.' => '08', 'ก.ย.' => '09', 'ต.ค.' => '10', 'พ.ย.' => '11', 'ธ.ค.' => '12',
                // เผื่อกรณีเขียนเต็ม (ถ้ามี)
                'มกราคม' => '01', 'กุมภาพันธ์' => '02', // ... ใส่เพิ่มได้
            ];

            $month = $months[$thaiMonth] ?? null;
            
            // 3. แปลงปี พ.ศ. เป็น ค.ศ. (ลบ 543)
            $year = (int)$thaiYear - 543;

            if ($month && checkdate($month, $day, $year)) {
                // ส่งค่ากลับเป็น Format มาตรฐาน Y-m-d (เช่น 2026-10-31)
                return "{$year}-{$month}-{$day}"; 
            }
        }

        return null; // ถ้าหาไม่เจอ หรือรูปแบบผิด
    }
}