<?php

namespace App\Filament\Resources\CalibrationRecordResource\Pages;

use App\Filament\Resources\CalibrationRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCalibrationRecord extends CreateRecord
{
    protected static string $resource = CalibrationRecordResource::class;

    /**
     * 🔥 Redirect ไปหน้า View หลัง create สำเร็จ
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * 🔥 เพิ่ม calibration_type ก่อนบันทึก
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // เพิ่ม calibration_type ใน calibration_data
        if (isset($data['calibration_data'])) {
            $data['calibration_data']['calibration_type'] = 'VernierCaliperDigital';
        } else {
            $data['calibration_data'] = [
                'calibration_type' => 'VernierCaliperDigital',
            ];
        }
        
        return $data;
    }
}