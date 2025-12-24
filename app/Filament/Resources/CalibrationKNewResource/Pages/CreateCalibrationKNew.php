<?php

namespace App\Filament\Resources\CalibrationKNewResource\Pages;

use App\Filament\Resources\CalibrationKNewResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCalibrationKNew extends CreateRecord
{
    protected static string $resource = CalibrationKNewResource::class;

    /**
     * 🔥 Redirect ไปหน้า View หลัง create สำเร็จ
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
