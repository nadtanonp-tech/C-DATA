<?php

namespace App\Filament\Resources\CalibrationKNewResource\Pages;

use App\Filament\Resources\CalibrationKNewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationKNews extends ListRecords
{
    protected static string $resource = CalibrationKNewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
