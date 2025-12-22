<?php

namespace App\Filament\Resources\CalibrationSnapGaugeResource\Pages;

use App\Filament\Resources\CalibrationSnapGaugeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCalibrationSnapGauge extends EditRecord
{
    protected static string $resource = CalibrationSnapGaugeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * 🔥 Mutate ข้อมูลก่อนแสดงในฟอร์ม (สำหรับ Edit/View)
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
            }
        }

        // calibration_data จะถูก cast เป็น array โดยอัตโนมัติจาก Model
        // ไม่ต้องทำอะไรเพิ่ม เพราะ Model มี protected $casts = ['calibration_data' => 'array'];
        
        return $data;
    }
}
