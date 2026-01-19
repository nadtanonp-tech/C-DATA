<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalThreadRingSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('📥 เริ่ม Import Thread Ring Gauge');
        $this->command->info('===========================================');
        
        // 🔥 ลบข้อมูลเก่าเฉพาะ Thread Ring Gauge (8-05-%) ก่อน import
        $this->command->warn('⚠️  กำลังลบข้อมูลเก่า...');
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
            
            // 🔥 ดึง measurement จาก Result field (ข้อความ)
            $measurementValue = isset($row->Result) ? trim($row->Result) : null;
            if ($measurementValue === '') $measurementValue = null;
            
            // 🔥 สร้าง readings จาก dimension_specs (เพื่อให้ได้ point, trend, standard_value ถูกต้อง)
            $readings = [];
            
            if (!empty($dimensionSpecs)) {
                // มี dimension_specs → สร้าง reading จากแต่ละ point
                foreach ($dimensionSpecs as $spec) {
                    $point = $spec['point'] ?? 'A';
                    $trend = $spec['trend'] ?? null;
                    
                    // ดึง standard_value จาก specs (วัดเกลียว)
                    $standardValue = null;
                    if (isset($spec['specs']) && is_array($spec['specs'])) {
                        foreach ($spec['specs'] as $specItem) {
                            if (($specItem['label'] ?? '') === 'วัดเกลียว') {
                                $standardValue = $specItem['standard_value'] ?? null;
                                break;
                            }
                        }
                    }
                    
                    $readings[] = [
                        'point' => $point,
                        'label' => 'วัดเกลียว',
                        'trend' => $trend,
                        'standard_value' => $standardValue,
                        'measurement' => $measurementValue, // 🔥 ใช้ค่าเดียวกับทุก point (ข้อมูลเก่ามีแค่ค่าเดียว)
                    ];
                }
            } else {
                // ไม่มี dimension_specs → สร้าง default point A
                $readings[] = [
                    'point' => 'A',
                    'label' => 'วัดเกลียว',
                    'trend' => null,
                    'standard_value' => null,
                    'measurement' => $measurementValue,
                ];
            }
            
            // 🔥 ตรวจสอบ code_no pattern เพื่อกำหนด calibration_type
            $codeNo = strtoupper(trim($row->CodeNo));
            $calibrationType = 'ThreadRingGauge'; // default
            
            if (preg_match('/^\d-05-/', $codeNo)) {
                $calibrationType = 'ThreadRingGauge';
            } elseif (preg_match('/^8-07-/', $codeNo)) {
                $calibrationType = 'SerrationRingGauge';
            } elseif (preg_match('/^\d-04-/', $codeNo)) {
                // 🔥 8-04-xxxx ใน CALThrSerRing → import เป็น ThreadRingGauge (วัดเกลียว)
                $calibrationType = 'ThreadRingGauge';
            } else {
                $this->command->warn("⚠️ ไม่รู้จัก pattern: {$codeNo} - ใช้ ThreadRingGauge");
            }
            
            // 🔥 ดึง Master Reference จาก CALMaster1
            $masterRefValue = isset($row->CALMaster1) ? trim($row->CALMaster1) : null;
            if ($masterRefValue === '') $masterRefValue = null;
            
            // 🔥 สร้าง master_references array
            $masterReferences = [];
            if ($masterRefValue) {
                $masterReferences[] = [
                    'master_id' => null,
                    'master_name' => $masterRefValue,
                ];
            }
            
            // 🔥 สร้าง calibration_data
            $calData = [
                'calibration_type' => $calibrationType,
                'readings' => $readings,
                'master_references' => $masterReferences,
            ];

            // 3. เตรียมข้อมูลบันทึก
            $batchData[] = [
                'instrument_id' => $instrument->id,
                'cal_date'      => $this->parseDate($row->CalDate),
                'next_cal_date' => $this->parseDate($row->DueDate),
                'cal_place'     => 'Internal',
                'calibration_type' => $calibrationType, // 🔥 เพิ่ม column
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
        
        $this->command->info('');
        $this->command->info('✅ นำเข้าข้อมูล Thread Ring Gauge เสร็จสิ้น!');
        $this->command->info("📊 สถิติ: นำเข้า {$importCount} รายการ | ข้าม {$skipCount} รายการ");
        $this->command->info('===========================================');
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
