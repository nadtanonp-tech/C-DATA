<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportStatusHistorySeeder extends Seeder
{
    // ฟังก์ชันแปลงวันที่
    private function parseDate($dateVal)
    {
        if (!$dateVal) return null;
        try {
            if ($dateVal == '0000-00-00' || $dateVal == '1899-12-30') return null;
            return Carbon::parse($dateVal)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function run()
    {
        $this->command->info('🚀 เริ่มการ Import Status History จาก DataRecord...');
        
        // ล้างข้อมูลเดิมก่อน
        DB::table('instrument_status_histories')->truncate();
        
        // 1. ดึงข้อมูลจากตาราง DataRecord
        $oldDataRecords = DB::table('DataRecord')
            ->whereNotNull('Status')
            ->where('Status', '!=', '')
            ->get();
        
        $this->command->info("📊 พบข้อมูล " . count($oldDataRecords) . " รายการ");

        $batchData = [];
        $batchSize = 100;
        $imported = 0;
        $skipped = 0;

        foreach ($oldDataRecords as $oldRow) {
            
            // ข้ามถ้าไม่มี CodeNo
            if (empty($oldRow->CodeNo)) {
                $skipped++;
                continue;
            }

            // หา instrument จาก code_no
            $instrument = DB::table('instruments')
                ->where('code_no', trim($oldRow->CodeNo))
                ->select('id')
                ->first();

            // ถ้าไม่เจอเครื่องมือในระบบใหม่ ให้ข้าม
            if (!$instrument) {
                $skipped++;
                continue;
            }

            // ดึง Remark และ Status จาก DataRecord
            $remark = $oldRow->Remark ?? '';
            $oldStatus = $oldRow->Status ?? 'ใช้งาน';
            $expireDate = $oldRow->ExpireDate ?? null;
            
            // === ข้ามถ้าสถานะเป็น ใช้งาน และไม่มี ExpireDate (ไม่เคยเปลี่ยนสถานะ) ===
            if (($oldStatus === 'ใช้งาน' || $oldStatus === 'Active') && empty($expireDate)) {
                $skipped++;
                continue;
            }
            
            // === Logic สำหรับ new_status ===
            $newStatus = null;
            
            // เช็คว่า Remark มีคำว่า Spar, Spare, spare หรือไม่
            if (preg_match('/spar|spare/i', $remark)) {
                $newStatus = 'Spare';
            }
            // เช็คว่า Remark มีคำว่า สูญหาย หรือไม่
            elseif (strpos($remark, 'สูญหาย') !== false) {
                $newStatus = 'สูญหาย';
            }
            // ถ้าเป็นสถานะยกเลิก และไม่มีข้อความตาม Remark ข้างบน
            elseif ($oldStatus === 'ยกเลิก' || $oldStatus === 'Inactive' || $oldStatus === 'Cancel') {
                $newStatus = 'ยกเลิก';
                $oldStatus = 'ใช้งาน'; // old_status เป็น ใช้งาน
            }
            // ถ้า status เดิมเป็น Active หรือ ใช้งาน
            elseif ($oldStatus === 'Active' || $oldStatus === 'ใช้งาน') {
                $newStatus = 'ใช้งาน';
            }
            // ถ้า status เดิมเป็น Spare
            elseif ($oldStatus === 'Spare' || strpos($oldStatus, 'Spare') !== false) {
                $newStatus = 'Spare';
            }
            // status อื่นๆ
            else {
                $newStatus = $oldStatus;
            }

            // === Logic สำหรับ changed_at ===
            // ถ้า ยกเลิก หรือ สูญหาย ให้ดึงจาก ExpireDate
            // ถ้าเป็นสถานะอื่นๆ ให้ดึงจาก RecieveDate
            if (in_array($newStatus, ['ยกเลิก', 'สูญหาย'])) {
                $changedAt = $this->parseDate($oldRow->ExpireDate ?? null);
            } else {
                $changedAt = $this->parseDate($oldRow->RecieveDate ?? $oldRow->ReceiveDate ?? null);
            }
            
            // ถ้าไม่มีวันที่ ใช้วันปัจจุบัน
            if (!$changedAt) {
                $changedAt = now()->format('Y-m-d H:i:s');
            }

            // เตรียมข้อมูลลง Array
            $batchData[] = [
                'instrument_id' => $instrument->id,
                'old_status'    => $oldStatus,
                'new_status'    => $newStatus,
                'reason'        => $remark ?: null,
                'changed_at'    => $changedAt,
                'changed_by'    => null, // ไม่มีข้อมูลผู้เปลี่ยน
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $imported++;

            // Batch Insert (บันทึกทีละ 100 แถว)
            if (count($batchData) >= $batchSize) {
                DB::table('instrument_status_histories')->insert($batchData);
                $this->command->info("  ✅ Inserted batch: {$imported} records...");
                $batchData = [];
            }
        }

        // เก็บตกเศษที่เหลือ
        if (!empty($batchData)) {
            DB::table('instrument_status_histories')->insert($batchData);
        }

        $this->command->info("🎉 Import เสร็จสิ้น! Imported: {$imported}, Skipped: {$skipped}");
    }
}
