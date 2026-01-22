<?php

namespace App\Filament\Resources\GaugeCalibrationResource\Pages;

use App\Filament\Resources\GaugeCalibrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGaugeCalibration extends ViewRecord
{
    protected static string $resource = GaugeCalibrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->color('warning'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure calibration_type is loaded for display
        if (isset($data['calibration_data']['calibration_type'])) {
            $data['calibration_type'] = $data['calibration_data']['calibration_type'];
        }

        return $data;
    }
}
