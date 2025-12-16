<?php

namespace App\Filament\Resources\CalibrationRecordResource\Pages;

use App\Filament\Resources\CalibrationRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationRecords extends ListRecords
{
    protected static string $resource = CalibrationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}   