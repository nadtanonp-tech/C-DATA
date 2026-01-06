<?php

namespace App\Filament\Resources\CalibrationThreadPlugGaugeFitWearResource\Pages;

use App\Filament\Resources\CalibrationThreadPlugGaugeFitWearResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCalibrationThreadPlugGaugeFitWear extends ViewRecord
{
    protected static string $resource = CalibrationThreadPlugGaugeFitWearResource::class;

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
            }
        }

        // calibration_data จะถูก cast เป็น array โดยอัตโนมัติจาก Model
        
        return $data;
    }
}
