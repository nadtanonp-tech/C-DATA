<?php

namespace App\Filament\Resources\ToolTypeResource\Pages;

use App\Filament\Resources\ToolTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditToolType extends EditRecord
{
    protected static string $resource = ToolTypeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $specs = $data['dimension_specs'] ?? [];

        // เช็คว่าเป็น Vernier Caliper หรือไม่ (มี S และ Cs)
        $firstPointSpecs = $specs[0]['specs'] ?? [];
        $firstPointLabel = $firstPointSpecs[0]['label'] ?? '';
        
        if ($firstPointLabel === 'S' || collect($firstPointSpecs)->pluck('label')->contains('S')) {
            $data['is_new_instruments_type'] = 1;
            return $data;
        }

        // เช็คว่าเป็น Snap / Plug Gauge (มี GO/NOGO)
        $firstPointName = $specs[0]['point'] ?? '';
        if (str_contains($firstPointName, '(GO)')) {
            // ถือว่าเป็นกลุ่ม Snap/Plug Gauge (UI เหมือนกัน)
            $data['is_snap_gauge'] = 1; 
            return $data;
        }

        // เช็คว่าเป็น K-Gauge (Point A, B และมีแค่ STD)
        // หรือ Thread Plug (Major, Pitch)
        if ($firstPointName === 'A') {
            if ($firstPointLabel === 'STD') {
                $data['is_kgauge'] = 1;
                return $data;
            }
            if ($firstPointLabel === 'Major' || $firstPointLabel === 'วัดเกลียว' || $firstPointLabel === 'Pitch') {
                $data['is_thread_plug_gauge'] = 1; // เหมากลุ่ม Thread/Serration ไว้ด้วยกันเพราะ UI คล้ายกัน
                return $data;
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // อัปเดต JSON สำหรับ criteria_unit
        // โหลดของเดิมมาก่อน (ถ้ามี)
        $existing = $this->record->criteria_unit ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }

        // เราจะอัปเดต index 1 (หรือสร้างใหม่ถ้ายังไม่มี)
        // กรณี user แก้ไขข้อมูล
        $found = false;
        foreach ($existing as $key => $item) {
            if (($item['index'] ?? 0) == 1) {
                // 🔥 บันทึก range ลง JSON
                $existing[$key]['range'] = $data['range'] ?? ($existing[$key]['range'] ?? null); 
                $existing[$key]['criteria_1'] = $data['criteria_1'] ?? $existing[$key]['criteria_1'];
                $existing[$key]['criteria_2'] = $data['criteria_2'] ?? $existing[$key]['criteria_2'];
                $existing[$key]['unit'] = $data['criteria_unit_selection'] ?? $existing[$key]['unit'];
                $found = true;
                break;
            }
        }

        if (!$found) {
            // ถ้าไม่เจอ index 1 ให้เพิ่มใหม่
             $existing[] = [
                'index' => 1,
                'range' => $data['range'] ?? null, // 🔥 บันทึก range
                'criteria_1' => $data['criteria_1'] ?? '0.00',
                'criteria_2' => $data['criteria_2'] ?? '-0.00',
                'unit' => $data['criteria_unit_selection'] ?? '%F.S',
            ];
        }

        $data['criteria_unit'] = $existing;

        // ลบ field virtual ออก
        unset($data['range']); // 🔥 อย่าลืมลบออก เพราะไม่มี column นี้จริง
        unset($data['criteria_1']);
        unset($data['criteria_2']);
        unset($data['criteria_unit_selection']);

        return $data;
    }
}
