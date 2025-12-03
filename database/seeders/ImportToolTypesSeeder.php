<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportToolTypesSeeder extends Seeder
{
    /**
     * Clean ข้อความก่อนบันทึก DB
     */
    private function cleanText($text)
    {
        // null ให้เป็น null
        if ($text === null) {
            return null;
        }

        // กัน mb_substr พังถ้าเป็นตัวเลข
        if (is_numeric($text)) {
            $text = (string) $text;
        }

        // clean ช่องว่าง
        $text = trim($text);

        // กันข้อความยาวเกินไป
        return mb_substr($text, 0, 1000);
    }

    public function run()
    {
        // อ่านข้อมูลจาก DB เก่า (ถ้ามี connection แยกให้ใช้ DB::connection('mysql_old')->table('Type'))
        $oldDataRows = DB::table('Type')->get();

        foreach ($oldDataRows as $oldRow) {

            // ---------------------------------------------
            // 0) เตรียมค่า code_type, name, size + fallback
            // ---------------------------------------------
            $codeType = $this->cleanText($oldRow->CodeType ?? null);
            $nameRaw  = $this->cleanText($oldRow->Name ?? null);
            $size     = $this->cleanText($oldRow->Size ?? null);

            // ถ้าไม่มี code_type เลย แนะนำให้ข้าม record นี้
            if ($codeType === null || $codeType === '') {
                // จะ log ไว้ก็ได้ เช่น logger()->warning(...);
                continue;
            }

            // ถ้า name ว่าง → ใช้ size หรือไม่ก็ fallback เป็น TYPE {code_type}
            $name = $nameRaw;
            if ($name === null || $name === '') {
                if ($size !== null && $size !== '') {
                    $name = $size;
                } else {
                    $name = 'TYPE ' . $codeType;
                }
            }

            // -----------------------------------------------------
            // 1) Dimension A–Q
            // -----------------------------------------------------
            $specs    = [];
            $prefixes = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q'];

            foreach ($prefixes as $char) {

                // ----- Clean SmallBig -----
                $rawVal   = $oldRow->{'SmallBig' . $char} ?? null;
                $cleanVal = null;

                if ($rawVal !== null) {
                    $trimVal = trim($rawVal);

                    if (in_array($trimVal, ['ใหญ่ขึ้น', 'Big', 'Bigger'], true)) {
                        $cleanVal = 'Bigger';
                    } elseif (in_array($trimVal, ['เล็กลง', 'Small', 'Smaller'], true)) {
                        $cleanVal = 'Smaller';
                    } else {
                        $cleanVal = $trimVal;
                    }
                }

                // ----- ฟิลด์หลักของ dimension -----
                $data = [
                    'max'        => $oldRow->{$char . '_Max'}     ?? null,
                    'min'        => $oldRow->{$char . '_Min'}     ?? null,
                    'lip_max'    => $oldRow->{$char . '_MaxLip'}  ?? null,
                    'lip_min'    => $oldRow->{$char . '_MinLip'}  ?? null,
                    'major_max'  => $oldRow->{$char . 'MajorMax'} ?? null,
                    'major_min'  => $oldRow->{$char . 'MajorMin'} ?? null,
                    'pitch_max'  => $oldRow->{$char . 'PitchMax'} ?? null,
                    'pitch_min'  => $oldRow->{$char . 'PitchMin'} ?? null,
                    'small_big'  => $cleanVal,
                ];

                // ----- ฟิลด์พิเศษ -----
                if ($char === 'A') {
                    $data['std_part'] = $this->cleanText($oldRow->STDPartA ?? null);
                }

                if ($char === 'B') {
                    $data['std_part'] = $this->cleanText($oldRow->STDPartB ?? null);
                    $data['plug_max'] = $oldRow->BPlug_Max ?? null;
                    $data['plug_min'] = $oldRow->BPlug_Min ?? null;
                }

                if ($char === 'D') {
                    $data['std_checking_fit']  = $this->cleanText($oldRow->STDCheckingFitD  ?? null);
                    $data['std_checking_wear'] = $this->cleanText($oldRow->STDCheckingWearD ?? null);
                }

                // เก็บเฉพาะค่าไม่เป็น null
                $specs[$char] = array_filter($data, fn($v) => !is_null($v));
            }

            // -----------------------------------------------------
            // 2) UI Options S1–S15, Cs1–Cs15
            // -----------------------------------------------------
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

            // -----------------------------------------------------
            // 3) INSERT ข้อมูลลง table tool_types
            // -----------------------------------------------------
            DB::table('tool_types')->insert([
                'code_type'     => $codeType,          // ใช้ตัวแปรที่เตรียมไว้
                'name'          => $name,              // ใช้ name ที่มี fallback แล้ว
                'size'          => $size,

                // รูปจะจัดการทีหลัง → null ไว้ก่อน
                'picture_path'  => null,

                // ตัวเลขไม่ต้อง clean
                'pr_rate'       => $oldRow->PRRate,

                // ข้อความที่มักมีขยะ → clean ให้หมด
                'reference_doc' => $this->cleanText($oldRow->Reference ?? null),
                'drawing_no'    => $this->cleanText($oldRow->DrawingNo ?? null),
                'remark'        => $this->cleanText($oldRow->Remark ?? null),

                'pre'           => $this->cleanText($oldRow->Pre ?? null),

                // ถ้า cal_flag เป็น VARCHAR → cleanText OK
                'cal_flag'      => $this->cleanText($oldRow->CAL ?? null),

                'input_data'    => $this->cleanText($oldRow->InputData ?? null),

                // JSON เก็บเป็น UTF-8 แบบไม่ escape
                'dimension_specs' => json_encode($specs, JSON_UNESCAPED_UNICODE),
                'ui_options'      => json_encode($uiOptions, JSON_UNESCAPED_UNICODE),

                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }
}
