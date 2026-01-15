<?php

namespace App\Filament\Resources\CalibrationSnapGaugeResource\Pages;

use App\Filament\Resources\CalibrationSnapGaugeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCalibrationSnapGauge extends CreateRecord
{
    protected static string $resource = CalibrationSnapGaugeResource::class;
    
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
     * 🔥 Redirect ไปหน้า View หลัง create สำเร็จ
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
