<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalPlugSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('📥 เริ่ม Import Plug Gauge (CALPlug)');
        $this->command->info('===========================================');
        
        // 🔥 ลบข้อมูลเก่าเฉพาะ Plug Gauge (8-03-%) ก่อน import
        $this->command->warn('⚠️  กำลังลบข้อมูลเก่า Plug Gauge...');
        $plugGaugeInstrumentIds = DB::table('instruments')
            ->where('code_no', 'LIKE', '8-03-%')
            ->pluck('id')
            ->toArray();
        
        if (!empty($plugGaugeInstrumentIds)) {
            DB::table('calibration_logs')
                ->whereIn('instrument_id', $plugGaugeInstrumentIds)
                ->delete();
            
            $this->command->info('🗑️ ลบข้อมูล Plug Gauge เก่าเรียบร้อยแล้ว');
        }

        // 1. ดึงข้อมูลจากตารางเก่า
        $oldLogs = DB::table('CALPlug')->get();
        $totalRecords = $oldLogs->count();
        $this->command->info("📊 พบข้อมูล {$totalRecords} รายการใน CALPlug");

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
                $this->command->warn("   ⚠️ ข้าม: ไม่พบ Instrument CodeNo: {$row->CodeNo}");
                $skipCount++;
                continue;
            }

            // 🔥 ดึง dimension_specs จาก tool_type เพื่อเอา min/max spec
            $toolType = DB::table('tool_types')
                        ->where('id', $instrument->tool_type_id)
                        ->select('dimension_specs')
                        ->first();
            
            $dimensionSpecs = $toolType ? json_decode($toolType->dimension_specs, true) : [];
            
            // 🔥 หา spec สำหรับ Point A (GO) และ Point B (NOGO)
            $goSpec = null;
            $nogoSpec = null;
            
            foreach ($dimensionSpecs as $spec) {
                $point = strtoupper($spec['point'] ?? '');
                if ($point === 'A' || str_contains(strtoupper($point), 'GO')) {
                    $goSpec = $spec;
                } elseif ($point === 'B' || str_contains(strtoupper($point), 'NOGO')) {
                    $nogoSpec = $spec;
                }
            }

            // 3. 🔥 ปั้น JSON ใน format ใหม่ที่มี readings และ measurements
            $readings = [];
            
            // 🔥 Point A(GO) - 3 ค่าวัด
            $goMeasurements = [
                ['value' => $this->parseNumeric($row->{'GO1-1'})],
                ['value' => $this->parseNumeric($row->{'GO1-2'})],
                ['value' => $this->parseNumeric($row->{'GO1-3'})],
            ];
            
            // ดึง min/max spec จาก dimension_specs
            $goMinSpec = null;
            $goMaxSpec = null;
            $goTrend = 'Smaller';
            
            if ($goSpec && isset($goSpec['specs'][0])) {
                $mainSpec = $goSpec['specs'][0];
                $goMinSpec = $mainSpec['min'] ?? null;
                $goMaxSpec = $mainSpec['max'] ?? null;
                $goTrend = $goSpec['trend'] ?? 'Smaller';
            }
            
            $readings[] = [
                'point' => 'A(GO)',
                'trend' => $goTrend,
                'std_label' => 'STD',
                'min_spec' => $goMinSpec !== null ? rtrim(rtrim(number_format((float)$goMinSpec, 8, '.', ''), '0'), '.') : null,
                'max_spec' => $goMaxSpec !== null ? rtrim(rtrim(number_format((float)$goMaxSpec, 8, '.', ''), '0'), '.') : null,
                'measurements' => $goMeasurements,
                'reading' => $this->parseNumeric($row->AvgGO),
                'error' => null,
                'Judgement' => trim($row->JudgeGO) ?: null,
                'grade' => trim($row->GradeGO) ?: null,
            ];
            
            // 🔥 Point B(NOGO) - 2 ค่าวัด
            $nogoMeasurements = [
                ['value' => $this->parseNumeric($row->{'NOGO1-1'})],
                ['value' => $this->parseNumeric($row->{'NOGO1-2'})],
            ];
            
            // ดึง min/max spec จาก dimension_specs
            $nogoMinSpec = null;
            $nogoMaxSpec = null;
            $nogoTrend = 'Bigger';
            
            if ($nogoSpec && isset($nogoSpec['specs'][0])) {
                $mainSpec = $nogoSpec['specs'][0];
                $nogoMinSpec = $mainSpec['min'] ?? null;
                $nogoMaxSpec = $mainSpec['max'] ?? null;
                $nogoTrend = $nogoSpec['trend'] ?? 'Bigger';
            }
            
            $readings[] = [
                'point' => 'B(NOGO)',
                'trend' => $nogoTrend,
                'std_label' => 'STD',
                'min_spec' => $nogoMinSpec !== null ? rtrim(rtrim(number_format((float)$nogoMinSpec, 8, '.', ''), '0'), '.') : null,
                'max_spec' => $nogoMaxSpec !== null ? rtrim(rtrim(number_format((float)$nogoMaxSpec, 8, '.', ''), '0'), '.') : null,
                'measurements' => $nogoMeasurements,
                'reading' => $this->parseNumeric($row->AvgNOGO),
                'error' => null,
                'Judgement' => trim($row->JudgeNOGO) ?: null,
                'grade' => trim($row->GradeNOGO) ?: null,
            ];
            
            // 🔥 สร้าง calibration_data ใน format ใหม่
            $calData = [
                'readings' => $readings,
            ];

            // 4. เตรียมข้อมูลบันทึก
            $batchData[] = [
                'instrument_id' => $instrument->id,
                'cal_date'      => $this->parseDate($row->CalDate),
                'next_cal_date' => $this->parseDate($row->DueDate),
                'cal_place'     => 'Internal',
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                
                'environment'   => json_encode([
                    'temperature' => $this->parseNumeric($row->Temp),
                    'humidity' => $this->parseNumeric($row->Humidity),
                ], JSON_UNESCAPED_UNICODE),
                
                'result_status' => trim($row->Total) ?: null,
                'cal_level'     => trim($row->Grade) ?: null,
                'remark'        => trim($row->RemarkC) ?: null,
                
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
        $this->command->info('✅ นำเข้าข้อมูล Plug Gauge เสร็จสิ้น!');
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