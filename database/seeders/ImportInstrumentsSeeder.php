<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportInstrumentsSeeder extends Seeder
{
    private function cleanText($text)
    {
        if ($text === null) return null;
        if (is_numeric($text)) $text = (string) $text;
        $text = trim($text);
        return mb_substr($text, 0, 255);
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

    // --- ฟังก์ชันจัดกลุ่ม Dropdown (เหมือนเดิม) ---
    private function mapStatus($val)
    {
        if (!$val) return 'ใช้งาน';
        $val = strtolower(trim($val));
        if (in_array($val, ['cancel', 'lost', 'broken', 'inactive', 'ยกเลิก', 'เสีย', 'ชำรุด'])) {
            return 'ยกเลิก';
        }
        return 'ใช้งาน';
    }

    private function mapEquipType($val)
    {
        if (!$val) return 'Working';
        $val = strtolower(trim($val));
        if (str_contains($val, 'master') || str_contains($val, 'std') || $val === 'm') {
            return 'Master';
        }
        return 'Working';
    }

    private function mapPlaceCal($val)
    {
        if (!$val) return 'Internal';
        $val = strtolower(trim($val));
        if (str_contains($val, 'ex') || str_contains($val, 'out') || str_contains($val, 'vendor') || $val === 'ภายนอก') {
            return 'External';
        }
        return 'Internal';
    }

    public function run()
    {
        // 1. ดึงข้อมูลจาก DataRecode
        $oldInstruments = DB::table('DataRecord')->get(); 

        $batchData = [];
        $batchSize = 100;

        foreach ($oldInstruments as $oldRow) {
            
            // ถ้าไม่มี CodeNo ข้าม (Key หลักต้องมี)
            if (empty($oldRow->CodeNo)) continue;

            // ---------------------------------------------------------
            // 2. ปรับปรุง Logic การหา Type ID (ตามข้อมูลใหม่ของคุณ)
            // ---------------------------------------------------------
            // ใช้ฟิลด์ 'Name' ของ DataRecode ไปเทียบกับ 'code_type' ของ tool_types
            $toolTypeId = null;
            if (!empty($oldRow->Name)) {
                $typeObj = DB::table('tool_types')
                             ->where('code_type', trim($oldRow->Name)) // <--- แก้ตรงนี้ครับ ใช้ Name แทน Type
                             ->select('id')
                             ->first();
                if ($typeObj) $toolTypeId = $typeObj->id;
            }

            // จัดการราคา
            $price = 0;
            if (!empty($oldRow->Price)) {
                $price = (float) str_replace(',', '', $oldRow->Price);
            }

            // รวม Criteria
            $criteriaSpec = $this->cleanText($oldRow->Criteria_1 ?? null);
            if (!empty($oldRow->Criteria1_1)) {
                $criteriaSpec .= ' / ' . $this->cleanText($oldRow->Criteria1_1);
            }

            // 3. เตรียมข้อมูล
            $batchData[] = [
                'code_no'      => $this->cleanText($oldRow->CodeNo),
                'tool_type_id' => $toolTypeId,
                
                // เก็บ Name เดิมลงไปด้วย (แม้จะเป็น CodeType) เพื่อรักษาข้อมูลต้นฉบับ
                'name'         => $this->cleanText($oldRow->Name),
                
                'serial_no'    => $this->cleanText($oldRow->Serial),
                'brand'        => $this->cleanText($oldRow->Brand),
                'asset_no'     => $this->cleanText($oldRow->AssetNo),
                
                // ใช้ Mapping Function
                'equip_type'   => $this->mapEquipType($oldRow->EquipType),
                'maker'        => $this->cleanText($oldRow->NameMakerB),
                
                'owner_id'     => $this->cleanText($oldRow->IDPers),
                'owner_name'   => $this->cleanText($oldRow->Personal),
                'department'   => $this->cleanText($oldRow->Department),
                'machine_name' => $this->cleanText($oldRow->Machine),
                
                'cal_freq_months' => (int) ($oldRow->FeqCAL ?? 12),
                'receive_date'  => $this->parseDate($oldRow->RecieveDate),
                'next_cal_date' => $this->parseDate($oldRow->ExpireDate),
                
                // ใช้ Mapping Function
                'cal_place'     => $this->mapPlaceCal($oldRow->PlaceCAL),
                
                'range_spec'    => $this->cleanText($oldRow->Range),
                'percent_adj'   => $this->cleanText($oldRow->PercentAdj),
                'criteria_specific' => $criteriaSpec,
                'reference_doc' => $this->cleanText($oldRow->Reference),

                // ใช้ Mapping Function
                'status'        => $this->mapStatus($oldRow->Status),
                
                'price'         => $price,
                'remark'        => $this->cleanText($oldRow->Remark),
                
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            // Batch Insert
            if (count($batchData) >= $batchSize) {
                DB::table('instruments')->insert($batchData);
                $batchData = [];
            }
        }

        if (!empty($batchData)) {
            DB::table('instruments')->insert($batchData);
        }
    }
}