<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalThreadPlugGaugeFitWearSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('📥 เริ่ม Import Thread Plug Gauge Fit Wear จาก CALSerPlThrPlSerPlFor...');
        
        // 🔥 ลบข้อมูลเก่าเฉพาะ 5-08-%, 5-09-%, 8-08-%, 8-09-% ก่อน import
        $fitWearInstrumentIds = DB::table('instruments')
            ->where(function ($q) {
                $q->where('code_no', 'LIKE', '5-08-%')
                  ->orWhere('code_no', 'LIKE', '5-09-%')
                  ->orWhere('code_no', 'LIKE', '8-08-%')
                  ->orWhere('code_no', 'LIKE', '8-09-%');
            })
            ->pluck('id')
            ->toArray();
        
        if (!empty($fitWearInstrumentIds)) {
            DB::table('calibration_logs')
                ->whereIn('instrument_id', $fitWearInstrumentIds)
                ->delete();
            
            $this->command->info('🗑️ ลบข้อมูล Thread Plug Gauge Fit Wear เก่าเรียบร้อยแล้ว');
        }

        // ดึงข้อมูลจาก CALSerPlThrPlSerPlFor เฉพาะ 5-08-%, 5-09-%, 8-08-%, 8-09-%
        $oldLogs = DB::table('CALSerPlThrPlSerPlFor')
            ->where(function ($q) {
                $q->where('CodeNo', 'LIKE', '5-08-%')
                  ->orWhere('CodeNo', 'LIKE', '5-09-%')
                  ->orWhere('CodeNo', 'LIKE', '8-08-%')
                  ->orWhere('CodeNo', 'LIKE', '8-09-%');
            })
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

            // ดึง dimension_specs จาก tool_type
            $toolType = DB::table('tool_types')
                        ->where('id', $instrument->tool_type_id)
                        ->select('dimension_specs')
                        ->first();
            
            $dimensionSpecs = $toolType ? json_decode($toolType->dimension_specs, true) : [];
            
            // หา spec สำหรับ Point (ไม่แบ่ง A/B)
            $pointSpec = $dimensionSpecs[0] ?? null;
            
            // สร้าง readings
            $readings = [];
            $specs = [];
            
            // Major: 4 ค่าวัด
            $majorValues = [
                $this->parseNumeric($row->{'Major1-1'}),
                $this->parseNumeric($row->{'Major1-2'}),
                $this->parseNumeric($row->{'Major2-1'}),
                $this->parseNumeric($row->{'Major2-2'}),
            ];
            
            if ($this->hasValidValues($majorValues)) {
                $majorMinSpec = null;
                $majorMaxSpec = null;
                if ($pointSpec && isset($pointSpec['specs'])) {
                    foreach ($pointSpec['specs'] as $specItem) {
                        if (($specItem['label'] ?? '') === 'Major') {
                            $majorMinSpec = $specItem['min'] ?? null;
                            $majorMaxSpec = $specItem['max'] ?? null;
                            break;
                        }
                    }
                }
                
                $specs[] = [
                    'label' => 'Major',
                    'min_spec' => $majorMinSpec !== null ? rtrim(rtrim(number_format((float)$majorMinSpec, 8, '.', ''), '0'), '.') : null,
                    'max_spec' => $majorMaxSpec !== null ? rtrim(rtrim(number_format((float)$majorMaxSpec, 8, '.', ''), '0'), '.') : null,
                    'measurements' => array_map(fn($v) => ['value' => $v], $majorValues),
                    'reading' => $this->parseNumeric($row->AvgMajor),
                    'error' => null,
                    'Judgement' => trim($row->JudgeMajor) ?: null,
                    'grade' => trim($row->GradeMajor) ?: null,
                ];
            }
            
            // Pitch: 4 ค่าวัด
            $pitchValues = [
                $this->parseNumeric($row->{'Pitch1-1'}),
                $this->parseNumeric($row->{'Pitch1-2'}),
                $this->parseNumeric($row->{'Pitch2-1'}),
                $this->parseNumeric($row->{'Pitch2-2'}),
            ];
            
            if ($this->hasValidValues($pitchValues)) {
                $pitchMinSpec = null;
                $pitchMaxSpec = null;
                if ($pointSpec && isset($pointSpec['specs'])) {
                    foreach ($pointSpec['specs'] as $specItem) {
                        if (($specItem['label'] ?? '') === 'Pitch') {
                            $pitchMinSpec = $specItem['min'] ?? null;
                            $pitchMaxSpec = $specItem['max'] ?? null;
                            break;
                        }
                    }
                }
                
                $specs[] = [
                    'label' => 'Pitch',
                    'min_spec' => $pitchMinSpec !== null ? rtrim(rtrim(number_format((float)$pitchMinSpec, 8, '.', ''), '0'), '.') : null,
                    'max_spec' => $pitchMaxSpec !== null ? rtrim(rtrim(number_format((float)$pitchMaxSpec, 8, '.', ''), '0'), '.') : null,
                    'measurements' => array_map(fn($v) => ['value' => $v], $pitchValues),
                    'reading' => $this->parseNumeric($row->AvgPitch),
                    'error' => null,
                    'Judgement' => trim($row->JudgePitch) ?: null,
                    'grade' => trim($row->GradePitch) ?: null,
                ];
            }
            
            // ข้ามถ้าไม่มี specs
            if (empty($specs)) {
                $skipCount++;
                continue;
            }
            
            $readings[] = [
                'point' => $row->Section ?? 'A', // ใช้ Section หรือ default 'A'
                'trend' => $pointSpec['trend'] ?? 'Smaller',
                'specs' => $specs,
            ];
            
            // 🔥 สร้าง calibration_data ด้วย calibration_type = ThreadPlugGaugeFitWear
            $calData = [
                'calibration_type' => 'ThreadPlugGaugeFitWear',
                'readings' => $readings,
            ];

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
        
        $this->command->info("✅ Import Thread Plug Gauge Fit Wear เสร็จสิ้น: {$importCount} records, ข้าม: {$skipCount} records");
    }

    /**
     * 🔥 ตรวจสอบว่า array มีค่าที่ valid (ไม่ใช่ null และไม่ใช่ 0) อย่างน้อย 1 ค่า
     */
    private function hasValidValues(array $values): bool
    {
        foreach ($values as $val) {
            if ($val !== null && $val !== '' && floatval($val) != 0) {
                return true;
            }
        }
        return false;
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
