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
        
        $this->command->info("Found " . $oldLogs->count() . " rows in ExternalCAL table.");

        $batchData = [];
        $batchSize = 50; // บันทึกทีละ 50 แถว

        foreach ($oldLogs as $row) { // แก้จาก $oldData เป็น $oldLogs
            
            // 2. หา ID เครื่องมือ
            // ใช้ trim เพื่อป้องกันวรรคหน้าหลังที่อาจทำให้หาไม่เจอ
            $codeNo = trim($row->CodeNo);
            $instrument = DB::table('instruments')
                            ->where('code_no', $codeNo)
                            ->select('id')
                            ->first();

            // ถ้าไม่เจอเครื่องมือ ให้ข้ามแถวนี้ไป
            if (!$instrument) {
                $this->command->warn("Skipped: Instrument not found for CodeNo: {$codeNo}");
                continue;
            }

            // 3. ปั้น JSON สำหรับผลสอบเทียบภายนอก
            $calData = [
                'calibration_type' => 'ExternalCal', // 🔥 ระบุ Type ใน JSON
                'Type' => 'External Calibration',
                'cer_no' => $row->CerNo,
                'place_cal' => $row->PlaceCAL ?? null,
                'trace_place' => $row->TracePlace ?? null,
                // 'price' => moved to column
                
                // ข้อมูล FreqCal และ LastCalDate/LastErrorMax
                'freq_cal' => $row->FeqCal1 ?? null,
                'last_cal_date' => $row->LastCalDate ?? null,
                'last_error_max' => $row->LastErrorMax ?? null,
                
                'error_max_now' => $row->ErrorMaxNow,
                'drift_rate' => $row->ErrorMax ?? null,
                'index_combined' => $row->Index ?? null,
                'new_index' => $row->NewIndex ?? null,
                'amount_day' => $row->AmountDay ?? null,
                'ranges' => array_filter([
                    ['range_name' => 'Range 1', 'error_max' => $row->ErrorMax1, 'index' => $row->Index1 ?? null],
                    ['range_name' => 'Range 2', 'error_max' => $row->ErrorMax2, 'index' => $row->Index2 ?? null],
                    ['range_name' => 'Range 3', 'error_max' => $row->ErrorMax3, 'index' => $row->Index3 ?? null],
                    ['range_name' => 'Range 4', 'error_max' => $row->ErrorMax4, 'index' => $row->Index4 ?? null],
                    ['range_name' => 'Range 5', 'error_max' => $row->ErrorMax5, 'index' => $row->Index5 ?? null],
                ], fn($r) => !empty($r['error_max'])),
            ];

            // 4. เตรียมข้อมูลลง Array (ยังไม่ Insert ทันที)
            $batchData[] = [
                'instrument_id' => $instrument->id,
                'calibration_type' => 'ExternalCal', // 🔥 ระบุ Type ใน Column
                'cal_date'      => $this->parseDate($row->CalDate),
                'next_cal_date' => $this->parseDate($row->DueDate),
                'cal_place'     => 'External', 
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                'result_status' => $row->Result, 
                'remark'        => $row->Remark,
                'cal_level'     => '-',
                'price'         => $row->Price_1 ?? null,
                
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