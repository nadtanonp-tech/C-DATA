<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCalThreadPlugSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('📥 เริ่ม Import Thread Plug Gauge');
        $this->command->info('===========================================');
        
        // 🔥 ลบข้อมูลเก่าเฉพาะ Thread Plug Gauge (8-04-%) ก่อน import
        $this->command->warn('⚠️  กำลังลบข้อมูลเก่า...');
        $threadPlugGaugeInstrumentIds = DB::table('instruments')
            ->where('code_no', 'LIKE', '8-04-%')
            ->pluck('id')
            ->toArray();
        
        if (!empty($threadPlugGaugeInstrumentIds)) {
            DB::table('calibration_logs')
                ->whereIn('instrument_id', $threadPlugGaugeInstrumentIds)
                ->delete();
            
            $this->command->info('🗑️ ลบข้อมูล Thread Plug Gauge เก่าเรียบร้อยแล้ว');
        }

        // 1. ดึงข้อมูลจากตารางเก่า (เฉพาะ 8-04-% และ 8-05-%)
        $oldLogs = DB::table('CALThreadPl')
            ->where(function ($q) {
                $q->where('CodeNo', 'LIKE', '8-04-%')
                  ->orWhere('CodeNo', 'LIKE', '8-05-%');
            })
            ->get();

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

            // 🔥 ดึง dimension_specs จาก tool_type เพื่อเอา min/max spec
            $toolType = DB::table('tool_types')
                        ->where('id', $instrument->tool_type_id)
                        ->select('dimension_specs')
                        ->first();
            
            $dimensionSpecs = $toolType ? json_decode($toolType->dimension_specs, true) : [];
            
            // 🔥 หา spec สำหรับ Point A และ Point B
            $pointASpec = null;
            $pointBSpec = null;
            
            foreach ($dimensionSpecs as $spec) {
                $point = strtoupper(trim($spec['point'] ?? ''));
                if ($point === 'A') {
                    $pointASpec = $spec;
                } elseif ($point === 'B') {
                    $pointBSpec = $spec;
                }
            }

            // 3. 🔥 ปั้น JSON ใน format ใหม่ที่มี readings -> specs -> measurements
            $readings = [];
            
            // =============================================
            // 🔥 Point A - Major, Pitch (จากข้อมูลเก่า)
            // =============================================
            $pointASpecs = [];
            
            // A-Major: 4 ค่าวัด (AMajor1-1, AMajor1-2, AMajor2-1, AMajor2-2)
            $aMajorValues = [
                $this->parseNumeric($row->{'AMajor1-1'}),
                $this->parseNumeric($row->{'AMajor1-2'}),
                $this->parseNumeric($row->{'AMajor2-1'}),
                $this->parseNumeric($row->{'AMajor2-2'}),
            ];
            
            // 🔥 ตรวจสอบว่ามีค่าที่ valid หรือไม่
            if ($this->hasValidValues($aMajorValues)) {
                // ดึง min/max spec จาก dimension_specs สำหรับ Major
                $aMajorMinSpec = null;
                $aMajorMaxSpec = null;
                if ($pointASpec && isset($pointASpec['specs'])) {
                    foreach ($pointASpec['specs'] as $specItem) {
                        if (($specItem['label'] ?? '') === 'Major') {
                            $aMajorMinSpec = $specItem['min'] ?? null;
                            $aMajorMaxSpec = $specItem['max'] ?? null;
                            break;
                        }
                    }
                }
                
                $pointASpecs[] = [
                    'label' => 'Major',
                    'min_spec' => $aMajorMinSpec !== null ? rtrim(rtrim(number_format((float)$aMajorMinSpec, 8, '.', ''), '0'), '.') : null,
                    'max_spec' => $aMajorMaxSpec !== null ? rtrim(rtrim(number_format((float)$aMajorMaxSpec, 8, '.', ''), '0'), '.') : null,
                    'measurements' => array_map(fn($v) => ['value' => $v], $aMajorValues),
                    'reading' => $this->parseNumeric($row->{'AvgAMajor'}),
                    'error' => null,
                    'Judgement' => trim($row->JudgeAMajor) ?: null,
                    'grade' => trim($row->GradeAMajor) ?: null,
                ];
            }
            
            // A-Pitch: 4 ค่าวัด (APitch1-1, APitch1-2, APitch2-1, APitch2-2)
            $aPitchValues = [
                $this->parseNumeric($row->{'APitch1-1'}),
                $this->parseNumeric($row->{'APitch1-2'}),
                $this->parseNumeric($row->{'APitch2-1'}),
                $this->parseNumeric($row->{'APitch2-2'}),
            ];
            
            // 🔥 ตรวจสอบว่ามีค่าที่ valid หรือไม่
            if ($this->hasValidValues($aPitchValues)) {
                // ดึง min/max spec จาก dimension_specs สำหรับ Pitch
                $aPitchMinSpec = null;
                $aPitchMaxSpec = null;
                if ($pointASpec && isset($pointASpec['specs'])) {
                    foreach ($pointASpec['specs'] as $specItem) {
                        if (($specItem['label'] ?? '') === 'Pitch') {
                            $aPitchMinSpec = $specItem['min'] ?? null;
                            $aPitchMaxSpec = $specItem['max'] ?? null;
                            break;
                        }
                    }
                }
                
                $pointASpecs[] = [
                    'label' => 'Pitch',
                    'min_spec' => $aPitchMinSpec !== null ? rtrim(rtrim(number_format((float)$aPitchMinSpec, 8, '.', ''), '0'), '.') : null,
                    'max_spec' => $aPitchMaxSpec !== null ? rtrim(rtrim(number_format((float)$aPitchMaxSpec, 8, '.', ''), '0'), '.') : null,
                    'measurements' => array_map(fn($v) => ['value' => $v], $aPitchValues),
                    'reading' => $this->parseNumeric($row->AvgAPitch),
                    'error' => null,
                    'Judgement' => trim($row->JudgeAPitch) ?: null,
                    'grade' => trim($row->GradeAPitch) ?: null,
                ];
            }
            
            // 🔥 เพิ่ม Point A เฉพาะเมื่อมี specs
            if (!empty($pointASpecs)) {
                $readings[] = [
                    'point' => 'A',
                    'trend' => $pointASpec['trend'] ?? 'Smaller',
                    'specs' => $pointASpecs,
                ];
            }
            
            // =============================================
            // 🔥 Point B - Plug, Major, Pitch (จากข้อมูลเก่า)
            // =============================================
            $pointBSpecs = [];
            
            // B-Plug: 4 ค่าวัด (BPlug1-1, BPlug1-2, BPlug2-1, BPlug2-2)
            $bPlugValues = [
                $this->parseNumeric($row->{'BPlug1-1'}),
                $this->parseNumeric($row->{'BPlug1-2'}),
                $this->parseNumeric($row->{'BPlug2-1'}),
                $this->parseNumeric($row->{'BPlug2-2'}),
            ];
            
            // 🔥 ตรวจสอบว่ามีค่าที่ valid หรือไม่
            if ($this->hasValidValues($bPlugValues)) {
                // ดึง min/max spec จาก dimension_specs สำหรับ Plug
                $bPlugMinSpec = null;
                $bPlugMaxSpec = null;
                if ($pointBSpec && isset($pointBSpec['specs'])) {
                    foreach ($pointBSpec['specs'] as $specItem) {
                        if (($specItem['label'] ?? '') === 'Plug') {
                            $bPlugMinSpec = $specItem['min'] ?? null;
                            $bPlugMaxSpec = $specItem['max'] ?? null;
                            break;
                        }
                    }
                }
                
                $pointBSpecs[] = [
                    'label' => 'Plug',
                    'min_spec' => $bPlugMinSpec !== null ? rtrim(rtrim(number_format((float)$bPlugMinSpec, 8, '.', ''), '0'), '.') : null,
                    'max_spec' => $bPlugMaxSpec !== null ? rtrim(rtrim(number_format((float)$bPlugMaxSpec, 8, '.', ''), '0'), '.') : null,
                    'measurements' => array_map(fn($v) => ['value' => $v], $bPlugValues),
                    'reading' => $this->parseNumeric($row->AvgBPlug),
                    'error' => null,
                    'Judgement' => trim($row->JudgeBPlug) ?: null,
                    'grade' => trim($row->GradeBPlug) ?: null,
                ];
            }
            
            // B-Major: 4 ค่าวัด (BMajor1-1, BMajor1-2, BMajor2-1, BMajor2-2)
            $bMajorValues = [
                $this->parseNumeric($row->{'BMajor1-1'}),
                $this->parseNumeric($row->{'BMajor1-2'}),
                $this->parseNumeric($row->{'BMajor2-1'}),
                $this->parseNumeric($row->{'BMajor2-2'}),
            ];
            
            // 🔥 ตรวจสอบว่ามีค่าที่ valid หรือไม่
            if ($this->hasValidValues($bMajorValues)) {
                // ดึง min/max spec จาก dimension_specs สำหรับ Major (Point B)
                $bMajorMinSpec = null;
                $bMajorMaxSpec = null;
                if ($pointBSpec && isset($pointBSpec['specs'])) {
                    foreach ($pointBSpec['specs'] as $specItem) {
                        if (($specItem['label'] ?? '') === 'Major') {
                            $bMajorMinSpec = $specItem['min'] ?? null;
                            $bMajorMaxSpec = $specItem['max'] ?? null;
                            break;
                        }
                    }
                }
                
                $pointBSpecs[] = [
                    'label' => 'Major',
                    'min_spec' => $bMajorMinSpec !== null ? rtrim(rtrim(number_format((float)$bMajorMinSpec, 8, '.', ''), '0'), '.') : null,
                    'max_spec' => $bMajorMaxSpec !== null ? rtrim(rtrim(number_format((float)$bMajorMaxSpec, 8, '.', ''), '0'), '.') : null,
                    'measurements' => array_map(fn($v) => ['value' => $v], $bMajorValues),
                    'reading' => $this->parseNumeric($row->AvgBMajor),
                    'error' => null,
                    'Judgement' => trim($row->JudgeBMajor) ?: null,
                    'grade' => trim($row->GradeBMajor) ?: null,
                ];
            }
            
            // B-Pitch: 4 ค่าวัด (BPitch1-1, BPitch1-2, BPitch2-1, BPitch2-2)
            $bPitchValues = [
                $this->parseNumeric($row->{'BPitch1-1'}),
                $this->parseNumeric($row->{'BPitch1-2'}),
                $this->parseNumeric($row->{'BPitch2-1'}),
                $this->parseNumeric($row->{'BPitch2-2'}),
            ];
            
            // 🔥 ตรวจสอบว่ามีค่าที่ valid หรือไม่
            if ($this->hasValidValues($bPitchValues)) {
                // ดึง min/max spec จาก dimension_specs สำหรับ Pitch (Point B)
                $bPitchMinSpec = null;
                $bPitchMaxSpec = null;
                if ($pointBSpec && isset($pointBSpec['specs'])) {
                    foreach ($pointBSpec['specs'] as $specItem) {
                        if (($specItem['label'] ?? '') === 'Pitch') {
                            $bPitchMinSpec = $specItem['min'] ?? null;
                            $bPitchMaxSpec = $specItem['max'] ?? null;
                            break;
                        }
                    }
                }
                
                $pointBSpecs[] = [
                    'label' => 'Pitch',
                    'min_spec' => $bPitchMinSpec !== null ? rtrim(rtrim(number_format((float)$bPitchMinSpec, 8, '.', ''), '0'), '.') : null,
                    'max_spec' => $bPitchMaxSpec !== null ? rtrim(rtrim(number_format((float)$bPitchMaxSpec, 8, '.', ''), '0'), '.') : null,
                    'measurements' => array_map(fn($v) => ['value' => $v], $bPitchValues),
                    'reading' => $this->parseNumeric($row->AvgBPitch),
                    'error' => null,
                    'Judgement' => trim($row->JudgeBPitch) ?: null,
                    'grade' => trim($row->GradeBPitch) ?: null,
                ];
            }
            
            // 🔥 เพิ่ม Point B เฉพาะเมื่อมี specs
            if (!empty($pointBSpecs)) {
                $readings[] = [
                    'point' => 'B',
                    'trend' => $pointBSpec['trend'] ?? 'Bigger',
                    'specs' => $pointBSpecs,
                ];
            }
            
            // 🔥 ข้ามถ้าไม่มี readings เลย
            if (empty($readings)) {
                $this->command->warn("⚠️ ข้าม {$row->CodeNo} - ไม่มีค่าวัดที่ valid");
                $skipCount++;
                continue;
            }
            
            // 🔥 สร้าง calibration_data ใน format ใหม่
            // 🔥 ตรวจสอบ code_no pattern เพื่อกำหนด calibration_type
            $codeNo = strtoupper(trim($row->CodeNo));
            $calibrationType = 'ThreadPlugGauge'; // default
            
            if (preg_match('/^\d-04-/', $codeNo)) {
                $calibrationType = 'ThreadPlugGauge';
            } elseif (preg_match('/^\d-06-/', $codeNo)) {
                $calibrationType = 'SerrationPlugGauge';
            } else {
                $this->command->warn("⚠️ ไม่รู้จัก pattern: {$codeNo} - ใช้ ThreadPlugGauge");
            }
            
            $calData = [
                'calibration_type' => $calibrationType,
                'readings' => $readings,
            ];

            // 4. เตรียมข้อมูลบันทึก
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
        $this->command->info('✅ นำเข้าข้อมูล Thread Plug Gauge (CALThreadPl) เสร็จสิ้น!');
        $this->command->info("📊 สถิติ: นำเข้า {$importCount} รายการ | ข้าม {$skipCount} รายการ");

        // =============================================
        // 🔥 Part 2: Import จาก CALSerPlThrPlSerPlFor (เฉพาะ 8-06-%)
        // =============================================
        $this->command->info('');
        $this->command->info('📥 เริ่ม Import Serration Plug (8-06-%)');
        $this->command->warn('⚠️  กำลังลบข้อมูลเก่า...');
        
        // ลบข้อมูลเก่าเฉพาะ 8-06-% ก่อน import
        $serrationPlugIds = DB::table('instruments')
            ->where('code_no', 'LIKE', '8-06-%')
            ->pluck('id')
            ->toArray();
        
        if (!empty($serrationPlugIds)) {
            DB::table('calibration_logs')
                ->whereIn('instrument_id', $serrationPlugIds)
                ->delete();
            
            $this->command->info('🗑️ ลบข้อมูล Serration Plug Gauge (8-06-%) เก่าเรียบร้อยแล้ว');
        }

        // ดึงข้อมูลจาก CALSerPlThrPlSerPlFor เฉพาะ 8-06-%
        $serrationLogs = DB::table('CALSerPlThrPlSerPlFor')
            ->where('CodeNo', 'LIKE', '8-06-%')
            ->get();

        $batchData2 = [];
        $importCount2 = 0;
        $skipCount2 = 0;

        foreach ($serrationLogs as $row) {
            // หา ID เครื่องมือ
            $instrument = DB::table('instruments')
                            ->where('code_no', strtoupper(trim($row->CodeNo)))
                            ->select('id', 'tool_type_id')
                            ->first();

            if (!$instrument) {
                $this->command->warn("⚠️ ไม่พบ Instrument: {$row->CodeNo}");
                $skipCount2++;
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
                $this->command->warn("   ⚠️ ข้าม: ไม่มีข้อมูล specs สำหรับ {$row->CodeNo}");
                $skipCount2++;
                continue;
            }
            
            $readings[] = [
                'point' => $row->Section ?? 'A', // ใช้ Section หรือ default 'A'
                'trend' => $pointSpec['trend'] ?? 'Smaller',
                'specs' => $specs,
            ];
            
            // 🔥 ตรวจสอบ code_no pattern เพื่อกำหนด calibration_type
            $codeNo = strtoupper(trim($row->CodeNo));
            $calibrationType = 'SerrationPlugGauge'; // default สำหรับ 8-06-
            
            if (preg_match('/^\d-04-/', $codeNo)) {
                $calibrationType = 'ThreadPlugGauge';
            } elseif (preg_match('/^\d-06-/', $codeNo)) {
                $calibrationType = 'SerrationPlugGauge';
            } else {
                $this->command->warn("⚠️ ไม่รู้จัก pattern: {$codeNo} - ใช้ SerrationPlugGauge");
            }
            
            // สร้าง calibration_data
            $calData = [
                'calibration_type' => $calibrationType,
                'readings' => $readings,
            ];

            $batchData2[] = [
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

            if (count($batchData2) >= $batchSize) {
                DB::table('calibration_logs')->insert($batchData2);
                $importCount2 += count($batchData2);
                $batchData2 = [];
            }
        }

        if (!empty($batchData2)) {
            DB::table('calibration_logs')->insert($batchData2);
            $importCount2 += count($batchData2);
        }
        
        $this->command->info('');
        $this->command->info('✅ นำเข้าข้อมูล Serration Plug (8-06-%) เสร็จสิ้น!');
        $this->command->info("📊 สถิติ: นำเข้า {$importCount2} รายการ | ข้าม {$skipCount2} รายการ");
        $this->command->info('===========================================');
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
