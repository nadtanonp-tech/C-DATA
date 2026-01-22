<?php

namespace App\Livewire;

use Livewire\Component;
use App\Filament\Resources\CalibrationRecordResource;
use App\Filament\Resources\GaugeCalibrationResource;
use App\Filament\Resources\ExternalCalResultResource;
class QuickCreateMenu extends Component
{
    public function getMenuItems(): array
    {
        return [
            [
                'label' => 'สอบเทียบภายใน (Internal)',
                'icon' => 'heroicon-o-rectangle-stack',
                'items' => [
                    [
                        'label' => 'K-Gauge',
                        'url' => GaugeCalibrationResource::getUrl('create', ['type' => 'KGauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Snap Gauge',
                        'url' => GaugeCalibrationResource::getUrl('create', ['type' => 'SnapGauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Plug Gauge',
                        'url' => GaugeCalibrationResource::getUrl('create', ['type' => 'PlugGauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Thread Plug Gauge',
                        'url' => GaugeCalibrationResource::getUrl('create', ['type' => 'ThreadPlugGauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Serration Plug Gauge',
                        'url' => GaugeCalibrationResource::getUrl('create', ['type' => 'SerrationPlugGauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Thread Ring Gauge',
                        'url' => GaugeCalibrationResource::getUrl('create', ['type' => 'ThreadRingGauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Serration Ring Gauge',
                        'url' => GaugeCalibrationResource::getUrl('create', ['type' => 'SerrationRingGauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Plug Gauge (Fit/Wear)',
                        'url' => GaugeCalibrationResource::getUrl('create', ['type' => 'ThreadPlugGaugeFitWear']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Vernier Caliper',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'vernier_caliper']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Vernier Digital',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'vernier_digital']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Vernier Special',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'vernier_special']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Depth Vernier',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'depth_vernier']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Vernier Hight Gauge',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'vernier_hight_gauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Dial Vernier Hight Gauge',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'dial_vernier_hight_gauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Micro Meter',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'micro_meter']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Dial Caliper',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'dial_caliper']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Dial Indicator',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'dial_indicator']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Dial Test Indicator',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'dial_test_indicator']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Thickness Gauge',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'thickness_gauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Thickness Caliper',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'thickness_caliper']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Cylinder Gauge',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'cylinder_gauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Chamfer Gauge',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'chamfer_gauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                    [
                        'label' => 'Pressure Gauge',
                        'url' => CalibrationRecordResource::getUrl('create', ['type' => 'pressure_gauge']),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                ],
            ],
            [
                'label' => 'สอบเทียบภายนอก (External)',
                'icon' => 'heroicon-o-rectangle-stack',
                'items' => [
                    [
                        'label' => 'External Calibration',
                        'url' => ExternalCalResultResource::getUrl('create'),
                        'icon' => 'heroicon-o-plus-circle',
                    ],
                ],
            ],
        ];
    }

    public function render()
    {
        return view('livewire.quick-create-menu', [
            'menuItems' => $this->getMenuItems(),
        ]);
    }
}
