<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportToolTypesSeeder extends Seeder
{
    private function cleanText($text)
    {
        if ($text === null) return null;
        if (is_numeric($text)) $text = (string) $text;
        $text = trim($text);
        return mb_substr($text, 0, 1000);
    }

    public function run()
    {
        $oldDataRows = DB::table('Type')->get(); 

        foreach ($oldDataRows as $oldRow) {

            // 0) ข้อมูลหลัก
            $codeType = $this->cleanText($oldRow->CodeType ?? null);
            if (empty($codeType)) continue;

            $nameRaw = $this->cleanText($oldRow->Name ?? null);
            $size    = $this->cleanText($oldRow->Size ?? null);
            
            $name = $nameRaw;
            if (empty($name)) {
                $name = (!empty($size)) ? $size : 'TYPE ' . $codeType;
            }

            // 1) แปลง A-Q เป็น JSON
            $dimensionSpecs = []; 
            $prefixes = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q'];

            foreach ($prefixes as $char) {
                // หา Trend
                $rawVal = $oldRow->{'SmallBig' . $char} ?? null;
                $trend = null;
                if ($rawVal !== null) {
                    $trimVal = trim($rawVal);
                    if (in_array($trimVal, ['ใหญ่ขึ้น', 'Big', 'Bigger'], true)) $trend = 'Bigger';
                    elseif (in_array($trimVal, ['เล็กลง', 'Small', 'Smaller'], true)) $trend = 'Smaller';
                    else $trend = $trimVal;
                }

                $specsList = [];

                // 🔥 ฟังก์ชันช่วยเก็บค่า (อัปเกรดใหม่: เพิ่ม $ignoreZero) 🔥
                // $ignoreZero = true แปลว่า "ถ้าค่าเป็น 0 ให้ถือว่าไม่มีข้อมูล (Null)"
                $addSpec = function($label, $minKey, $maxKey, $ignoreZero = false) use ($oldRow, &$specsList) {
                    $min = $oldRow->{$minKey} ?? null;
                    $max = $oldRow->{$maxKey} ?? null;
                    
                    // Logic พิเศษสำหรับ Lip: ถ้าเป็น 0 ให้กลายเป็น Null
                    if ($ignoreZero) {
                        if ((float)$min == 0) $min = null;
                        if ((float)$max == 0) $max = null;
                    }

                    // ถ้ามีค่าอย่างใดอย่างหนึ่ง (ที่ไม่ใช่ Null) ให้บันทึก
                    if ($min !== null || $max !== null) {
                        $specObj = [
                            'label' => $label,
                            'min'   => $min,
                            'max'   => $max,
                        ];

                        // 🔥 Clean ค่า Null ออกจาก Object ย่อย (min/max ที่เป็น null จะหายไป)
                        $specsList[] = array_filter($specObj, fn($v) => !is_null($v));
                    }
                };

                // เรียกใช้งาน
                $addSpec('STD', $char . '_Min', $char . '_Max');           
                
                // ✅ Lip: เปิดโหมด ignoreZero = true (ถ้าเป็น 0 ไม่ต้องเอามา)
                $addSpec('Lip', $char . '_MinLip', $char . '_MaxLip', true);     
                
                $addSpec('Major', $char . 'MajorMin', $char . 'MajorMax'); 
                $addSpec('Pitch', $char . 'PitchMin', $char . 'PitchMax'); 

                if ($char === 'B') {
                    $addSpec('Plug', 'BPlug_Min', 'BPlug_Max'); 
                }

                // 🔥 เพิ่ม Logic ดึงค่า S และ Cs เข้าไปใน Specs (เริ่มจาก A=1, B=2...)
                $charIndex = array_search($char, $prefixes) + 1; // A->1, B->2
                $sVal  = $this->cleanText($oldRow->{'S' . $charIndex}  ?? null);
                $csVal = $this->cleanText($oldRow->{'Cs' . $charIndex} ?? null);

                if (!empty($sVal)) {
                    $specsList[] = [
                        'label'   => 'S',
                        's_std' => $sVal,
                    ];
                }
                if (!empty($csVal)) {
                    $specsList[] = [
                        'label'    => 'Cs',
                        'cs_std' => $csVal,
                    ];
                }

                if (!empty($specsList)) {
                    $pointObj = [
                        'point' => $char,
                        'trend' => $trend,
                        'specs' => $specsList,
                    ];

                    // 🔥 Clean ค่า Null ออกจาก Object (เช่น trend: null ก็เอาออกเลย)
                    $pointObj = array_filter($pointObj, fn($v) => !is_null($v));

                    $dimensionSpecs[] = $pointObj;
                }
            }
            
            // (ลบ Loop UI Options เดิมออก เพราะย้ายไปรวมใน dimension_specs แล้ว)

            // 3) Criteria Unit Options (Range1-15, Criteria1-15, Criteria1-1..15-1, Unit1-15)
            $criteriaUnitOptions = [];
            for ($i = 1; $i <= 15; $i++) {
                // Construct column names dynamically
                $rangeKey      = 'Range' . $i;
                $criteriaKey   = 'Criteria' . $i;
                $criteria1Key  = 'Criteria' . $i . '-' . $i; // e.g., Criteria1-1, Criteria2-2
                $unitKey       = 'Unit' . $i;

                $rangeVal      = $this->cleanText($oldRow->{$rangeKey} ?? null);
                $criteriaVal   = $this->cleanText($oldRow->{$criteriaKey} ?? null);
                $criteria1Val  = $this->cleanText($oldRow->{$criteria1Key} ?? null);
                $unitVal       = $this->cleanText($oldRow->{$unitKey} ?? null);

                // Add to list if any value exists exists
                if ($rangeVal || $criteriaVal || $criteria1Val || $unitVal) {
                    $criteriaUnitOptions[] = [
                        'index'       => $i,
                        'range'       => $rangeVal,
                        'criteria_1'  => $criteriaVal,   // Maps to CriteriaX
                        'criteria_2'  => $criteria1Val,  // Maps to CriteriaX-X
                        'unit'        => $unitVal,
                    ];
                }
            }

            // 3) Update Or Insert
            DB::table('tool_types')->updateOrInsert(
                ['code_type' => $codeType],
                [
                    'name'            => $name,
                    'size'            => $size,
                    'picture_path'    => null,
                    'pr_rate'         => $oldRow->PRRate,
                    'reference_doc'   => $this->cleanText($oldRow->Reference ?? null),
                    'drawing_no'      => $this->cleanText($oldRow->DrawingNo ?? null),
                    'remark'          => $this->cleanText($oldRow->Remark ?? null),
                    'pre'             => $this->cleanText($oldRow->Pre ?? null),
                    'cal_flag'        => $this->cleanText($oldRow->CAL ?? null),
                    'input_data'      => $this->cleanText($oldRow->InputData ?? null),
                    'dimension_specs' => json_encode($dimensionSpecs, JSON_UNESCAPED_UNICODE),
                    'criteria_unit'   => json_encode($criteriaUnitOptions, JSON_UNESCAPED_UNICODE),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }
        // ---------------------------------------------------------------------
        // 4) Backfill Criteria Logic (DataRecord -> tool_types)
        // ---------------------------------------------------------------------
        // "ถ้ามีข้อมูล JSON ให้ลงข้อมูลนี้ทับเลย" -> ไม่ต้องกั้น whereNull
        // "ยกเว้นข้อมูลที่ เป็น 0 และ-0 หรือค่า Null" -> เช็คค่าก่อน
        
        $targetToolTypes = \App\Models\ToolType::all(); 
        $total = $targetToolTypes->count();
        $this->command->info("Found {$total} ToolTypes to check...");

        $processed = 0;
        $updated = 0;

        foreach ($targetToolTypes as $toolType) {
            $processed++;
            if ($processed % 10 === 0) {
                $this->command->info("Checking... {$processed}/{$total}");
            }

            $dataRecord = DB::table('DataRecord')
                ->where('Name', $toolType->code_type)
                ->first();

            if ($dataRecord) {
                // Condition: Check if Type is 'Pressure Gauge'
                $recordType = trim($dataRecord->Type ?? '');
                
                // ใช้การเช็คแบบ Loose หน่อยเผื่อมีเว้นวรรค หรือ Case sensitive
                if (stripos($recordType, 'Pressure Gauge') !== false) {
                    
                    $c1 = $this->cleanText($dataRecord->Criteria_1 ?? null);
                    $c2 = $this->cleanText($dataRecord->Criteria1_1 ?? null);

                    // Logic เดิม: Merge into Index 1
                    $existingData = $toolType->criteria_unit;
                    if (!is_array($existingData)) {
                        $existingData = [];
                    }

                    $foundIndex1 = false;
                    foreach ($existingData as &$item) {
                        if (isset($item['index']) && $item['index'] == 1) {
                            $item['criteria_1'] = $c1; // ใส่เลย ไม่ต้องเช็ค 0
                            $item['criteria_2'] = $c2;
                            if (empty($item['unit'])) $item['unit'] = '%F.S'; 
                            $foundIndex1 = true;
                            break;
                        }
                    }
                    unset($item);

                    if (!$foundIndex1) {
                        $existingData[] = [
                            'index'       => 1,
                            'range'       => null, 
                            'criteria_1'  => $c1,
                            'criteria_2'  => $c2,
                            'unit'        => '%F.S',  
                        ];
                    }

                    $toolType->update([
                        'criteria_unit' => $existingData, 
                    ]);

                    $updated++;
                    $this->command->info("✅ Updated (Pressure Gauge): {$toolType->code_type}");
                }
            }
        }
        $this->command->info("🎉 Finished! Checked: {$processed}, Updated: {$updated}");
    }
}