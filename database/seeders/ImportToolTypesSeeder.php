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
                        $specsList[] = [
                            'label' => $label,
                            'min'   => $min,
                            'max'   => $max,
                        ];
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

                if (!empty($specsList)) {
                    $dimensionSpecs[] = [
                        'point' => $char,
                        'trend' => $trend,
                        'specs' => $specsList,
                    ];
                }
            }

            // 2) UI Options
            $uiOptions = [];
            for ($i = 1; $i <= 15; $i++) {
                $sVal  = $oldRow->{'S' . $i}  ?? null;
                $csVal = $oldRow->{'Cs' . $i} ?? null;
                if ($sVal || $csVal) {
                    $uiOptions[] = [
                        'index' => $i,
                        's'     => $this->cleanText($sVal),
                        'cs'    => $this->cleanText($csVal),
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
                    'ui_options'      => json_encode($uiOptions, JSON_UNESCAPED_UNICODE),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }
    }
}