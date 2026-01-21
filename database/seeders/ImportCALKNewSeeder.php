<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCALKNewSeeder extends Seeder
{
    public function run()
    {
        // 🔥 ลบข้อมูลเก่าทิ้งก่อน (ถ้ามี)
        $this->command->warn('⚠️  กำลังลบข้อมูลเก่าใน calibration_logs...');
        DB::table('calibration_logs')
            ->whereNotNull('id') // ลบทั้งหมด (ปรับตามต้องการ)
            ->delete();
        $this->command->info('✅ ลบข้อมูลเก่าเสร็จสิ้น');
        
        // 1. ดึงข้อมูลจากตารางเก่า
        $oldLogs = DB::table('CALKNew')->get();
        $this->command->info("📊 พบข้อมูล {$oldLogs->count()} รายการจาก CALKNew");

        // 🔥 OPTIMIZATION: ดึง Instrument ทั้งหมดมารอไว้ใน Array (Key=CodeNo, Value=ID)
        $instrumentMap = DB::table('instruments')
                            ->pluck('id', 'code_no')
                            ->mapWithKeys(fn($id, $code) => [strtoupper(trim($code)) => $id])
                            ->toArray();

        $batchData = [];
        $batchSize = 50; 
        $imported = 0;
        $skipped = 0;

        foreach ($oldLogs as $row) {
            
            // ใช้ CodeNo จากไฟล์เก่า ไปเทียบหา ID ใน Array ที่เตรียมไว้
            $legacyCode = strtoupper(trim($row->CodeNo));
            
            if (!isset($instrumentMap[$legacyCode])) {
                // ถ้าหาไม่เจอ ให้ข้าม (หรือ Log เก็บไว้)
                $skipped++;
                $this->command->warn("⚠️  ข้ามรายการ: CodeNo {$legacyCode} ไม่พบใน instruments");
                continue; 
            }
            $instrumentId = $instrumentMap[$legacyCode];

            // 🔥 ดึงข้อมูล Instrument พร้อม ToolType เพื่อเอา dimension_specs
            $instrument = DB::table('instruments')
                ->where('id', $instrumentId)
                ->first();
            
            $dimensionSpecs = [];
            if ($instrument && $instrument->tool_type_id) {
                $toolType = DB::table('tool_types')
                    ->where('id', $instrument->tool_type_id)
                    ->first();
                
                if ($toolType && $toolType->dimension_specs) {
                    // dimension_specs เป็น JSON ต้อง decode
                    $dimensionSpecs = json_decode($toolType->dimension_specs, true) ?? [];
                }
            }

            // สร้าง Map สำหรับหา spec ของแต่ละ point
            $specsMap = [];
            foreach ($dimensionSpecs as $spec) {
                if (isset($spec['point'])) {
                    $specsMap[$spec['point']] = $spec;
                }
            }

            // 3. ปั้น JSON ในรูปแบบใหม่ที่ตรงกับ CalibrationKNewResource Form
            $readingsArray = [];
            $chars = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q'];

            foreach ($chars as $char) {
                // ตรวจสอบว่ามีข้อมูลในจุดนี้หรือไม่ (ดูจาก Result{Point})
                $reading = $row->{'Result'.$char} ?? null;
                
                if (is_null($reading) || $reading === '') {
                    continue; // ข้ามจุดที่ไม่มีข้อมูล
                }

                // 🔥 ดึงข้อมูล spec จาก ToolType (ถ้ามี)
                $specData = $specsMap[$char] ?? null;
                
                // ค่า default ถ้าไม่มี spec
                $trend = 'Smaller';
                $minSpec = '0';
                $maxSpec = '100';
                $stdLabel = 'STD';
                $allSpecs = [['label' => 'STD', 'min' => '0', 'max' => '100']];

                // ถ้ามี spec data จาก ToolType ให้ใช้ค่าจริง
                if ($specData) {
                    $trend = $specData['trend'] ?? 'Smaller';
                    
                    // ดึง spec จาก specs array (ใช้ spec แรก)
                    if (isset($specData['specs']) && is_array($specData['specs']) && count($specData['specs']) > 0) {
                        $mainSpec = $specData['specs'][0];
                        $minSpec = (string)($mainSpec['min'] ?? '0');
                        $maxSpec = (string)($mainSpec['max'] ?? '100');
                        $stdLabel = $mainSpec['label'] ?? 'STD';
                        
                        // all_specs เก็บทั้ง array
                        $allSpecs = $specData['specs'];
                    }
                }

                // 🎯 โครงสร้างที่ตรงตามตัวอย่าง JSON
                $readingItem = [
                    'point' => $char,
                    'trend' => $trend,
                    'min_spec' => $minSpec,
                    'max_spec' => $maxSpec,
                    'std_label' => $stdLabel,
                    'all_specs' => $allSpecs,
                    'reading' => (string)$reading, // ResultA -> reading
                    'error' => '0.0000', // ไม่มีในฐานข้อมูล ใส่ค่า default
                    'Judgement' => $row->{'Judge'.$char} ?? null, // JudgeA -> Judgement
                    'grade' => $row->{'Grade'.$char} ?? null, // GradeA -> grade
                ];

                // กรองค่า null ออก
                $readingItem = array_filter($readingItem, function($v) { 
                    return !is_null($v) && $v !== ''; 
                });

                $readingsArray[] = $readingItem;
            }

            // 🎯 โครงสร้าง calibration_data ที่ตรงกับ Form
            $calData = [
                'calibration_type' => 'KGauge', // 🔥 เพิ่ม Type ลง JSON
                'readings' => $readingsArray,
            ];

            // Validate result_status (Pass/Reject only)
            $resultStatus = trim($row->Total ?? '');
            if (!in_array($resultStatus, ['Pass', 'Reject'])) {
                $resultStatus = null;
            }

            // Validate cal_level (A/B/C only)
            $calLevel = trim($row->Grade ?? '');
            if (!in_array($calLevel, ['A', 'B', 'C'])) {
                $calLevel = null;
            }

            // Remark (null ถ้าว่าง)
            $remark = trim($row->RemarkC ?? '');
            $remark = ($remark === '') ? null : $remark;

            // 4. เตรียมข้อมูลบันทึก - ตรงกับ migration ปัจจุบัน
            $batchData[] = [
                'instrument_id' => $instrumentId,
                'calibration_type' => 'KGauge', // 🔥 เพิ่ม Type ลง Column
                'cal_date'      => $this->parseDate($row->CalDate),
                'next_cal_date' => $this->parseDate($row->DueDate),
                'cal_place'     => 'Internal', // ค่าคงที่
                
                // 🔥 JSON ที่ตรงกับ Form
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                
                // 🔥 Environment แบบ JSON
                'environment'   => json_encode([
                    'temperature' => $row->Temp ?? null,
                    'humidity' => $row->Humidity ?? null,
                ], JSON_UNESCAPED_UNICODE),
                
                // 🔥 ใช้ชื่อฟิลด์ที่ถูกต้องพร้อม validation
                'result_status' => $resultStatus, // Pass/Reject หรือ null
                'cal_level'     => $calLevel, // A/B/C หรือ null
                'remark'        => $remark, // ข้อความหรือ null
                
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $imported++;

            if (count($batchData) >= $batchSize) {
                DB::table('calibration_logs')->insert($batchData);
                $this->command->info("📝 บันทึกแล้ว {$imported} รายการ...");
                $batchData = [];
            }
        }

        // Insert ข้อมูลที่เหลือ
        if (!empty($batchData)) {
            DB::table('calibration_logs')->insert($batchData);
        }

        $this->command->info('');
        $this->command->info('✅ นำเข้าข้อมูลเสร็จสิ้น!');
        $this->command->info("📊 สถิติ: นำเข้า {$imported} รายการ | ข้าม {$skipped} รายการ");
    }

    private function parseDate($dateVal)
    {
        if (!$dateVal) return null;
        try {
            return Carbon::parse($dateVal)->format('Y-m-d');
        } catch (\Exception $e) { 
            return null; 
        }
    }
}