<?php

namespace App\Filament\Resources\CalibrationRecordResource\Pages;

use App\Filament\Resources\CalibrationRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCalibrationRecord extends EditRecord
{
    protected static string $resource = CalibrationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * 🔥 Mutate ข้อมูลก่อนแสดงในฟอร์ม (สำหรับ Edit)
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
                
                // โหลด criteria จาก ToolType
                $criteriaUnit = $instrument->toolType?->criteria_unit ?? [];
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

    /**
     * 🔥 รักษา calibration_type เดิมก่อนบันทึก (สำหรับ Edit)
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // calibration_type จะถูกเก็บไว้ใน calibration_data อยู่แล้ว
        // ไม่ต้อง override เพราะจะใช้ค่าเดิมจากข้อมูลที่โหลดมา
        
        return $data;
    }
}