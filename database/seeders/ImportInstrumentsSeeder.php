<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Department;

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

    // --- Helper Functions ---
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
        $oldInstruments = DB::table('DataRecord')->get(); 

        foreach ($oldInstruments as $oldRow) {
            
            if (empty($oldRow->CodeNo)) continue;

            $codeNo = $this->cleanText($oldRow->CodeNo);

            // --- 2. หา Type ID ---
            $toolTypeId = null;
            if (!empty($oldRow->Name)) { 
                $typeObj = DB::table('tool_types')
                             ->where('code_type', trim($oldRow->Name))
                             ->select('id')
                             ->first();
                if ($typeObj) $toolTypeId = $typeObj->id;
            }

            // --- 3. จัดการแผนก (Department) ---
            $deptId = null;
            $deptName = $this->cleanText($oldRow->Department);
            if (!empty($deptName)) {
                $dept = Department::firstOrCreate(['name' => $deptName]);
                $deptId = $dept->id;
            }

            // --- 4. จัดการราคา ---
            $price = 0;
            if (!empty($oldRow->Price)) {
                $price = (float) str_replace(',', '', $oldRow->Price);
            }

            // --- 5. บันทึกหรืออัปเดต (updateOrInsert) ---
            // ข้อดี: รันซ้ำกี่รอบก็ได้ ข้อมูลไม่พัง
            DB::table('instruments')->updateOrInsert(
                ['code_no' => $codeNo], // เงื่อนไขการเช็ค (ถ้าเจอ Code นี้)
                [
                    // ข้อมูลที่จะบันทึก/อัปเดต
                    'tool_type_id'    => $toolTypeId,
                    'name'            => $this->cleanText($oldRow->Name), // คุณแก้เป็น Name แล้วถูกต้องครับ
                    'serial_no'       => $this->cleanText($oldRow->Serial),
                    'brand'           => $this->cleanText($oldRow->Brand),
                    'asset_no'        => $this->cleanText($oldRow->AssetNo),
                    'equip_type'      => $this->mapEquipType($oldRow->EquipType),
                    'maker'           => $this->cleanText($oldRow->NameMakerB),
                    'owner_id'        => $this->cleanText($oldRow->IDPers),
                    'owner_name'      => $this->cleanText($oldRow->Personal),
                    'department_id'   => $deptId,
                    'machine_name'    => $this->cleanText($oldRow->Machine),
                    'cal_freq_months' => (int) ($oldRow->FeqCAL ?? 12),
                    'receive_date'    => $this->parseDate($oldRow->RecieveDate),
                    'next_cal_date'     => null, // ปล่อยว่างไว้ก่อน (เพราะ ExpireDate ของเก่าคือวันยกเลิก)
                    'cancellation_date' => $this->parseDate($oldRow->ExpireDate), // ย้ายมาลงช่องนี้แทน
                    'cal_place'       => $this->mapPlaceCal($oldRow->PlaceCAL),
                    'range_spec'      => $this->cleanText($oldRow->Range),
                    'percent_adj'     => $this->cleanText($oldRow->PercentAdj),
                    'criteria_1'      => isset($oldRow->Criteria_1) ? (float) $oldRow->Criteria_1 : null,
                    'criteria_2'      => isset($oldRow->Criteria1_1) ? (float) $oldRow->Criteria1_1 : null,
                    'reference_doc'   => $this->cleanText($oldRow->Reference),
                    'status'          => $this->mapStatus($oldRow->Status),
                    'price'           => $price,
                    'remark'          => $this->cleanText($oldRow->Remark),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }
        // หมายเหตุ: เมื่อใช้ updateOrInsert เราจะไม่ใช้ Batch Insert ($batchData) นะครับ
        // เพราะมันต้องเช็คทีละแถว ทำให้ช้ากว่านิดหน่อย แต่ปลอดภัยกว่าครับ
    }
}