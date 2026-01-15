<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalVernierCaliperDigitalSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('📥 เริ่ม Import Vernier Caliper Digital (8-10-%)');
        $this->command->info('===========================================');
        
        // 🔥 ลบข้อมูลเก่าเฉพาะ 8-10-% ก่อน import
        $this->command->warn('⚠️  กำลังลบข้อมูลเก่า...');
        $instrumentIds = DB::table('instruments')
            ->where('code_no', 'LIKE', '8-10-%')
            ->pluck('id')
            ->toArray();
        
        if (!empty($instrumentIds)) {
            DB::table('calibration_logs')
                ->whereIn('instrument_id', $instrumentIds)
                ->delete();
            
            $this->command->info('🗑️ ลบข้อมูล Vernier Caliper Digital เก่าเรียบร้อยแล้ว');
        }

        // ดึงข้อมูลจาก CALVernierDigital เฉพาะ 8-10-%
        $oldLogs = DB::table('CALVernierDigital')
            ->where('CodeNo', 'LIKE', '8-10-%')
            ->get();

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
                        ->select('name', 'dimension_specs', 'criteria_unit')
                        ->first();
            
            // 🔥 กำหนด calibration_type จากชื่อ ToolType
            $toolTypeName = $toolType->name ?? '';
            
            // ข้าม ToolType ที่มีคำว่า "Special" (จะ import จาก ImportCalVernierOtherSeeder)
            if (stripos($toolTypeName, 'Special') !== false) {
                $skipCount++;
                continue;
            }
            
            // กำหนด calibration_type
            if (stripos($toolTypeName, 'Digital') !== false) {
                $calibrationType = 'VernierDigital';
            } else {
                $calibrationType = 'VernierCaliper';
            }
            
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
            
            // 🔥 สร้าง readings (Section 1: ตรวจสอบความถูกต้องของสเกล)
            $readings = $this->buildReadings($row, $dimensionSpecs, $criteria1, $criteria2);
            
            // 🔥 สร้าง readings_inner (Section 2: สเกลวัดใน)
            $readingsInner = $this->buildReadingsInner($row, $dimensionSpecs, $criteria1, $criteria2);
            
            // 🔥 สร้าง readings_depth (Section 3: สเกลวัดลึก)
            $readingsDepth = $this->buildReadingsDepth($row, $dimensionSpecs, $criteria1, $criteria2);
            
            // 🔥 สร้าง readings_parallelism (Section 4: ตรวจสอบความเรียบและความขนาน)
            $readingsParallelism = $this->buildReadingsParallelism($row, $dimensionSpecs, $criteria1, $criteria2);
            
            // ข้ามถ้าไม่มี readings เลย
            if (empty($readings) && empty($readingsInner) && empty($readingsDepth) && empty($readingsParallelism)) {
                $this->command->warn("   ⚠️ ข้าม: ไม่มีข้อมูล readings สำหรับ {$row->CodeNo}");
                $skipCount++;
                continue;
            }
            
            // 🔥 สร้าง calibration_data (ตรง structure กับเว็บ)
            $calData = [
                'calibration_type' => $calibrationType,
            ];
            
            if (!empty($readings)) {
                $calData['readings'] = $readings;
            }
            if (!empty($readingsInner)) {
                $calData['readings_inner'] = $readingsInner;
            }
            if (!empty($readingsDepth)) {
                $calData['readings_depth'] = $readingsDepth;
            }
            
            // flatness_check จาก SerRough
            $flatnessCheck = trim($row->SerRough ?? '') ?: null;
            if ($flatnessCheck !== null) {
                $calData['flatness_check'] = $flatnessCheck;
            }
            
            if (!empty($readingsParallelism)) {
                $calData['readings_parallelism'] = $readingsParallelism;
            }

            $batchData[] = [
                'instrument_id' => $instrument->id,
                'cal_date'      => $this->parseDate($row->CalDate ?? null),
                'next_cal_date' => $this->parseDate($row->DueDate ?? null),
                'cal_place'     => 'Internal',
                'calibration_type' => $calibrationType,
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
        $this->command->info('✅ นำเข้าข้อมูล Vernier Caliper Digital เสร็จสิ้น!');
        $this->command->info("📊 สถิติ: นำเข้า {$importCount} รายการ | ข้าม {$skipCount} รายการ");
        $this->command->info('===========================================');
    }

    /**
     * 🔥 สร้าง readings สำหรับ Section 1 (ตรวจสอบความถูกต้องของสเกล)
     */
    private function buildReadings($row, $dimensionSpecs, $criteria1, $criteria2): array
    {
        $readings = [];
        $pointLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        
        for ($i = 1; $i <= 10; $i++) {
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
            
            // 🔥 คำนวณ average, sd, correction
            $average = count($values) > 0 ? array_sum($values) / count($values) : null;
            $sd = count($values) > 1 ? $this->calculateSD($values) : 0;
            
            // correction = S + Cs - average
            $correction = null;
            if ($sValue !== null && $csValue !== null && $average !== null) {
                $correction = (float)$sValue + (float)$csValue - $average;
            }
            
            // ดึง Judgement และ Level จาก source หรือใช้ค่าที่มี
            $judgement = trim($row->{"Judge{$i}"} ?? '') ?: null;
            $level = trim($row->{"Grade{$i}"} ?? '') ?: null;
            
            $specs = [[
                'label' => 'S',
                's_value' => $sValue,
                'measurements' => $measurements,
                'average' => $average !== null ? number_format($average, 3, '.', '') : null,
                'sd' => $sd !== null ? number_format($sd, 3, '.', '') : null,
                'correction' => $correction !== null ? $this->formatNumeric($correction) : null,
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
     * 🔥 สร้าง readings_inner สำหรับ Section 2 (สเกลวัดใน)
     */
    private function buildReadingsInner($row, $dimensionSpecs, $criteria1, $criteria2): array
    {
        $readings = [];
        $pointLabels = ['A', 'B', 'C'];
        
        for ($i = 1; $i <= 3; $i++) {
            $measurements = [];
            $values = [];
            for ($j = 1; $j <= 2; $j++) {
                $colName = "InR{$i}-{$j}";
                if (isset($row->$colName)) {
                    $val = $this->parseNumeric($row->$colName);
                    if ($val !== null) {
                        $measurements[] = ['value' => $val];
                        $values[] = (float) $val;
                    }
                }
            }
            
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
            
            // 🔥 คำนวณ average, sd, correction
            $average = count($values) > 0 ? array_sum($values) / count($values) : null;
            $sd = count($values) > 1 ? $this->calculateSD($values) : 0;
            
            $correction = null;
            if ($sValue !== null && $csValue !== null && $average !== null) {
                $correction = (float)$sValue + (float)$csValue - $average;
            }
            
            $judgement = trim($row->{"JudgeInR{$i}"} ?? '') ?: null;
            $level = trim($row->{"GradeInR{$i}"} ?? '') ?: null;
            
            $specs = [[
                'label' => 'S',
                's_value' => $sValue,
                'measurements' => $measurements,
                'average' => $average !== null ? number_format($average, 3, '.', '') : null,
                'sd' => $sd !== null ? number_format($sd, 3, '.', '') : null,
                'correction' => $correction !== null ? $this->formatNumeric($correction) : null,
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
     * 🔥 สร้าง readings_depth สำหรับ Section 3 (สเกลวัดลึก)
     */
    private function buildReadingsDepth($row, $dimensionSpecs, $criteria1, $criteria2): array
    {
        $readings = [];
        $pointLabels = ['A', 'B', 'C'];
        
        for ($i = 1; $i <= 3; $i++) {
            $measurements = [];
            $values = [];
            for ($j = 1; $j <= 2; $j++) {
                $colName = "DepthR{$i}-{$j}";
                if (isset($row->$colName)) {
                    $val = $this->parseNumeric($row->$colName);
                    if ($val !== null) {
                        $measurements[] = ['value' => $val];
                        $values[] = (float) $val;
                    }
                }
            }
            
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
            
            // 🔥 คำนวณ average, sd, correction
            $average = count($values) > 0 ? array_sum($values) / count($values) : null;
            $sd = count($values) > 1 ? $this->calculateSD($values) : 0;
            
            $correction = null;
            if ($sValue !== null && $csValue !== null && $average !== null) {
                $correction = (float)$sValue + (float)$csValue - $average;
            }
            
            $judgement = trim($row->{"JudgeDepthR{$i}"} ?? '') ?: null;
            $level = trim($row->{"GradeDepthR{$i}"} ?? '') ?: null;
            
            $specs = [[
                'label' => 'S',
                's_value' => $sValue,
                'measurements' => $measurements,
                'average' => $average !== null ? number_format($average, 3, '.', '') : null,
                'sd' => $sd !== null ? number_format($sd, 3, '.', '') : null,
                'correction' => $correction !== null ? $this->formatNumeric($correction) : null,
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
     * 🔥 สร้าง readings_parallelism สำหรับ Section 4
     */
    private function buildReadingsParallelism($row, $dimensionSpecs, $criteria1, $criteria2): array
    {
        $readings = [];
        $pointLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        
        for ($i = 1; $i <= 10; $i++) {
            $paraCol = "Pa{$i}";
            if (!isset($row->$paraCol)) {
                continue;
            }
            
            $paraValue = $this->parseNumeric($row->$paraCol);
            // ยอมรับ parallelism = 0 ด้วย
            if ($paraValue === null) {
                continue;
            }
            
            $pointLabel = $pointLabels[$i - 1] ?? "P{$i}";
            
            // ดึง S value จาก dimension_specs
            $sValue = null;
            if (is_array($dimensionSpecs)) {
                foreach ($dimensionSpecs as $spec) {
                    if (($spec['point'] ?? '') === $pointLabel) {
                        if (isset($spec['specs']) && is_array($spec['specs'])) {
                            foreach ($spec['specs'] as $s) {
                                if (($s['label'] ?? '') === 'S') {
                                    $sValue = $s['s_std'] ?? null;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
            
            $judgement = trim($row->{"JudgeParallel{$i}"} ?? '') ?: null;
            // Note: typo in source column name - GradePararllel (double r)
            $level = trim($row->{"GradePararllel{$i}"} ?? '') ?: null;
            
            $readings[] = [
                'point' => $pointLabel,
                's_value' => $sValue,
                'position_start' => $this->parseNumeric($row->{"First{$i}"} ?? null),
                'position_middle' => $this->parseNumeric($row->{"Mid{$i}"} ?? null),
                'position_end' => $this->parseNumeric($row->{"Last{$i}"} ?? null),
                'parallelism' => $paraValue,
                'Judgement' => $judgement,
                'level' => $level,
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

    /**
     * 🔥 Format ตัวเลขให้แสดงแบบปกติ (ไม่ใช่ scientific notation)
     */
    private function formatNumeric($val): string
    {
        if ($val === null) return null;
        $float = (float) $val;
        // ใช้ precision สูงแล้ว trim trailing zeros
        return rtrim(rtrim(number_format($float, 8, '.', ''), '0'), '.');
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
