<?php

namespace App\Filament\Resources\CalibrationRecordResource\Pages;

use App\Filament\Resources\CalibrationRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCalibrationRecord extends ViewRecord
{
    protected static string $resource = CalibrationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    /**
     * 🔥 Mutate ข้อมูลก่อนแสดงในฟอร์ม (สำหรับ View)
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // โหลดข้อมูล Instrument มาแสดงในฟิลด์ Preview
        if (isset($data['instrument_id'])) {
            $instrument = \App\Models\Instrument::with('toolType', 'department')->find($data['instrument_id']);
            
            if ($instrument) {
                $data['instrument_name'] = $instrument->toolType?->name ?? '-';
                $data['instrument_size'] = $instrument->toolType?->size ?? '-';
                $data['instrument_department'] = $instrument->department?->name ?? '-';
                $data['instrument_serial'] = $instrument->serial_no ?? '-';
                $data['instrument_drawing'] = $instrument->toolType?->drawing_no ?? '-';
                
                // 🔥 โหลด criteria จาก Instrument แทน ToolType
                $criteriaUnit = $instrument->criteria_unit ?? [];
                if (is_array($criteriaUnit)) {
                    foreach ($criteriaUnit as $item) {
                        if (($item['index'] ?? 0) == 1) {
                            $data['criteria_1'] = $item['criteria_1'] ?? null;
                            $data['criteria_2'] = $item['criteria_2'] ?? null;
                            $data['criteria_unit'] = $item['unit'] ?? 'mm.';
                            break;
                        }
                    }
                }
            }
        }

        // calibration_data จะถูก cast เป็น array โดยอัตโนมัติจาก Model
        // ไม่ต้องทำอะไรเพิ่ม เพราะ Model มี protected $casts = ['calibration_data' => 'array'];
        
        return $data;
    }
}
