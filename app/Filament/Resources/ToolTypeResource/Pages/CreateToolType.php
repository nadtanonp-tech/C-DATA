<?php

namespace App\Filament\Resources\ToolTypeResource\Pages;

use App\Filament\Resources\ToolTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateToolType extends CreateRecord
{
    protected static string $resource = ToolTypeResource::class;

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

        return $data;
    }
}
