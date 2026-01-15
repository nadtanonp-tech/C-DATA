<?php

namespace App\Filament\Resources\CalibrationPlugGaugeResource\Pages;

use App\Filament\Resources\CalibrationPlugGaugeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCalibrationPlugGauge extends CreateRecord
{
    protected static string $resource = CalibrationPlugGaugeResource::class;
    
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
