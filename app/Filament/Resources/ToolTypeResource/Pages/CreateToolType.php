<?php

namespace App\Filament\Resources\ToolTypeResource\Pages;

use App\Filament\Resources\ToolTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateToolType extends CreateRecord
{
    protected static string $resource = ToolTypeResource::class;

    /**
     * 🔥 เปลี่ยนหัวข้อหน้าตาม type ที่เลือก
     */
    public function getTitle(): string
    {
        // Map flags to display names
        $typeLabels = [
            'is_kgauge' => 'K-Gauge',
            'is_snap_gauge' => 'Snap Gauge',
            'is_plug_gauge' => 'Plug Gauge',
            'is_thread_plug_gauge' => 'Thread Plug Gauge',
            'is_thread_ring_gauge' => 'Thread Ring Gauge',
            'is_serration_plug_gauge' => 'Serration Plug Gauge',
            'is_serration_ring_gauge' => 'Serration Ring Gauge',
            'is_thread_plug_gauge_for_checking_fit_wear' => 'Thread Plug Gauge (Fit/Wear)',
            'is_serration_plug_gauge_for_checking_fit_wear' => 'Serration Plug Gauge (Fit/Wear)',
            'is_new_instruments_type' => 'New Instrument',
            'is_external_cal_type' => 'External Calibration',
        ];
        
        foreach ($typeLabels as $flag => $label) {
            if (request()->query($flag)) {
                return "Create {$label} Type";
            }
        }
        
        return 'Create Tool Type';
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    public function mount(): void
    {
        parent::mount();

        $data = [];

        // 1. เก็บ State จาก URL ลงใน Hidden Fields (เพื่อให้ Logic ใน ToolTypeResource ทำงานต่อได้หลัง Livewire Update)
        $flags = [
            'is_kgauge',
            'is_snap_gauge',
            'is_plug_gauge',
            'is_thread_plug_gauge',
            'is_thread_ring_gauge',
            'is_serration_plug_gauge',
            'is_serration_ring_gauge',
            'is_thread_plug_gauge_for_checking_fit_wear',
            'is_serration_plug_gauge_for_checking_fit_wear',
            'is_new_instruments_type',
            'is_external_cal_type',
        ];

        foreach ($flags as $flag) {
            if (request()->query($flag)) {
                $data[$flag] = 1;
            }
        }

        // 2. กำหนดค่าเริ่มต้น Dimension Specs ตามประเภท
        if (request()->query('is_snap_gauge')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A(GO)',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'STD', 'min' => null, 'max' => null],
                    ]
                ],
                [
                    'point' => 'B(NOGO)',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'STD', 'min' => null, 'max' => null],
                    ]
                ],
            ];
        } elseif (request()->query('is_kgauge')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'STD', 'min' => null, 'max' => null],
                    ]
                ],
                [
                    'point' => 'B',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'STD', 'min' => null, 'max' => null],
                    ]
                ],
            ];
        } elseif (request()->query('is_plug_gauge')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A(GO)',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'STD', 'min' => null, 'max' => null],
                    ]
                ],
                [
                    'point' => 'B(NOGO)',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'STD', 'min' => null, 'max' => null],
                    ]
                ],
            ];
        } elseif (request()->query('is_thread_plug_gauge')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'Major', 'min' => null, 'max' => null],
                        ['label' => 'Pitch', 'min' => null, 'max' => null],
                    ]
                ],
                [
                    'point' => 'B',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'Major', 'min' => null, 'max' => null],
                        ['label' => 'Pitch', 'min' => null, 'max' => null],
                        ['label' => 'Plug', 'min' => null, 'max' => null],
                    ]
                ],
            ];
        } elseif (request()->query('is_thread_ring_gauge')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'วัดเกลียว', 'standard_value' => null], 
                    ]
                ],
            ];
        } elseif (request()->query('is_serration_plug_gauge')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'Major', 'min' => null, 'max' => null],
                        ['label' => 'Pitch', 'min' => null, 'max' => null],
                    ]
                ],
            ];
        } elseif (request()->query('is_serration_ring_gauge')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'วัดเกลียว', 'standard_value' => null], 
                    ]
                ],
            ];
        } elseif (request()->query('is_thread_plug_gauge_for_checking_fit_wear')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'Major', 'min' => null, 'max' => null],
                        ['label' => 'Pitch', 'min' => null, 'max' => null],
                    ]
                ],
            ];
        } elseif (request()->query('is_serration_ring_gauge_for_checking_fit_wear')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A',
                    'trend' => 'Smaller',
                    'specs' => [
                        ['label' => 'Major', 'min' => null, 'max' => null],
                        ['label' => 'Pitch', 'min' => null, 'max' => null], 
                    ]
                ],
            ];
        } elseif (request()->query('is_new_instruments_type')) {
            $data['dimension_specs'] = [
                [
                    'point' => 'A',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
                [
                    'point' => 'B',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
                [
                    'point' => 'C',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
                [
                    'point' => 'D',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
                [
                    'point' => 'E',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
                [
                    'point' => 'F',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
                [
                    'point' => 'G',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
                [
                    'point' => 'H',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ], 
                [
                    'point' => 'I',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
                [
                    'point' => 'J',
                    'specs' => [
                        ['label' => 'S', 's_value' => null],
                        ['label' => 'Cs', 'cs_value' => null],
                    ]
                ],
            ];
        } elseif (request()->query('is_external_cal_type')) {
            // External Calibration Type - Range 1-5 with label=Criteria, usage, cri_plus, cri_minus, cri_unit
            $data['dimension_specs'] = [
                ['point' => '1', 'specs' => [['label' => 'Criteria', 'usage' => null, 'cri_plus' => null, 'cri_minus' => null, 'cri_unit' => 'mm']]],
                ['point' => '2', 'specs' => [['label' => 'Criteria', 'usage' => null, 'cri_plus' => null, 'cri_minus' => null, 'cri_unit' => 'mm']]],
                ['point' => '3', 'specs' => [['label' => 'Criteria', 'usage' => null, 'cri_plus' => null, 'cri_minus' => null, 'cri_unit' => 'mm']]],
                ['point' => '4', 'specs' => [['label' => 'Criteria', 'usage' => null, 'cri_plus' => null, 'cri_minus' => null, 'cri_unit' => 'mm']]],
                ['point' => '5', 'specs' => [['label' => 'Criteria', 'usage' => null, 'cri_plus' => null, 'cri_minus' => null, 'cri_unit' => 'mm']]],
            ];
        }
        
        $this->form->fill($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // สร้าง JSON สำหรับ criteria_unit
        // ถ้ามีการกรอก criteria_1, criteria_2 หรือหน่วยมา (หรือ range)
        if (isset($data['criteria_1']) || isset($data['criteria_2']) || isset($data['criteria_unit_selection']) || isset($data['range'])) {
            $data['criteria_unit'] = [
                [
                    'index' => 1,
                    'range' => $data['range'] ?? null,   // 🔥 ใส่ range
                    'criteria_1' => $data['criteria_1'] ?? '0.00',
                    'criteria_2' => $data['criteria_2'] ?? '-0.00',
                    'unit' => $data['criteria_unit_selection'] ?? '%F.S',
                ]
            ];
        }

        // ลบ field virtual ออกไป เพื่อไม่ให้ Eloquent พยายาม save ลง column จริง (ซึ่งไม่มี)
        unset($data['range']); // 🔥 ลบออก
        unset($data['criteria_1']);
        unset($data['criteria_2']);
        unset($data['criteria_unit_selection']);

        // 🔥 แปลง cri_minus จากค่าบวกเป็นค่าลบ (ถ้า user ลืมใส่ -)
        if (isset($data['dimension_specs']) && is_array($data['dimension_specs'])) {
            foreach ($data['dimension_specs'] as &$point) {
                if (isset($point['specs']) && is_array($point['specs'])) {
                    foreach ($point['specs'] as &$spec) {
                        if (isset($spec['cri_minus']) && is_numeric($spec['cri_minus']) && (float)$spec['cri_minus'] > 0) {
                            $spec['cri_minus'] = -abs((float)$spec['cri_minus']);
                        }
                    }
                }
            }
            unset($point, $spec); // ลบ reference
        }

        // 🔥 กรอง dimension_specs - ลบ specs ที่ไม่มีข้อมูล และ trend ที่ว่างออก
        if (isset($data['dimension_specs']) && is_array($data['dimension_specs'])) {
            $filteredPoints = [];
            
            foreach ($data['dimension_specs'] as $point) {
                $filteredSpecs = [];
                
                if (isset($point['specs']) && is_array($point['specs'])) {
                    foreach ($point['specs'] as $spec) {
                        $label = $spec['label'] ?? null;
                        
                        // ตรวจสอบว่า spec นี้มีค่าที่มีความหมายหรือไม่
                        $hasValue = false;
                        
                        // สำหรับ STD, Major, Pitch, Plug ต้องมี min หรือ max
                        if (in_array($label, ['STD', 'Major', 'Pitch', 'Plug'])) {
                            $min = $spec['min'] ?? null;
                            $max = $spec['max'] ?? null;
                            // ตรวจสอบว่ามีค่าที่ไม่ใช่ 0/null/ว่าง
                            $hasValue = ($min !== null && $min !== '' && $min !== '0' && $min !== 0 && (float)$min !== 0.0) ||
                                       ($max !== null && $max !== '' && $max !== '0' && $max !== 0 && (float)$max !== 0.0);
                        }
                        // สำหรับ วัดเกลียว ต้องมี standard_value
                        elseif ($label === 'วัดเกลียว') {
                            $stdValue = $spec['standard_value'] ?? null;
                            $hasValue = $stdValue !== null && $stdValue !== '' && $stdValue !== '0' && $stdValue !== 0;
                        }
                        // สำหรับ S ต้องมี s_std
                        elseif ($label === 'S') {
                            $sStd = $spec['s_std'] ?? null;
                            $hasValue = $sStd !== null && $sStd !== '' && $sStd !== '0' && $sStd !== 0 && (float)$sStd !== 0.0;
                        }
                        // สำหรับ Cs ต้องมี cs_std
                        elseif ($label === 'Cs') {
                            $csStd = $spec['cs_std'] ?? null;
                            $hasValue = $csStd !== null && $csStd !== '' && $csStd !== '0' && $csStd !== 0 && (float)$csStd !== 0.0;
                        }
                        // 🔥 สำหรับ External Cal Type (มี cri_plus หรือ cri_minus)
                        elseif (isset($spec['cri_plus']) || isset($spec['cri_minus'])) {
                            $criPlus = $spec['cri_plus'] ?? null;
                            $criMinus = $spec['cri_minus'] ?? null;
                            // ถ้ามี cri_plus หรือ cri_minus ที่ไม่ว่าง → ถือว่ามีค่า
                            $hasValue = ($criPlus !== null && $criPlus !== '') || 
                                       ($criMinus !== null && $criMinus !== '') ||
                                       ($label !== null && $label !== ''); // หรือมี label
                        }
                        
                        // เก็บ spec นี้ถ้ามีค่าที่มีความหมาย
                        if ($hasValue) {
                            // กรองเอาเฉพาะ key ที่มีค่า
                            $filteredSpec = array_filter($spec, function ($value, $key) {
                                if ($key === 'label') return true;
                                if ($value === null || $value === '' || $value === '0' || $value === 0) {
                                    return false;
                                }
                                return true;
                            }, ARRAY_FILTER_USE_BOTH);
                            
                            $filteredSpecs[] = $filteredSpec;
                        }
                    }
                }
                
                // เก็บ point นี้ถ้ามี specs ที่มีค่า
                if (!empty($filteredSpecs)) {
                    $filteredPoint = [
                        'point' => $point['point'] ?? null,
                        'specs' => $filteredSpecs,
                    ];
                    
                    // เก็บ trend เฉพาะถ้ามีค่าที่ไม่ว่าง/null
                    $trend = $point['trend'] ?? null;
                    if ($trend !== null && $trend !== '' && $trend !== '0' && $trend !== 0) {
                        $filteredPoint['trend'] = $trend;
                    }
                    
                    $filteredPoints[] = $filteredPoint;
                }
            }
            
            $data['dimension_specs'] = $filteredPoints;
        }

        return $data;
    }
}
