<?php

namespace App\Filament\Resources\CalibrationRecordResource\Pages;

use App\Filament\Resources\CalibrationRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Helpers\DashboardCacheHelper;

class CreateCalibrationRecord extends CreateRecord
{
    protected static string $resource = CalibrationRecordResource::class;

    /**
     * 🔥 เปลี่ยนหัวข้อหน้าตาม type ที่เลือก
     */
    public ?string $type = null;

    public function mount(): void
    {
        $this->type = request()->query('type');
        parent::mount();
    }

    /**
     * 🔥 Load specs after form is filled with default values
     * This runs AFTER the form has been hydrated with default() values
     */
    protected function afterFill(): void
    {
        $instrumentId = $this->data['instrument_id'] ?? request()->query('instrument_id');
        
        if ($instrumentId) {
            // Use the same logic as onInstrumentSelected
            $instrument = \App\Models\Instrument::with('toolType', 'department')->find($instrumentId);
            if ($instrument) {
                // Set display info
                $this->data['instrument_name'] = $instrument->toolType?->name ?? '-';
                $this->data['instrument_size'] = $instrument->toolType?->size ?? '-';
                $this->data['instrument_brand'] = $instrument->brand ?? '-';
                $this->data['instrument_department'] = $instrument->department?->name ?? '-';
                $this->data['instrument_serial'] = $instrument->serial_no ?? '-';
                $this->data['instrument_drawing'] = $instrument->toolType?->drawing_no ?? '-';
                $this->data['instrument_machine'] = $instrument->machine_name ?? '-';
                $this->data['next_cal_date'] = now()->addMonths($instrument->cal_freq_months ?? 6);
                
                // Load criteria
                $criteriaUnit = $instrument->criteria_unit ?? [];
                $criteria1 = '0.00'; $criteria2 = '-0.00'; $unit = 'mm.';
                if (is_array($criteriaUnit)) {
                    foreach ($criteriaUnit as $item) {
                        if (($item['index'] ?? 0) == 1) {
                            $criteria1 = $item['criteria_1'] ?? '0.00';
                            $criteria2 = $item['criteria_2'] ?? '-0.00';
                            $unit = $item['unit'] ?? 'mm.';
                            break;
                        }
                    }
                }
                $this->data['criteria_1'] = $criteria1;
                $this->data['criteria_2'] = $criteria2;
                $this->data['criteria_unit'] = $unit;
                
                // 🔥 Load dimension specs into readings
                $this->loadDimensionSpecs($instrument);
            }
        }
    }

