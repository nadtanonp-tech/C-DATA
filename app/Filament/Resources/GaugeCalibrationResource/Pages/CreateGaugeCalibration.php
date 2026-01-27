<?php

namespace App\Filament\Resources\GaugeCalibrationResource\Pages;

use App\Filament\Resources\GaugeCalibrationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Helpers\DashboardCacheHelper;

class CreateGaugeCalibration extends CreateRecord
{
    protected static string $resource = GaugeCalibrationResource::class;
    
    public ?string $type = null;

    public function mount(): void
    {
        $this->type = request()->query('type');
        parent::mount();
    }

    public function getTitle(): string
    {
        $type = $this->type ?? request()->get('type', 'instrument');
        
        // Map type to display name
        $typeLabels = [
            'KGauge' => 'K-Gauge',
            'SnapGauge' => 'Snap Gauge',
            'PlugGauge' => 'Plug Gauge',
            'ThreadPlugGauge' => 'Thread Plug Gauge',
            'SerrationPlugGauge' => 'Serration Plug Gauge',
            'ThreadRingGauge' => 'Thread Ring Gauge',
            'SerrationRingGauge' => 'Serration Ring Gauge',
            'ThreadPlugGaugeFitWear' => 'Plug Gauge Fit Wear',
        ];
        
        $label = $typeLabels[$type] ?? 'Instrument';
        
        return "Create {$label} Calibration";
    }
    
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure calibration_type is set
        if (empty($data['calibration_type'])) {
            $data['calibration_type'] = 'KGauge';
        }

        // If calibration_data exists and has calibration_type, sync it
        if (isset($data['calibration_data']['calibration_type'])) {
            $data['calibration_type'] = $data['calibration_data']['calibration_type'];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // 🔥 Clear Dashboard Cache
        DashboardCacheHelper::clearDashboardCache();
    }
}
