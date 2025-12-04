<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportBorrowsSeeder extends Seeder
{
    // ฟังก์ชันทำความสะอาดข้อความ
    private function cleanText($text)
    {
        if ($text === null) return null;
        $text = trim($text);
        if ($text === '') return null;
        return mb_substr($text, 0, 255);
    }

    // ฟังก์ชันแปลงวันที่
    private function parseDate($dateVal)
    {
        if (!$dateVal) return null;
        try {
            if ($dateVal == '0000-00-00') return null;
            return Carbon::parse($dateVal)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function run()
    {
        // 1. ดึงข้อมูลจากตาราง Borrow เดิม
        $oldBorrows = DB::table('Borrow')->get(); 

        $batchData = [];
        $batchSize = 100;

        foreach ($oldBorrows as $oldRow) {
            
            // 2. ข้ามถ้าไม่มี CodeNo (เพราะไม่รู้ว่าเป็นของชิ้นไหน)
            if (empty($oldRow->CodeNo)) continue;

            // 3. เชื่อมโยงกับตาราง Instruments ใหม่ (หา ID)
            $instrument = DB::table('instruments')
                            ->where('code_no', trim($oldRow->CodeNo))
                            ->select('id')
                            ->first();

            // ถ้าไม่เจอเครื่องมือในระบบใหม่ ให้ข้ามรายการยืมนี้ไป
            if (!$instrument) continue;

            // 4. เตรียมข้อมูลพนักงาน (อนุญาตให้เป็น Null ได้)
            $empId = $this->cleanText($oldRow->IDEmp);
            // *** ตรงนี้เราลบโค้ดที่สั่ง continue ออกแล้ว เพื่อให้รับค่า Null ได้ ***

            // 5. จัดการวันที่และสถานะ
            $borrowDate   = $this->parseDate($oldRow->DateBorrow);
            $dueDate      = $this->parseDate($oldRow->DueDate);
            
            // เช็คชื่อฟิลด์วันที่คืน (เผื่อพิมพ์ผิดใน DB เก่า)
            $returnedDate = $this->parseDate($oldRow->DateSent ?? $oldRow->DetaSent ?? null);

            // Logic คำนวณสถานะ:
            // ถ้ามีวันที่คืนแล้ว = Returned (คืนแล้ว)
            // ถ้ายังไม่มีวันที่คืน = Borrowed (กำลังยืม)
            $status = ($returnedDate) ? 'Returned' : 'Borrowed';

            // 6. เตรียมข้อมูลลง Array
            $batchData[] = [
                'instrument_id' => $instrument->id,
                
                'emp_id'        => $empId, // เก็บค่า Null ได้ถ้าไม่มีข้อมูล
                'emp_name'      => $this->cleanText($oldRow->Name),    
                'emp_dept'      => $this->cleanText($oldRow->Section), 
                
                'borrow_date'   => $borrowDate,
                'due_date'      => $dueDate,
                'returned_date' => $returnedDate,
                
                'status'        => $status,
                'doc_file'      => null, 
                'remark'        => null,
                
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            // Batch Insert (บันทึกทีละ 100 แถว)
            if (count($batchData) >= $batchSize) {
                DB::table('borrows')->insert($batchData);
                $batchData = [];
            }
        }

        // เก็บตกเศษที่เหลือ
        if (!empty($batchData)) {
            DB::table('borrows')->insert($batchData);
        }
    }
}