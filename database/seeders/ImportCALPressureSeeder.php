<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCALPressureSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('📥 เริ่ม Import Pressure Gauge (CALPressure)');
        $this->command->info('===========================================');
        
        // ดึงข้อมูลจาก CALPressure
        $oldLogs = DB::table('CALPressure')->get();
        $totalRecords = $oldLogs->count();
        $this->command->info("📊 พบข้อมูล {$totalRecords} รายการใน CALPressure");

        $batchData = [];
        $batchSize = 50;
        $importCount = 0;
        $skipCount = 0;

        foreach ($oldLogs as $row) {
            // หา ID เครื่องมือ
            $instrument = DB::table('instruments')
                            ->where('code_no', strtoupper(trim($row->CodeNo)))
                            ->select('id', 'tool_type_id')
                            ->first();

            if (!$instrument) {
                $this->command->warn("⚠️ ไม่พบ Instrument: {$row->CodeNo}");
                $skipCount++;
                continue;
            }

            // ดึง dimension_specs จาก tool_type
            $toolType = DB::table('tool_types')
                        ->where('id', $instrument->tool_type_id)
                        ->select('dimension_specs')
                        ->first();
            
            $dimensionSpecs = $toolType ? json_decode($toolType->dimension_specs, true) : [];
            
            // 🔥 สร้าง readings_pressure (6 points)
            $readingsPressure = $this->buildReadingsPressure($row, $dimensionSpecs);
            
            // ข้ามถ้าไม่มี readings เลย
            if (empty($readingsPressure)) {
                $this->command->warn("   ⚠️ ข้าม: ไม่มีข้อมูล readings สำหรับ {$row->CodeNo}");
                $skipCount++;
                continue;
            }
            
            // 🔥 สร้าง calibration_data
            $calData = [
                'calibration_type' => 'PressureGauge',
                'readings_pressure' => $readingsPressure,
            ];

            $batchData[] = [
                'instrument_id' => $instrument->id,
                'cal_date'      => $this->parseDate($row->CalDate ?? null),
                'next_cal_date' => $this->parseDate($row->DueDate ?? null),
                'cal_place'     => 'Internal',
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                
                'environment'   => json_encode([
                    'temperature' => $this->parseNumeric($row->Temp ?? null),
                    'humidity' => $this->parseNumeric($row->Humidity ?? null),
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
        $this->command->info('✅ นำเข้าข้อมูล Pressure Gauge เสร็จสิ้น!');
        $this->command->info("📊 สถิติ: นำเข้า {$importCount} รายการ | ข้าม {$skipCount} รายการ");
        $this->command->info('===========================================');
    }

    /**
     * 🔥 สร้าง readings_pressure สำหรับ Pressure Gauge - 6 points
     */
    private function buildReadingsPressure($row, $dimensionSpecs): array
    {
        $readings = [];
        
        for ($i = 1; $i <= 6; $i++) {
            // ดึงค่า S จาก dimension_specs (ถ้ามี)
            $sValue = null;
            if (is_array($dimensionSpecs) && isset($dimensionSpecs[$i - 1])) {
                $spec = $dimensionSpecs[$i - 1];
                if (isset($spec['specs']) && is_array($spec['specs'])) {
                    foreach ($spec['specs'] as $s) {
                        $sValue = $s['s_std'] ?? $s['s_value'] ?? null;
                        if ($sValue !== null) break;
                    }
                }
            }
            
            // ดึงข้อมูลจาก source table
            $dpiCol = "DPI{$i}";        // ค่าจาก Master
            $errCol = "Err{$i}";         // ERROR
            $rangeCol = "Range{$i}";     // % ERROR (คิดจาก Range)
            $judgeCol = "Judge{$i}";     // Judgement
            $gradeCol = "Grade{$i}";     // Level/Grade
            
            $masterValue = isset($row->$dpiCol) ? $this->parseNumeric($row->$dpiCol) : null;
            $error = isset($row->$errCol) ? $this->parseNumeric($row->$errCol) : null;
            $percentError = isset($row->$rangeCol) ? $this->parseNumeric($row->$rangeCol) : null;
            $judgement = isset($row->$judgeCol) ? trim($row->$judgeCol) : null;
            $level = isset($row->$gradeCol) ? trim($row->$gradeCol) : null;
            
            // ข้ามถ้าไม่มีข้อมูลเลย
            if ($masterValue === null && $error === null) {
                continue;
            }
            
            $readings[] = [
                's_value' => $sValue,
                'master_value' => $masterValue,
                'error' => $error,
                'percent_error' => $percentError,
                'Judgement' => $judgement ?: null,
                'level' => $level ?: null,
            ];
        }
        
        return $readings;
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