    /**
     * 🔥 Load dimension specs from instrument's toolType
     */
    protected function loadDimensionSpecs($instrument): void
    {
        if (!$instrument->toolType || !$instrument->toolType->dimension_specs) return;
        
        $dimensionSpecs = $instrument->toolType->dimension_specs;
        $readings = []; $readingsInner = []; $readingsDepth = []; $readingsParallelism = [];

        foreach ($dimensionSpecs as $spec) {
            $point = $spec['point'] ?? null;
            if (!$point) continue;
            
            $csValue = 0; $sSpecs = [];
            if (isset($spec['specs']) && is_array($spec['specs'])) {
                foreach ($spec['specs'] as $specItem) {
                    $label = $specItem['label'] ?? '';
                    if ($label === 'S') {
                        $sSpecs[] = ['label' => 'S', 's_value' => $specItem['s_std'] ?? null, 
                            'measurements' => array_fill(0, 4, ['value' => null]), 'average' => null, 'sd' => null];
                    } elseif ($label === 'Cs') {
                        $csValue = $specItem['cs_std'] ?? 0;
                    }
                }
            }
            
            if (!empty($sSpecs)) {
                $readings[] = ['point' => $point, 'cs_value' => $csValue, 'specs' => $sSpecs];
                
                $sSpecsInner = array_map(fn($s) => ['label' => 'S', 's_value' => $s['s_value'], 
                    'measurements' => [['value' => null], ['value' => null]], 'average' => null, 'sd' => null], $sSpecs);
                $readingsInner[] = ['point' => $point, 'cs_value' => $csValue, 'specs' => $sSpecsInner];
                
                $sSpecsDepth = array_map(fn($s) => ['label' => 'S', 's_value' => $s['s_value'], 
                    'measurements' => [['value' => null], ['value' => null]], 'average' => null, 'sd' => null], $sSpecs);
                $readingsDepth[] = ['point' => $point, 'cs_value' => $csValue, 'specs' => $sSpecsDepth];
                
                foreach ($sSpecs as $sSpec) {
                    $readingsParallelism[] = ['point' => $point, 's_value' => $sSpec['s_value'],
                        'position_start' => null, 'position_middle' => null, 'position_end' => null,
                        'parallelism' => null, 'Judgement' => null, 'level' => null];
                }
            }
        }

        // Set the readings data
        $this->data['calibration_data']['readings'] = $readings;
        $this->data['calibration_data']['readings_inner'] = $readingsInner;
        $this->data['calibration_data']['readings_depth'] = $readingsDepth;
        $this->data['calibration_data']['readings_parallelism'] = $readingsParallelism;
        
        // 🔥 Fallback: ถ้าไม่มี Specs เลย ให้สร้าง Default Points
        if (empty($readings)) {
            $toolTypeName = $instrument->toolType->name ?? '';
            $isVernierCaliper = stripos($toolTypeName, 'Vernier') !== false && stripos($toolTypeName, 'Caliper') !== false;
            $isVernierDigital = stripos($toolTypeName, 'Digital') !== false;
            $isMicrometer = stripos($toolTypeName, 'Micro') !== false;
            $isDial = stripos($toolTypeName, 'Dial') !== false;
            
            if ($isVernierCaliper || $isVernierDigital) {
                $points = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $innerPoints = ['A', 'B', 'C'];
                
                $fallbackReadings = [];
                foreach ($points as $p) {
                    $fallbackReadings[] = [
                        'point' => $p, 'cs_value' => null, 
                        'specs' => [[
                            'label' => 'S', 's_value' => null, 
                            'measurements' => array_fill(0, 4, ['value' => null]), 
                            'average' => null, 'sd' => null
                        ]]
                    ];
                }
                
                $fallbackInner = [];
                foreach ($innerPoints as $p) {
                    $fallbackInner[] = [
                        'point' => $p, 'cs_value' => null,
                        'specs' => [[
                            'label' => 'S', 's_value' => null,
                            'measurements' => [['value' => null], ['value' => null]],
                            'average' => null, 'sd' => null
                        ]]
                    ];
                }
                
                $this->data['calibration_data']['readings'] = $fallbackReadings;
                $this->data['calibration_data']['readings_inner'] = $fallbackInner;
                $this->data['calibration_data']['readings_depth'] = $fallbackInner;
            }
            elseif ($isMicrometer || $isDial || stripos($toolTypeName, 'Vernier') !== false) {
                 $points = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
                 $fallbackReadings = [];
                 foreach ($points as $p) {
                    $fallbackReadings[] = [
                        'point' => $p, 'cs_value' => null, 
                        'specs' => [[
                            'label' => 'S', 's_value' => null, 
                            'measurements' => array_fill(0, 4, ['value' => null]), 
                            'average' => null, 'sd' => null
                        ]]
                    ];
                }
                $this->data['calibration_data']['readings'] = $fallbackReadings;
            }
        }
    }

    /**
     * 🔥 เปลี่ยนหัวข้อหน้าตาม type ที่เลือก
     */
    public function getTitle(): string
    {
        $type = $this->type ?? request()->get('type', 'instrument');
        
        // Map type to display name
        $typeLabels = [
            'vernier_caliper' => 'Vernier Caliper',
            'vernier_digital' => 'Vernier Digital',
            'vernier_special' => 'Vernier Special',
            'depth_vernier' => 'Depth Vernier',
            'vernier_hight_gauge' => 'Vernier Height Gauge',
            'dial_vernier_hight_gauge' => 'Dial Vernier Height Gauge',
            'micro_meter' => 'Micro Meter',
            'dial_caliper' => 'Dial Caliper',
            'dial_indicator' => 'Dial Indicator',
            'dial_test_indicator' => 'Dial Test Indicator',
            'thickness_gauge' => 'Thickness Gauge',
            'thickness_caliper' => 'Thickness Caliper',
            'cylinder_gauge' => 'Cylinder Gauge',
            'chamfer_gauge' => 'Chamfer Gauge',
            'pressure_gauge' => 'Pressure Gauge',
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

    /**
     * 🔥 รักษา calibration_type จาก form data (Hidden field)
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // calibration_type ถูก set ใน Hidden field แล้ว
        // ใช้ค่าจาก form data แทน request parameter
        // เพราะ request()->get('type') อาจหายไปตอน submit form
        
        // ถ้าไม่มี calibration_type ให้ fallback เป็น VernierOther
        if (!isset($data['calibration_data']['calibration_type']) || empty($data['calibration_data']['calibration_type'])) {
            $data['calibration_data']['calibration_type'] = 'VernierOther';
        }

        // 🔥 Save Current User as Calibrator (ID)
        if (auth()->check()) {
            $data['cal_by'] = auth()->id();
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // 🔥 Clear Dashboard Cache
        DashboardCacheHelper::clearDashboardCache();
    }
}