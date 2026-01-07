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
     * 🔥 รักษา calibration_type จาก form data (Hidden field)
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // calibration_type ถูก set ใน Hidden field แล้ว
        // ใช้ค่าจาก form data แทน request parameter
        // เพราะ request()->get('type') อาจหายไปตอน submit form
        
        // ถ้าไม่มี calibration_type ให้ fallback เป็น VernierOther
        if (!isset($data['calibration_data']['calibration_type']) || empty($data['calibration_data']['calibration_type'])) {
            $data['calibration_data']['calibration_type'] = 'VernierOther';
        }
        
        return $data;
    }
}