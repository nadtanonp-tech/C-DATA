<?php

namespace App\Filament\Resources\CalibrationThreadPlugGaugeFitWearResource\Pages;

use App\Filament\Resources\CalibrationThreadPlugGaugeFitWearResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCalibrationThreadPlugGaugeFitWear extends CreateRecord
{
    protected static string $resource = CalibrationThreadPlugGaugeFitWearResource::class;
    
    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Save');
    }
    
    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Save & save another');
    }

    /**
     * 🔥 Mutate data ก่อน save เพื่อให้แน่ใจว่า calibration_type ถูกตั้งค่า
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure calibration_type is set
        if (!isset($data['calibration_data'])) {
            $data['calibration_data'] = [];
        }
        $data['calibration_data']['calibration_type'] = 'ThreadPlugGaugeFitWear';
        
        return $data;
    }

    /**
     * 🔥 Redirect ไปหน้า View หลัง create สำเร็จ
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
