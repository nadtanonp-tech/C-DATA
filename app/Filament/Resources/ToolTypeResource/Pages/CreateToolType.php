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

        if (request()->query('is_snap_gauge')) {
            $this->form->fill([
                'dimension_specs' => [
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
                ],
            ]);
        } elseif (request()->query('is_kgauge')) {
            $this->form->fill([
                'dimension_specs' => [
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
                ],
            ]);
        } elseif (request()->query('is_plug_gauge')) {
            $this->form->fill([
                'dimension_specs' => [
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
                ],
            ]);
        } elseif (request()->query('is_thread_plug_gauge')) {
            $this->form->fill([
                'dimension_specs' => [
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
                ],
            ]);
        } elseif (request()->query('is_thread_ring_gauge')) {
            $this->form->fill([
                'dimension_specs' => [
                    [
                        'point' => 'A',
                        'trend' => 'Smaller',
                        'specs' => [
                            ['label' => 'วัดเกลียว', 'min' => null, 'max' => null], 
                        ]
                    ],
                ],
            ]);
        } elseif (request()->query('is_serration_plug_gauge')) {
            $this->form->fill([
                'dimension_specs' => [
                    [
                        'point' => 'A',
                        'trend' => 'Smaller',
                        'specs' => [
                            ['label' => 'Major', 'min' => null, 'max' => null],
                            ['label' => 'Pitch', 'min' => null, 'max' => null],
                        ]
                    ],
                ],
            ]);
        } elseif (request()->query('is_serration_ring_gauge')) {
            $this->form->fill([
                'dimension_specs' => [
                    [
                        'point' => 'A',
                        'trend' => 'Smaller',
                        'specs' => [
                            ['label' => 'วัดเกลียว', 'min' => null, 'max' => null], 
                        ]
                    ],
                ],
            ]);
        } elseif (request()->query('is_thread_plug_gauge_for_checking_fit_wear')) {
            $this->form->fill([
                'dimension_specs' => [
                    [
                        'point' => 'A',
                        'trend' => 'Smaller',
                        'specs' => [
                            ['label' => 'Major', 'min' => null, 'max' => null],
                            ['label' => 'Pitch', 'min' => null, 'max' => null],
                        ]
                    ],
                ],
            ]);
        } elseif (request()->query('is_serration_ring_gauge_for_checking_fit_wear')) {
            $this->form->fill([
                'dimension_specs' => [
                    [
                        'point' => 'A',
                        'trend' => 'Smaller',
                        'specs' => [
                            ['label' => 'Major', 'min' => null, 'max' => null],
                            ['label' => 'Pitch', 'min' => null, 'max' => null], 
                        ]
                    ],
                ],
            ]);
        }
    }
}
