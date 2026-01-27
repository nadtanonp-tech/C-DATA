<?php

namespace App\Filament\Resources\GaugeCalibrationResource\Pages;

use App\Filament\Resources\GaugeCalibrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Helpers\DashboardCacheHelper;

class EditGaugeCalibration extends EditRecord
{
    protected static string $resource = GaugeCalibrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Sync calibration_type from calibration_data
        if (isset($data['calibration_data']['calibration_type'])) {
            $data['calibration_type'] = $data['calibration_data']['calibration_type'];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure calibration_type is synced
        if (isset($data['calibration_data']['calibration_type'])) {
            $data['calibration_type'] = $data['calibration_data']['calibration_type'];
        }

        return $data;
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function afterSave(): void
    {
        // 🔥 Clear Dashboard Cache
        DashboardCacheHelper::clearDashboardCache();
    }

    protected function afterDelete(): void
    {
        // 🔥 Clear Dashboard Cache
        DashboardCacheHelper::clearDashboardCache();
    }
}
