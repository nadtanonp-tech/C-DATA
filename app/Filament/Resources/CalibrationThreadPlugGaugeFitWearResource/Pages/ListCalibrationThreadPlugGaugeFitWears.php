<?php

namespace App\Filament\Resources\CalibrationThreadPlugGaugeFitWearResource\Pages;

use App\Filament\Resources\CalibrationThreadPlugGaugeFitWearResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationThreadPlugGaugeFitWears extends ListRecords
{
    protected static string $resource = CalibrationThreadPlugGaugeFitWearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('New Thread Plug Gauge Fit Wear Calibration')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('primary'),
        ];
    }
}
