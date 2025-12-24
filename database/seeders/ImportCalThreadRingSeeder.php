<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalThreadRingSeeder extends Seeder
{
    public function run()
    {
        // 🔥 ลบข้อมูลเก่าเฉพาะ Thread Ring Gauge (8-05-%) ก่อน import
        $threadRingGaugeInstrumentIds = DB::table('instruments')
            ->where('code_no', 'LIKE', '8-05-%')
            ->pluck('id')
            ->toArray();
        
        if (!empty($threadRingGaugeInstrumentIds)) {
            DB::table('calibration_logs')
                ->whereIn('instrument_id', $threadRingGaugeInstrumentIds)
                ->delete();
            
            $this->command->info('🗑️ ลบข้อมูล Thread Ring Gauge เก่าเรียบร้อยแล้ว');
        }

        // 1. ดึงข้อมูลจากตารางเก่า
        $oldLogs = DB::table('CALThrSerRing')->get();

        $batchData = [];
        $batchSize = 50; 
        $importCount = 0;
        $skipCount = 0;

        foreach ($oldLogs as $row) {
            
            // 2. หา ID เครื่องมือ
            $instrument = DB::table('instruments')
                            ->where('code_no', strtoupper(trim($row->CodeNo)))
                            ->select('id', 'tool_type_id')
                            ->first();

            if (!$instrument) {
                $this->command->warn("⚠️ ไม่พบ Instrument: {$row->CodeNo}");
                $skipCount++;
                continue;
            }

            // 🔥 ดึง dimension_specs จาก tool_type เพื่อเอา standard_value
            $toolType = DB::table('tool_types')
                        ->where('id', $instrument->tool_type_id)
                        ->select('dimension_specs')
                        ->first();
            
            $dimensionSpecs = $toolType ? json_decode($toolType->dimension_specs, true) : [];
            
            // 🔥 สร้าง readings จาก dimension_specs (วัดเกลียว)
            // Format ตรงกับที่ CalibrationThreadRingGaugeResource form ต้องการ
            $readings = [];
            
            // 🔥 ดึง measurement จาก Result field (ข้อความ)
            $measurementValue = isset($row->Result) ? trim($row->Result) : null;
            if ($measurementValue === '') $measurementValue = null;
            
            foreach ($dimensionSpecs as $spec) {
                $point = $spec['point'] ?? null;
                if (!$point) continue;
                
                // ดึง specs สำหรับ Point นี้
                if (isset($spec['specs']) && is_array($spec['specs'])) {
                    foreach ($spec['specs'] as $specItem) {
                        $label = $specItem['label'] ?? '';
                        
                        // สำหรับ วัดเกลียว - ใช้ standard_value
                        if ($label === 'วัดเกลียว') {
                            // 🔥 Format ตรงกับ form - ไม่มี Judgement/grade
                            $readings[] = [
                                'point' => $point,
                                'label' => 'วัดเกลียว',
                                'trend' => $spec['trend'] ?? null,
                                'measurement' => $measurementValue, // 🔥 ดึงจาก Result field
                            ];
                        }
                    }
                }
            }
            
            // 🔥 สร้าง calibration_data
            $calData = [
                'calibration_type' => 'ThreadRingGauge', // 🔥 สำหรับแยกประเภท
                'readings' => $readings,
            ];

            // 3. เตรียมข้อมูลบันทึก
            $batchData[] = [
                'instrument_id' => $instrument->id,
                'cal_date'      => $this->parseDate($row->CalDate),
                'next_cal_date' => $this->parseDate($row->DueDate),
                
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                
                'environment'   => json_encode([
                    'temperature' => $this->parseNumeric($row->Temp),
                    'humidity' => $this->parseNumeric($row->Humidity),
                ], JSON_UNESCAPED_UNICODE),
                
                'result_status' => trim($row->Total ?? '') ?: null,
                'cal_level'     => trim($row->Grade ?? '') ?: null,
                'remark'        => trim($row->RemarkC ?? '') ?: null,
                
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            if (count($batchData) >= $batchSize) {
                DB::table('calibration_logs')->insert($batchData);
                $importCount += count($batchData);
                $batchData = [];
            }
        }

        if (!empty($batchData)) {
            DB::table('calibration_logs')->insert($batchData);
            $importCount += count($batchData);
        }
        
        $this->command->info("✅ Import Thread Ring Gauge เสร็จสิ้น: {$importCount} records, ข้าม: {$skipCount} records");
    }

    private function parseDate($dateVal)
    {
        if (!$dateVal) return null;
        try {
            return Carbon::parse($dateVal)->format('Y-m-d');
        } catch (\Exception $e) { return null; }
    }
    
    private function parseNumeric($val)
    {
        if ($val === null || $val === '') return null;
        $cleaned = trim(str_replace([',', ' '], '', $val));
        return is_numeric($cleaned) ? $cleaned : null;
    }
}
