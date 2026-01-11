<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalVernierOtherSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('📥 เริ่ม Import Vernier Other (CALVernierOther)');
        $this->command->info('===========================================');
        
        // ดึงข้อมูลจาก CALVernierOther
        $oldLogs = DB::table('CALVernierOther')->get();
        $totalRecords = $oldLogs->count();
        $this->command->info("📊 พบข้อมูล {$totalRecords} รายการใน CALVernierOther");

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

            // ดึง dimension_specs และ criteria_unit จาก tool_type
            $toolType = DB::table('tool_types')
                        ->where('id', $instrument->tool_type_id)
                        ->select('dimension_specs', 'criteria_unit')
                        ->first();
            
            $dimensionSpecs = $toolType ? json_decode($toolType->dimension_specs, true) : [];
            $criteriaUnit = $toolType ? json_decode($toolType->criteria_unit, true) : [];
            
            // ดึง criteria สำหรับการ grading
            $criteria1 = null;
            $criteria2 = null;
            if (is_array($criteriaUnit)) {
                foreach ($criteriaUnit as $item) {
                    if (($item['index'] ?? 0) == 1) {
                        $criteria1 = $item['criteria_1'] ?? null;
                        $criteria2 = $item['criteria_2'] ?? null;
                        break;
                    }
                }
            }
            
            // 🔥 สร้าง readings (Section 1: ตรวจสอบความถูกต้องของสเกล) - 15 points
            $readings = $this->buildReadings($row, $dimensionSpecs, $criteria1, $criteria2);
            
            // ข้ามถ้าไม่มี readings เลย
            if (empty($readings)) {
                $this->command->warn("   ⚠️ ข้าม: ไม่มีข้อมูล readings สำหรับ {$row->CodeNo}");
                $skipCount++;
                continue;
            }
            
            // 🔥 สร้าง calibration_data
            $calData = [
                'calibration_type' => 'VernierOther',
                'readings' => $readings,
            ];
            
            // flatness_check จาก SerRough
            $flatnessCheck = trim($row->SerRough ?? '') ?: null;
            if ($flatnessCheck !== null) {
                $calData['flatness_check'] = $flatnessCheck;
            }

            // 🔥 Environment with measurement_point
            $measurementPoint = trim($row->CAL_InOut ?? '') ?: 'ไม่มีจุดวัด';

            $batchData[] = [
                'instrument_id' => $instrument->id,
                'cal_date'      => $this->parseDate($row->CalDate ?? null),
                'next_cal_date' => $this->parseDate($row->DueDate ?? null),
                'cal_place'     => 'Internal',
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                
                'environment'   => json_encode([
                    'temperature' => $this->parseNumeric($row->Temp ?? null),
                    'humidity' => $this->parseNumeric($row->Humidity ?? null),
                    'measurement_point' => $measurementPoint,
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
        $this->command->info('✅ นำเข้าข้อมูล Vernier Other เสร็จสิ้น!');
        $this->command->info("📊 สถิติ: นำเข้า {$importCount} รายการ | ข้าม {$skipCount} รายการ");
        $this->command->info('===========================================');
    }

    /**
     * 🔥 สร้าง readings สำหรับ Section 1 (ตรวจสอบความถูกต้องของสเกล) - 15 points
     */
    private function buildReadings($row, $dimensionSpecs, $criteria1, $criteria2): array
    {
        $readings = [];
        $pointLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
        
        for ($i = 1; $i <= 15; $i++) {
            // ดึง 4 ค่าวัด
            $measurements = [];
            $values = [];
            for ($j = 1; $j <= 4; $j++) {
                $colName = "R{$i}-{$j}";
                if (isset($row->$colName)) {
                    $val = $this->parseNumeric($row->$colName);
                    if ($val !== null) {
                        $measurements[] = ['value' => $val];
                        $values[] = (float) $val;
                    }
                }
            }
            
            // ข้ามถ้าไม่มี measurements
            if (empty($measurements)) {
                continue;
            }
            
            $pointLabel = $pointLabels[$i - 1] ?? "P{$i}";
            
            // ดึง S value และ Cs value จาก dimension_specs
            $sValue = null;
            $csValue = null;
            
            if (is_array($dimensionSpecs)) {
                foreach ($dimensionSpecs as $spec) {
                    if (($spec['point'] ?? '') === $pointLabel) {
                        if (isset($spec['specs']) && is_array($spec['specs'])) {
                            foreach ($spec['specs'] as $s) {
                                if (($s['label'] ?? '') === 'S') {
                                    $sValue = $s['s_std'] ?? null;
                                }
                                if (($s['label'] ?? '') === 'Cs') {
                                    $csValue = $s['cs_std'] ?? null;
                                }
                            }
                        }
                        break;
                    }
                }
            }
            
            // 🔥 ดึงค่า Average, SD, Scale (correction) จาก source table
            $avgCol = "Avg{$i}";
            $sdCol = "SD{$i}";
            $scaleCol = "Scale{$i}";
            
            $average = isset($row->$avgCol) ? $this->parseNumeric($row->$avgCol) : null;
            $sd = isset($row->$sdCol) ? $this->parseNumeric($row->$sdCol) : null;
            $correction = isset($row->$scaleCol) ? $this->parseNumeric($row->$scaleCol) : null;
            
            // ถ้าไม่มีค่าจาก source ให้คำนวณเอง
            if ($average === null && count($values) > 0) {
                $average = number_format(array_sum($values) / count($values), 3, '.', '');
            }
            if ($sd === null && count($values) > 1) {
                $sd = number_format($this->calculateSD($values), 3, '.', '');
            }
            
            // ดึง Judgement และ Level จาก source
            $judgement = trim($row->{"Judge{$i}"} ?? '') ?: null;
            $level = trim($row->{"Grade{$i}"} ?? '') ?: null;
            
            $specs = [[
                'label' => 'S',
                's_value' => $sValue,
                'measurements' => $measurements,
                'average' => $average,
                'sd' => $sd,
                'correction' => $correction,
                'Judgement' => $judgement,
                'level' => $level,
            ]];
            
            $readings[] = [
                'point' => $pointLabel,
                'cs_value' => $csValue,
                'specs' => $specs,
            ];
        }
        
        return $readings;
    }

    /**
     * 🔥 คำนวณ Standard Deviation
     */
    private function calculateSD(array $values): float
    {
        $n = count($values);
        if ($n <= 1) return 0;
        
        $mean = array_sum($values) / $n;
        $sumSquares = 0;
        
        foreach ($values as $val) {
            $sumSquares += pow($val - $mean, 2);
        }
        
        return sqrt($sumSquares / ($n - 1)); // Sample SD
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
