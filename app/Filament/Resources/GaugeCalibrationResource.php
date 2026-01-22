<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GaugeCalibrationResource\Pages;
use App\Models\CalibrationRecord;
use App\Models\Instrument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Clusters\CalibrationReport;

class GaugeCalibrationResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;
    protected static ?string $slug = 'gauge-calibration';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Gauge Calibration';
    protected static ?string $navigationGroup = 'สอบเทียบภายใน (Internal)';
    protected static ?string $cluster = CalibrationReport::class;
    protected static ?string $modelLabel = 'Gauge Calibration';
    protected static ?int $navigationSort = 1;

    // 🔥 Gauge Type Configuration
    protected static array $gaugeTypes = [
        'KGauge' => [
            'label' => 'K-Gauge',
            'code_pattern' => '%-01-%',
        ],
        'SnapGauge' => [
            'label' => 'Snap Gauge',
            'code_pattern' => '%-02-%',
        ],
        'PlugGauge' => [
            'label' => 'Plug Gauge',
            'code_pattern' => '%-03-%',
        ],
        'ThreadPlugGauge' => [
            'label' => 'Thread Plug Gauge',
            'code_pattern' => '%-04-%|%-05-%|%-06-%',
        ],
        'SerrationPlugGauge' => [
            'label' => 'Serration Plug Gauge',
            'code_pattern' => '%-04-%|%-05-%|%-06-%',
        ],
        'ThreadRingGauge' => [
            'label' => 'Thread Ring Gauge',
            'code_pattern' => '%-04-%|%-05-%|%-07-%',
        ],
        'SerrationRingGauge' => [
            'label' => 'Serration Ring Gauge',
            'code_pattern' => '%-04-%|%-05-%|%-07-%',
        ],
        'ThreadPlugGaugeFitWear' => [
            'label' => 'Plug Gauge (Fit & Wear)',
            'code_pattern' => '%-08-%|%-09-%',
        ], 
    ];

    public static function getEloquentQuery(): Builder
    {
        // 🔥 Query all gauge types dynamically from $gaugeTypes patterns
        return parent::getEloquentQuery()
            ->with(['instrument.toolType'])
            ->whereHas('instrument', function ($query) {
                $query->where(function ($q) {
                    // รวมทุก pattern จาก $gaugeTypes
                    foreach (self::$gaugeTypes as $type => $config) {
                        $patterns = explode('|', $config['code_pattern']);
                        foreach ($patterns as $pattern) {
                            $q->orWhere('code_no', 'LIKE', $pattern);
                        }
                    }
                });
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    Section::make('ข้อมูลการสอบเทียบ (Calibration Info)')
                        ->schema([
                            Grid::make(3)->schema([
                                Forms\Components\Hidden::make('calibration_type')
                                    ->default(request()->query('type') ?? 'KGauge')
                                    ->afterStateHydrated(function ($state, Set $set) {
                                        $type = $state ?? request()->query('type') ?? 'KGauge';
                                        $set('calibration_type', $type);
                                        $set('calibration_data.calibration_type', $type);
                                    })
                                    ->dehydrated(),

                                Select::make('instrument_id')
                                    ->label('เลือกเครื่องมือ (Code No)')
                                    ->searchable()
                                    ->required()
                                    ->placeholder('รหัสเครื่องมือ หรือ รหัสประเภทเครื่องมือ')
                                    ->columnSpan(2)
                                    ->reactive()
                                    ->getSearchResultsUsing(function (string $search, Get $get) {
                                        $calibrationType = $get('calibration_type') ?? 'KGauge';
                                        $pattern = self::$gaugeTypes[$calibrationType]['code_pattern'] ?? '8-%';
                                        
                                        $query = Instrument::query();
                                        
                                        // 🔥 Handle multiple patterns separated by |
                                        if (str_contains($pattern, '|')) {
                                            $patterns = explode('|', $pattern);
                                            $query->where(function ($q) use ($patterns) {
                                                foreach ($patterns as $p) {
                                                    $q->orWhere('code_no', 'LIKE', $p);
                                                }
                                            });
                                        } else {
                                            $query->where('code_no', 'LIKE', $pattern);
                                        }
                                        
                                        return $query
                                            ->where(function($q) use ($search) {
                                                $q->where('code_no', 'like', "%{$search}%")
                                                  ->orWhere('name', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($instrument) => [
                                                $instrument->id => "{$instrument->code_no} - {$instrument->name}"
                                            ])
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $instrument = Instrument::find($value);
                                        return $instrument ? "{$instrument->code_no} - {$instrument->name}" : '';
                                    })
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $calibrationType = $get('calibration_type') ?? request()->query('type') ?? 'KGauge';
                                        if ($calibrationType === 'PlugGauge') {
                                            self::onInstrumentSelectedPlugGauge($state, $set, $get);
                                        } elseif (in_array($calibrationType, ['ThreadPlugGauge', 'SerrationPlugGauge'])) {
                                            self::onInstrumentSelectedThreadPlugGauge($state, $set, $get);
                                        } elseif (in_array($calibrationType, ['ThreadRingGauge', 'SerrationRingGauge'])) {
                                            self::onInstrumentSelectedThreadRingGauge($state, $set, $get);
                                        } elseif ($calibrationType === 'ThreadPlugGaugeFitWear') {
                                            self::onInstrumentSelectedThreadPlugGaugeFitWear($state, $set, $get);
                                        } else {
                                            self::onInstrumentSelected($state, $set, $get);
                                        }
                                    })
                                    ->default(request()->query('instrument_id'))
                                    ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                        $id = $state ?? request()->query('instrument_id');
                                        if ($id) {
                                            if (!$state) {
                                                $set('instrument_id', $id);
                                            }
                                            $calibrationType = $get('calibration_type') ?? request()->query('type') ?? 'KGauge';
                                            if ($calibrationType === 'PlugGauge') {
                                                self::onInstrumentSelectedPlugGauge($id, $set, $get);
                                            } elseif (in_array($calibrationType, ['ThreadPlugGauge', 'SerrationPlugGauge'])) {
                                                self::onInstrumentSelectedThreadPlugGauge($id, $set, $get);
                                            } elseif (in_array($calibrationType, ['ThreadRingGauge', 'SerrationRingGauge'])) {
                                                self::onInstrumentSelectedThreadRingGauge($id, $set, $get);
                                            } elseif ($calibrationType === 'ThreadPlugGaugeFitWear') {
                                                self::onInstrumentSelectedThreadPlugGaugeFitWear($id, $set, $get);
                                            } else {
                                                self::onInstrumentSelected($id, $set, $get);
                                            }
                                        }
                                    }),

                                Forms\Components\Hidden::make('calibration_data.calibration_type')
                                    ->dehydrated(),

                                DatePicker::make('cal_date')
                                    ->label('วันที่สอบเทียบ (Cal Date)')
                                    ->default(now())
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $level = $get('cal_level') ?? 'A';
                                        self::updateNextCalDate($set, $get, $level);
                                    }),

                                TextInput::make('instrument_name')
                                    ->label('Name')
                                    ->disabled()
                                    ->columnSpan(3)
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $state, Get $get) {
                                        $id = $get('instrument_id') ?? request()->query('instrument_id');
                                        if ($id && !$state) {
                                            $instrument = Instrument::with('toolType')->find($id);
                                            $component->state($instrument->toolType?->name ?? '-');
                                        }
                                    }),

                                TextInput::make('instrument_size')
                                    ->label('Size')
                                    ->disabled()
                                    ->columnSpan(3)
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $state, Get $get) {
                                        $id = $get('instrument_id') ?? request()->query('instrument_id');
                                        if ($id && !$state) {
                                            $instrument = Instrument::with('toolType')->find($id);
                                            $component->state($instrument->toolType?->size ?? '-');
                                        }
                                    }),

                                TextInput::make('instrument_department')
                                    ->label('แผนก')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $state, Get $get) {
                                        $id = $get('instrument_id') ?? request()->query('instrument_id');
                                        if ($id && !$state) {
                                            $instrument = Instrument::with('department')->find($id);
                                            $component->state($instrument->department?->name ?? '-');
                                        }
                                    }),

                                TextInput::make('instrument_serial')
                                    ->label('Serial No.')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $state, Get $get) {
                                        $id = $get('instrument_id') ?? request()->query('instrument_id');
                                        if ($id && !$state) {
                                            $instrument = Instrument::find($id);
                                            $component->state($instrument->serial_no ?? '-');
                                        }
                                    }),

                                TextInput::make('instrument_drawing')
                                    ->label('Drawing No.')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $state, Get $get) {
                                        $id = $get('instrument_id') ?? request()->query('instrument_id');
                                        if ($id && !$state) {
                                            $instrument = Instrument::with('toolType')->find($id);
                                            $component->state($instrument->toolType?->drawing_no ?? '-');
                                        }
                                    }),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('environment.temperature')
                                    ->label('อุณหภูมิ (°C)')
                                    ->numeric()
                                    ->default(null),
                                TextInput::make('environment.humidity')
                                    ->label('ความชื้น (%)')
                                    ->numeric()
                                    ->default(null),
                            ]),
                        ])
                        ->columnSpan(1),

                    Section::make('รูปภาพอ้างอิง (Drawing Reference)')
                        ->schema([
                            Placeholder::make('picture_path')
                                ->label('')
                                ->content(fn (Get $get) => view('filament.components.picture_path', [
                                    'instrumentId' => $get('instrument_id'),
                                ])),
                        ])
                        ->columnSpan(1),
                ]),

                Section::make('รายการเครื่องมือมาตรฐานที่ใช้สอบเทียบ (Master Reference)')
                    ->schema([
                        Placeholder::make('masters_reference')
                            ->label('')
                            ->content(function (Get $get) {
                                $instrumentId = $get('instrument_id');
                                if (!$instrumentId) {
                                    return view('filament.components.masters-placeholder', [
                                        'message' => 'กรุณาเลือกเครื่องมือก่อน'
                                    ]);
                                }

                                $instrument = Instrument::with('toolType.masters')->find($instrumentId);
                                if (!$instrument || !$instrument->toolType) {
                                    return view('filament.components.masters-placeholder', [
                                        'message' => 'ไม่พบข้อมูล Tool Type'
                                    ]);
                                }

                                $masters = $instrument->toolType->masters;
                                if ($masters->isEmpty()) {
                                    return view('filament.components.masters-placeholder', [
                                        'message' => 'ยังไม่มี Master กำหนดไว้'
                                    ]);
                                }

                                return view('filament.components.masters-table', [
                                    'masters' => $masters
                                ]);
                            }),
                    ]),

                // 🔥 Section สำหรับ Plug Gauge (Nested Repeater with multiple measurements)
                Section::make('ผลการวัด (Measurement Results) - Plug Gauge')
                    ->description('กรอกค่าตามจุดตรวจสอบ - สามารถเพิ่มหลายค่าต่อจุด และใช้ค่าเฉลี่ยในการคำนวณ')
                    ->visible(fn (Get $get) => $get('calibration_type') === 'PlugGauge')
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?') . ' - STD')
                            ->afterStateHydrated(function ($component, $state, Get $get, Set $set) {
                                $id = $get('instrument_id') ?? request()->query('instrument_id');
                                $type = $get('calibration_type') ?? request()->query('type');
                                if ($id && empty($state) && $type === 'PlugGauge') {
                                    self::onInstrumentSelectedPlugGauge($id, $set, $get);
                                }
                            })
                            ->schema([
                                Grid::make(12)->schema([
                                    Forms\Components\Hidden::make('point')->dehydrated(),
                                    Forms\Components\Hidden::make('std_label')->dehydrated(),
                                    Forms\Components\Hidden::make('trend')->dehydrated(),
                                    Forms\Components\Hidden::make('min_spec')->dehydrated(),
                                    Forms\Components\Hidden::make('max_spec')->dehydrated(),
                                    Forms\Components\Hidden::make('all_specs')->dehydrated(),

                                    Placeholder::make('point_info')
                                        ->label('')
                                        ->columnSpan(12)
                                        ->content(fn (Get $get) => view('filament.components.point-info', [
                                            'point' => $get('point'),
                                            'trend' => $get('trend'),
                                            'minSpec' => $get('min_spec') . ' mm.',
                                            'maxSpec' => $get('max_spec') . ' mm.',
                                            'stdLabel' => $get('std_label'),
                                        ])),

                                    Repeater::make('measurements')
                                        ->hiddenLabel()
                                        ->columnSpan(6)
                                        ->schema([
                                            TextInput::make('value')
                                                ->label('ค่าวัด')
                                                ->numeric()
                                                ->placeholder('0.000')
                                                ->live(debounce: 500)
                                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                    self::calculateAverageReading($get, $set);
                                                })
                                                ->extraAttributes([
                                                    'style' => 'font-family: monospace; text-align: center;'
                                                ]),
                                        ])
                                        ->addActionLabel('+ เพิ่มค่าวัด')
                                        ->reorderable(false)
                                        ->cloneable(false)
                                        ->defaultItems(1)
                                        ->minItems(1)
                                        ->columns(1)
                                        ->grid(3)
                                        ->itemLabel(fn (array $state): ?string => $state['value'] ? 'ค่า: ' . $state['value'] . ' mm.' : 'กรอกค่า'),

                                    Section::make('ผลลัพธ์')
                                        ->columnSpan(6)
                                        ->compact()
                                        ->schema([
                                            Grid::make(4)->schema([
                                                TextInput::make('reading')
                                                    ->label('ค่าเฉลี่ย (Avg)')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->extraAttributes([
                                                        'style' => 'font-family: monospace; font-weight: 700; text-align: center; background-color: #e0f2fe; color: #0369a1; font-size: 1.1rem;'
                                                    ]),
                                                
                                                TextInput::make('error')
                                                    ->label('Error')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600; text-align: center;']),
                                                
                                                TextInput::make('Judgement')
                                                    ->label('Judgement')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->extraAttributes(fn ($state) => [
                                                        'style' => match($state) {
                                                            'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                                                            'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                                                            default => 'text-align: center;'
                                                        }
                                                    ]),

                                                Select::make('grade')
                                                    ->label('Grade')
                                                    ->disabled()
                                                    ->options([
                                                        'A' => 'Grade A',
                                                        'B' => 'Grade B',
                                                        'C' => 'Grade C',
                                                    ])
                                                    ->dehydrated()
                                                    ->extraAttributes(fn ($state) => [
                                                        'style' => match($state) {
                                                            'A' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important;',
                                                            'B' => 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: bold !important;',
                                                            'C' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important;',
                                                            default => ''
                                                        }
                                                    ]),
                                            ]),
                                        ]),
                                ]),
                            ])
                            ->collapsible()
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->cloneable(false)
                            ->defaultItems(0)
                            ->columns(1),
                    ]),

                // 🔥 Section สำหรับ Thread Plug Gauge (Nested Specs with measurements)
                Section::make('ผลการวัด (Measurement Results) - Thread Plug Gauge')
                    ->description('กรอกค่าตามจุดตรวจสอบ - Thread Plug Gauge รวม Major, Pitch, Plug ไว้ในแต่ละ Point')
                    ->visible(fn (Get $get) => in_array($get('calibration_type'), ['ThreadPlugGauge', 'SerrationPlugGauge']))
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?') . ' - Major - Pitch - Plug')
                            ->afterStateHydrated(function ($component, $state, Get $get, Set $set) {
                                $id = $get('instrument_id') ?? request()->query('instrument_id');
                                $type = $get('calibration_type') ?? request()->query('type');
                                if ($id && empty($state) && in_array($type, ['ThreadPlugGauge', 'SerrationPlugGauge'])) {
                                    self::onInstrumentSelectedThreadPlugGauge($id, $set, $get);
                                }
                            })
                            ->schema([
                                Forms\Components\Hidden::make('point')->dehydrated(),
                                Forms\Components\Hidden::make('trend')->dehydrated(),

                                // 🔥 Nested Repeater สำหรับ specs (Major, Pitch, Plug)
                                Repeater::make('specs')
                                    ->label('รายการ Specs')
                                    ->schema([
                                        Forms\Components\Hidden::make('label')->dehydrated(),
                                        Forms\Components\Hidden::make('min_spec')->dehydrated(),
                                        Forms\Components\Hidden::make('max_spec')->dehydrated(),

                                        Placeholder::make('spec_info')
                                            ->label('')
                                            ->content(fn (Get $get) => view('filament.components.thread-plug-spec-info', [
                                                'label' => $get('label'),
                                                'minSpec' => $get('min_spec'),
                                                'maxSpec' => $get('max_spec'),
                                                'trend' => $get('../../trend'),
                                            ])),

                                        // 🔥 Nested Repeater สำหรับหลายค่าวัด
                                        Repeater::make('measurements')
                                            ->hiddenLabel()
                                            ->schema([
                                                TextInput::make('value')
                                                    ->label('ค่า')
                                                    ->numeric()
                                                    ->placeholder('0.000')
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        self::calculateSpecResultThreadPlugGauge($get, $set);
                                                    })
                                                    ->extraAttributes([
                                                        'style' => 'font-family: monospace; text-align: center; font-weight: 600;'
                                                    ]),
                                            ])
                                            ->addActionLabel('+ เพิ่มค่าวัด')
                                            ->reorderable(false)
                                            ->cloneable(false)
                                            ->defaultItems(4)
                                            ->minItems(1)
                                            ->grid(4)
                                            ->itemLabel(fn (array $state): ?string => $state['value'] ? $state['value'] . ' mm.' : 'กรอกค่า'),

                                        // 🔥 Result Section
                                        Section::make('ผลลัพธ์')
                                            ->compact()
                                            ->schema([
                                                Grid::make(4)->schema([
                                                    TextInput::make('reading')
                                                        ->label('ค่าเฉลี่ย (Avg)')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 700; text-align: center; background-color: #e0f2fe; color: #0369a1;'
                                                        ]),

                                                    TextInput::make('error')
                                                        ->label('Error')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 600; text-align: center;'
                                                        ]),

                                                    TextInput::make('Judgement')
                                                        ->label('Judgement')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes(fn ($state) => [
                                                            'style' => match($state) {
                                                                'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                                                                'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                                                                default => 'text-align: center;'
                                                            }
                                                        ]),

                                                    Select::make('grade')
                                                        ->label('Grade')
                                                        ->disabled()
                                                        ->options([
                                                            'A' => 'Grade A',
                                                            'B' => 'Grade B',
                                                            'C' => 'Grade C',
                                                        ])
                                                        ->dehydrated()
                                                        ->extraAttributes(fn ($state) => [
                                                            'style' => match($state) {
                                                                'A' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important;',
                                                                'B' => 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: bold !important;',
                                                                'C' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important;',
                                                                default => ''
                                                            }
                                                        ]),
                                                ]),
                                            ]),
                                    ])
                                    ->reorderable(false)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->cloneable(false)
                                    ->defaultItems(0)
                                    ->columns(1)
                                    ->itemLabel(fn (array $state): ?string => ($state['label'] ?? '?')),
                            ])
                            ->collapsible()
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->cloneable(false)
                            ->defaultItems(0)
                            ->columns(1),
                    ]),

                // 🔥 Section สำหรับ Thread Ring Gauge (Simple text input - no calculation)
                Section::make('ผลการวัดเกลียว (Thread Measurement) - Ring Gauge')
                    ->description('กรอกค่าวัดเกลียวเป็นข้อความ - ไม่มีการคำนวณ')
                    ->visible(fn (Get $get) => in_array($get('calibration_type'), ['ThreadRingGauge', 'SerrationRingGauge']))
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?') . ' - วัดเกลียว')
                            ->afterStateHydrated(function ($component, $state, Get $get, Set $set) {
                                $id = $get('instrument_id') ?? request()->query('instrument_id');
                                $type = $get('calibration_type') ?? request()->query('type');
                                if ($id && empty($state) && in_array($type, ['ThreadRingGauge', 'SerrationRingGauge'])) {
                                    self::onInstrumentSelectedThreadRingGauge($id, $set, $get);
                                }
                            })
                            ->schema([
                                Forms\Components\Hidden::make('point')->dehydrated(),
                                Forms\Components\Hidden::make('label')->dehydrated(),

                                Grid::make(5)->schema([
                                    Select::make('trend')
                                        ->label('Trend')
                                        ->options([
                                            'Smaller' => 'เล็กลง (Smaller)',
                                            'Bigger' => 'ใหญ่ขึ้น (Bigger)',
                                            'None' => 'ไม่มี (General)',
                                        ])
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('standard_value')
                                        ->label('ค่ามาตรฐาน')
                                        ->columnSpan(2)
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('measurement')
                                        ->label('ค่าวัดเกลียว')
                                        ->columnSpan(2)
                                        ->placeholder('กรอกค่าวัดเกลียว...')
                                        ->dehydrated(),
                                ]),
                            ])
                            ->collapsible()
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->cloneable(false)
                            ->defaultItems(0)
                            ->columns(1),
                    ]),

                // 🔥 Section สำหรับ Plug Gauge Fit & Wear (Nested Specs with measurements - same as ThreadPlugGauge)
                Section::make('ผลการวัด (Measurement Results) - Plug Gauge Fit & Wear')
                    ->description('กรอกค่าตามจุดตรวจสอบ - รวม Major, Pitch, Plug ไว้ในแต่ละ Point')
                    ->visible(fn (Get $get) => $get('calibration_type') === 'ThreadPlugGaugeFitWear')
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?') . ' - Major - Pitch - Plug')
                            ->afterStateHydrated(function ($component, $state, Get $get, Set $set) {
                                $id = $get('instrument_id') ?? request()->query('instrument_id');
                                $type = $get('calibration_type') ?? request()->query('type');
                                if ($id && empty($state) && $type === 'ThreadPlugGaugeFitWear') {
                                    self::onInstrumentSelectedThreadPlugGaugeFitWear($id, $set, $get);
                                }
                            })
                            ->schema([
                                Forms\Components\Hidden::make('point')->dehydrated(),
                                Forms\Components\Hidden::make('trend')->dehydrated(),

                                // 🔥 Nested Repeater สำหรับ specs (Major, Pitch, Plug)
                                Repeater::make('specs')
                                    ->label('รายการ Specs')
                                    ->schema([
                                        Forms\Components\Hidden::make('label')->dehydrated(),
                                        Forms\Components\Hidden::make('min_spec')->dehydrated(),
                                        Forms\Components\Hidden::make('max_spec')->dehydrated(),

                                        Placeholder::make('spec_info')
                                            ->label('')
                                            ->content(fn (Get $get) => view('filament.components.thread-plug-spec-info', [
                                                'label' => $get('label'),
                                                'minSpec' => $get('min_spec'),
                                                'maxSpec' => $get('max_spec'),
                                                'trend' => $get('../../trend'),
                                            ])),

                                        // 🔥 Nested Repeater สำหรับหลายค่าวัด
                                        Repeater::make('measurements')
                                            ->hiddenLabel()
                                            ->schema([
                                                TextInput::make('value')
                                                    ->label('ค่า')
                                                    ->numeric()
                                                    ->placeholder('0.000')
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        self::calculateSpecResultThreadPlugGaugeFitWear($get, $set);
                                                    })
                                                    ->extraAttributes([
                                                        'style' => 'font-family: monospace; text-align: center; font-weight: 600;'
                                                    ]),
                                            ])
                                            ->addActionLabel('+ เพิ่มค่าวัด')
                                            ->reorderable(false)
                                            ->cloneable(false)
                                            ->defaultItems(4)
                                            ->minItems(1)
                                            ->grid(4)
                                            ->itemLabel(fn (array $state): ?string => $state['value'] ? $state['value'] . ' mm.' : 'กรอกค่า'),

                                        // 🔥 Result Section
                                        Section::make('ผลลัพธ์')
                                            ->compact()
                                            ->schema([
                                                Grid::make(4)->schema([
                                                    TextInput::make('reading')
                                                        ->label('ค่าเฉลี่ย (Avg)')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 700; text-align: center; background-color: #e0f2fe; color: #0369a1;'
                                                        ]),

                                                    TextInput::make('error')
                                                        ->label('Error')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 600; text-align: center;'
                                                        ]),

                                                    TextInput::make('Judgement')
                                                        ->label('Judgement')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes(fn ($state) => [
                                                            'style' => match($state) {
                                                                'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                                                                'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                                                                default => 'text-align: center;'
                                                            }
                                                        ]),

                                                    Select::make('grade')
                                                        ->label('Grade')
                                                        ->disabled()
                                                        ->options([
                                                            'A' => 'Grade A',
                                                            'B' => 'Grade B',
                                                            'C' => 'Grade C',
                                                        ])
                                                        ->dehydrated()
                                                        ->extraAttributes(fn ($state) => [
                                                            'style' => match($state) {
                                                                'A' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important;',
                                                                'B' => 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: bold !important;',
                                                                'C' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important;',
                                                                default => ''
                                                            }
                                                        ]),
                                                ]),
                                            ]),
                                    ])
                                    ->reorderable(false)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->cloneable(false)
                                    ->defaultItems(0)
                                    ->columns(1)
                                    ->itemLabel(fn (array $state): ?string => ($state['label'] ?? '?')),
                            ])
                            ->collapsible()
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->cloneable(false)
                            ->defaultItems(0)
                            ->columns(1),
                    ]),

                // 🔥 Section สำหรับ Gauge อื่นๆ (K-Gauge, Snap Gauge - Simple Input)
                Section::make('ผลการวัด (Measurement Results)')
                    ->description('กรอกค่าตามจุดตรวจสอบ (A, B, C...)')
                    ->visible(fn (Get $get) => !in_array($get('calibration_type'), ['PlugGauge', 'ThreadPlugGauge', 'SerrationPlugGauge', 'ThreadRingGauge', 'SerrationRingGauge', 'ThreadPlugGaugeFitWear']))
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?') . ' - ' . ($state['std_label'] ?? 'STD'))
                            ->afterStateHydrated(function ($component, $state, Get $get, Set $set) {
                                $id = $get('instrument_id') ?? request()->query('instrument_id');
                                $type = $get('calibration_type') ?? request()->query('type');
                                if ($id && empty($state) && $type !== 'PlugGauge') {
                                    self::onInstrumentSelected($id, $set, $get);
                                }
                            })
                            ->schema([
                                Grid::make(9)->schema([
                                    Select::make('trend')
                                        ->label('Trend')
                                        ->columnSpan(2)
                                        ->options([
                                            'Smaller' => 'เล็กลง (Smaller)',
                                            'Bigger' => 'ใหญ่ขึ้น (Bigger)',
                                            'None' => 'ไม่มี (General)',
                                        ])
                                        ->disabled()
                                        ->dehydrated(),

                                    Forms\Components\Hidden::make('std_label')
                                        ->dehydrated(),

                                    TextInput::make('min_spec')
                                        ->label('Min')
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('max_spec')
                                        ->label('Max')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('reading')
                                        ->label('ค่าที่วัดได้')
                                        ->live(onBlur: true)
                                        ->placeholder('0.000')
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            $readings = $get('../../../calibration_data.readings') ?? [];
                                            $allFilled = true;

                                            foreach ($readings as $reading) {
                                                if (empty($reading['reading']) || $reading['reading'] == 0) {
                                                    $allFilled = false;
                                                    break;
                                                }
                                            }

                                            if ($allFilled) {
                                                self::calculateAllPointsAuto($get, $set);
                                            }
                                        }),

                                    TextInput::make('error')
                                        ->label('Error')
                                        ->disabled()
                                        ->dehydrated()
                                        ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600; text-align: center;']),

                                    TextInput::make('Judgement')
                                        ->label('Judgement')
                                        ->disabled()
                                        ->dehydrated()
                                        ->extraAttributes(fn ($state) => [
                                            'style' => match($state) {
                                                'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                                                'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                                                default => 'text-align: center;'
                                            }
                                        ]),

                                    Select::make('grade')
                                        ->label('Grade Result')
                                        ->columnSpan(2)
                                        ->options([
                                            'A' => 'Grade A (Pass)',
                                            'B' => 'Grade B (Warning)',
                                            'C' => 'Grade C (Fail)',
                                        ])
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            self::calculateOverallFromGrades($get, $set);
                                        })
                                        ->dehydrated(),
                                ]),
                            ])
                            ->collapsible()
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->cloneable(false)
                            ->defaultItems(0)
                            ->columns(1),
                    ]),

                Section::make('สรุปผล (Conclusion)')
                    ->schema([
                        Grid::make(4)->schema([
                            Select::make('result_status')
                                ->label('ผลการสอบเทียบ (Status)')
                                ->options([
                                    'Pass' => 'ผ่าน (Pass)',
                                    'Reject' => 'ไม่ผ่าน (Reject)',
                                ])
                                ->dehydrated()
                                ->native(false)
                                ->extraAttributes(fn ($state) => [
                                    'style' => match($state) {
                                        'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; border: 2px solid #86efac !important;',
                                        'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; border: 2px solid #fca5a5 !important;',
                                        default => ''
                                    }
                                ]),

                            Select::make('cal_level')
                                ->label('ระดับการสอบเทียบ (Level)')
                                ->options([
                                    'A' => 'ระดับ A',
                                    'B' => 'ระดับ B',
                                    'C' => 'ระดับ C',
                                ])
                                ->dehydrated()
                                ->native(false)
                                ->extraAttributes(fn ($state) => [
                                    'style' => match($state) {
                                        'A' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; border: 2px solid #86efac !important;',
                                        'B' => 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: bold !important; border: 2px solid #fde047 !important;',
                                        'C' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; border: 2px solid #fca5a5 !important;',
                                        default => ''
                                    }
                                ]),

                            DatePicker::make('next_cal_date')
                                ->label('วันครบกำหนดครั้งถัดไป (Next Cal)')
                                ->dehydrated()
                                ->visible(fn (Get $get) => $get('result_status') !== 'Reject' && $get('cal_level') !== 'C')
                                ->required(fn (Get $get) => $get('result_status') !== 'Reject' && $get('cal_level') !== 'C')
                                ->live(),

                            TextInput::make('price')
                                ->label('ราคาสอบเทียบ (บาท)')
                                ->numeric()
                                ->prefix('฿')
                                ->placeholder('0.00')
                                ->step(0.01)
                                ->default(0),

                             Textarea::make('remark')
                                ->label('หมายเหตุ (Remark)')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    ]),

                // 🔥 Certificate PDF Upload Section
                Section::make('Certificate')
                    ->description('อัพโหลดไฟล์ ขนาดไม่เกิน 10MB')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('certificate_file')
                            ->label('อัพโหลด Certificate PDF')
                            ->disk('public')
                            ->directory('gauge-calibration-certificates')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function onInstrumentSelected($state, Set $set, Get $get)
    {
        if (!$state) return;

        $instrument = Instrument::with('toolType', 'department')->find($state);
        if (!$instrument) return;

        $set('next_cal_date', now()->addMonths($instrument->cal_freq_months ?? 6));
        $set('instrument_size', $instrument->toolType?->size ?? '-');
        $set('instrument_name', $instrument->toolType?->name ?? '-');
        $set('instrument_department', $instrument->department?->name ?? '-');
        $set('instrument_serial', $instrument->serial_no ?? '-');
        $set('instrument_drawing', $instrument->toolType?->drawing_no ?? '-');

        if ($instrument->toolType && $instrument->toolType->dimension_specs) {
            $dimensionSpecs = $instrument->toolType->dimension_specs;
            $readings = [];

            foreach ($dimensionSpecs as $spec) {
                $point = $spec['point'] ?? null;
                if (!$point) continue;

                $trend = $spec['trend'] ?? 'Smaller';

                if (isset($spec['specs']) && is_array($spec['specs'])) {
                    foreach ($spec['specs'] as $specItem) {
                        $valMin = $specItem['min'] ?? null;
                        $valMax = $specItem['max'] ?? null;

                        $readings[] = [
                            'point' => $point,
                            'trend' => $trend,
                            'std_label' => $specItem['label'] ?? 'STD',
                            'min_spec' => $valMin !== null ? rtrim(rtrim(number_format((float)$valMin, 8, '.', ''), '0'), '.') : null,
                            'max_spec' => $valMax !== null ? rtrim(rtrim(number_format((float)$valMax, 8, '.', ''), '0'), '.') : null,
                        ];
                    }
                }
            }

            $set('calibration_data.readings', $readings);
        }
    }

    protected static function calculateAllPointsAuto(Get $get, Set $set)
    {
        $readings = $get('../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../instrument_id');

        if (!$instrumentId || empty($readings)) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $percentAdj = (float) ($instrument->percent_adj ?? 10);

        foreach ($readings as $index => $reading) {
            $readingValue = (float) ($reading['reading'] ?? 0);
            if ($readingValue == 0) continue;

            $minSpec = (float) ($reading['min_spec'] ?? 0);
            $maxSpec = (float) ($reading['max_spec'] ?? 0);
            $trend = $reading['trend'];

            $range = $maxSpec - $minSpec;
            $tolerance = $range * ($percentAdj / 100);

            $grade = 'C';
            $error = 0;
            $judgement = 'Reject';

            if ($trend === 'Smaller') {
                $error = $readingValue - $minSpec;
                $thresholdA = $minSpec + $tolerance;

                if ($readingValue < $minSpec || $readingValue > $maxSpec) {
                    $grade = 'C';
                } elseif ($readingValue >= $thresholdA && $readingValue <= $maxSpec) {
                    $grade = 'A';
                } else {
                    $grade = 'B';
                }
            } elseif ($trend === 'Bigger') {
                $error = $readingValue - $maxSpec;
                $thresholdA = $maxSpec - $tolerance;

                if ($readingValue < $minSpec || $readingValue > $maxSpec) {
                    $grade = 'C';
                } elseif ($readingValue <= $thresholdA && $readingValue >= $minSpec) {
                    $grade = 'A';
                } else {
                    $grade = 'B';
                }
            }

            $judgement = ($grade === 'C') ? 'Reject' : 'Pass';

            $set("../../../calibration_data.readings.{$index}.error", number_format($error, 4));
            $set("../../../calibration_data.readings.{$index}.Judgement", $judgement);
            $set("../../../calibration_data.readings.{$index}.grade", $grade);
        }

        $readings = $get('../../../calibration_data.readings') ?? [];
        $grades = collect($readings)->pluck('grade')->filter();

        $level = 'A';
        if ($grades->contains('C')) {
            $level = 'C';
        } elseif ($grades->contains('B')) {
            $level = 'B';
        }

        $status = $grades->contains('C') ? 'Reject' : 'Pass';

        $set('../../../result_status', $status);
        $set('../../../cal_level', $level);

        $calDate = $get('../../../cal_date');
        if ($calDate) {
            $nextDate = match($level) {
                'A' => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
                'B' => \Carbon\Carbon::parse($calDate)->addMonth()->endOfMonth(),
                'C' => null,
                default => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
            };

            if ($nextDate) {
                $set('../../../next_cal_date', $nextDate->format('Y-m-d'));
            }
        }
    }

    protected static function calculateOverallFromGrades(Get $get, Set $set)
    {
        $readings = $get('../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../instrument_id');

        if (!$instrumentId || empty($readings)) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $grades = collect($readings)->pluck('grade')->filter();

        $level = 'A';
        if ($grades->contains('C')) {
            $level = 'C';
        } elseif ($grades->contains('B')) {
            $level = 'B';
        }

        $status = $grades->contains('C') ? 'Reject' : 'Pass';

        $set('../../../result_status', $status);
        $set('../../../cal_level', $level);

        $calDate = $get('../../../cal_date');
        if ($calDate) {
            $nextDate = match($level) {
                'A' => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
                'B' => \Carbon\Carbon::parse($calDate)->addMonth()->endOfMonth(),
                'C' => null,
                default => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
            };

            if ($nextDate) {
                $set('../../../next_cal_date', $nextDate->format('Y-m-d'));
            }
        }
    }

    protected static function updateNextCalDate(Set $set, Get $get, string $level)
    {
        $calDate = $get('../../../cal_date') ?? $get('cal_date');
        $instrumentId = $get('../../../instrument_id') ?? $get('instrument_id');

        if (!$calDate || !$instrumentId) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $nextDate = match($level) {
            'A' => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
            'B' => \Carbon\Carbon::parse($calDate)->addMonth()->endOfMonth(),
            'C' => null,
            default => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
        };

        if ($nextDate) {
            $pathToTry = ['../../../next_cal_date', 'next_cal_date'];
            foreach ($pathToTry as $path) {
                try {
                    $set($path, $nextDate->format('Y-m-d'));
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    // 🔥 Logic สำหรับ Plug Gauge - สร้าง readings พร้อม measurements array
    protected static function onInstrumentSelectedPlugGauge($state, Set $set, Get $get)
    {
        if (!$state) return;

        $instrument = Instrument::with('toolType', 'department')->find($state);
        if (!$instrument) return;

        $set('next_cal_date', now()->addMonths($instrument->cal_freq_months ?? 6));
        $set('instrument_size', $instrument->toolType?->size ?? '-');
        $set('instrument_name', $instrument->toolType?->name ?? '-');
        $set('instrument_department', $instrument->department?->name ?? '-');
        $set('instrument_serial', $instrument->serial_no ?? '-');
        $set('instrument_drawing', $instrument->toolType?->drawing_no ?? '-');

        if ($instrument->toolType && $instrument->toolType->dimension_specs) {
            $dimensionSpecs = $instrument->toolType->dimension_specs;
            $readings = [];

            foreach ($dimensionSpecs as $pointIndex => $spec) {
                $point = $spec['point'] ?? null;
                if (!$point) continue;

                $readingItem = [
                    'point' => $point,
                    'trend' => $spec['trend'] ?? 'Smaller',
                ];

                if (isset($spec['specs']) && is_array($spec['specs']) && count($spec['specs']) > 0) {
                    $mainSpec = $spec['specs'][0];
                    $readingItem['std_label'] = $mainSpec['label'] ?? 'STD';

                    $valMin = $mainSpec['min'] ?? null;
                    $valMax = $mainSpec['max'] ?? null;
                    $readingItem['min_spec'] = $valMin !== null ? rtrim(rtrim(number_format((float)$valMin, 8, '.', ''), '0'), '.') : null;
                    $readingItem['max_spec'] = $valMax !== null ? rtrim(rtrim(number_format((float)$valMax, 8, '.', ''), '0'), '.') : null;
                }

                if (isset($spec['specs'])) {
                    $readingItem['all_specs'] = $spec['specs'];
                }

                // 🔥 กำหนดจำนวน default measurements ตามลำดับ Point
                $measurementCount = match($pointIndex) {
                    0 => 3,  // Point 1 = 3 ช่อง
                    1 => 2,  // Point 2 = 2 ช่อง
                    default => 1,
                };

                $readingItem['measurements'] = array_fill(0, $measurementCount, ['value' => null]);

                $readings[] = $readingItem;
            }

            $set('calibration_data.readings', $readings);
        }
    }

    // 🔥 ฟังก์ชันคำนวณค่าเฉลี่ยจาก measurements (Plug Gauge)
    protected static function calculateAverageReading(Get $get, Set $set)
    {
        // 🔥 ตรวจสอบว่ากรอก value ครบ **ทุกช่อง** ของ **ทุก Point** หรือยัง
        $readings = $get('../../../../../calibration_data.readings') ?? [];

        $allValuesFilled = true;
        foreach ($readings as $reading) {
            $pointMeasurements = $reading['measurements'] ?? [];

            if (empty($pointMeasurements)) {
                $allValuesFilled = false;
                break;
            }

            foreach ($pointMeasurements as $m) {
                if (!isset($m['value']) || $m['value'] === '' || $m['value'] === null) {
                    $allValuesFilled = false;
                    break 2;
                }
            }
        }

        if (!$allValuesFilled) {
            return;
        }

        // 🔥 ถ้ากรอกครบทุกช่องแล้ว → คำนวณทั้งหมด
        self::calculateAllPointsFromMeasurementsPlugGauge($get, $set);
    }

    // 🔥 ฟังก์ชันคำนวณทุก Point พร้อมกัน (Plug Gauge)
    protected static function calculateAllPointsFromMeasurementsPlugGauge(Get $get, Set $set)
    {
        $readings = $get('../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../instrument_id');

        if (!$instrumentId || empty($readings)) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $percentAdj = (float) ($instrument->percent_adj ?? 10);

        foreach ($readings as $index => $reading) {
            $stdLabel = $reading['std_label'] ?? '';

            // คำนวณค่าเฉลี่ยจาก measurements
            $measurements = $reading['measurements'] ?? [];
            $values = collect($measurements)
                ->pluck('value')
                ->filter(fn ($v) => !is_null($v) && $v !== '' && is_numeric($v))
                ->map(fn ($v) => (float) $v);

            if ($values->isEmpty()) continue;

            $readingValue = $values->avg();
            $minSpec = (float) ($reading['min_spec'] ?? 0);
            $maxSpec = (float) ($reading['max_spec'] ?? 0);
            $trend = $reading['trend'] ?? 'Smaller';

            $range = $maxSpec - $minSpec;
            $tolerance = $range * ($percentAdj / 100);

            $grade = 'C';
            $error = 0;
            $judgement = 'Reject';

            if ($trend === 'Smaller') {
                $error = $readingValue - $minSpec;
                $thresholdA = $minSpec + $tolerance;

                if ($readingValue < $minSpec || $readingValue > $maxSpec) {
                    $grade = 'C';
                } elseif ($readingValue >= $thresholdA && $readingValue <= $maxSpec) {
                    $grade = 'A';
                } else {
                    $grade = 'B';
                }
            } elseif ($trend === 'Bigger') {
                $error = $readingValue - $maxSpec;
                $thresholdA = $maxSpec - $tolerance;

                if ($readingValue < $minSpec || $readingValue > $maxSpec) {
                    $grade = 'C';
                } elseif ($readingValue <= $thresholdA && $readingValue >= $minSpec) {
                    $grade = 'A';
                } else {
                    $grade = 'B';
                }
            }

            $judgement = ($grade === 'C') ? 'Reject' : 'Pass';

            $formattedAvg = rtrim(rtrim(number_format($readingValue, 6, '.', ''), '0'), '.');

            $set("../../../../../calibration_data.readings.{$index}.reading", $formattedAvg);
            $set("../../../../../calibration_data.readings.{$index}.error", number_format($error, 4));
            $set("../../../../../calibration_data.readings.{$index}.Judgement", $judgement);
            $set("../../../../../calibration_data.readings.{$index}.grade", $grade);
        }

        // คำนวณ Overall Status และ Level
        self::calculateOverallStatusPlugGauge($get, $set);
    }

    // 🔥 ฟังก์ชันคำนวณสถานะรวม (Plug Gauge)
    protected static function calculateOverallStatusPlugGauge(Get $get, Set $set)
    {
        $readings = $get('../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../instrument_id');

        if (!$instrumentId || empty($readings)) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $grades = collect($readings)->pluck('grade')->filter();

        $level = 'A';
        if ($grades->contains('C')) {
            $level = 'C';
        } elseif ($grades->contains('B')) {
            $level = 'B';
        }

        $status = $grades->contains('C') ? 'Reject' : 'Pass';

        $set('../../../../../result_status', $status);
        $set('../../../../../cal_level', $level);

        // Update Next Cal Date
        $calDate = $get('../../../../../cal_date');
        if ($calDate) {
            $nextDate = match($level) {
                'A' => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
                'B' => \Carbon\Carbon::parse($calDate)->addMonth()->endOfMonth(),
                'C' => null,
                default => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
            };

            if ($nextDate) {
                $set('../../../../../next_cal_date', $nextDate->format('Y-m-d'));
            }
        }
    }

    // 🔥 Logic สำหรับ Thread Plug Gauge - รวม Major, Pitch, Plug ไว้ใน Point เดียวกัน
    protected static function onInstrumentSelectedThreadPlugGauge($state, Set $set, Get $get)
    {
        if (!$state) return;

        $instrument = Instrument::with('toolType', 'department')->find($state);
        if (!$instrument) return;

        $set('next_cal_date', now()->addMonths($instrument->cal_freq_months ?? 6));
        $set('instrument_size', $instrument->toolType?->size ?? '-');
        $set('instrument_name', $instrument->toolType?->name ?? '-');
        $set('instrument_department', $instrument->department?->name ?? '-');
        $set('instrument_serial', $instrument->serial_no ?? '-');
        $set('instrument_drawing', $instrument->toolType?->drawing_no ?? '-');

        if ($instrument->toolType && $instrument->toolType->dimension_specs) {
            $dimensionSpecs = $instrument->toolType->dimension_specs;
            $readings = [];

            foreach ($dimensionSpecs as $pointIndex => $spec) {
                $point = $spec['point'] ?? null;
                if (!$point) continue;

                $readingItem = [
                    'point' => $point,
                    'trend' => $spec['trend'] ?? 'Smaller',
                    'specs' => [],
                ];

                // รวบรวม specs ทั้งหมด (Major, Pitch, Plug)
                $allSpecs = $spec['specs'] ?? [];
                foreach ($allSpecs as $specItem) {
                    $label = $specItem['label'] ?? '';

                    // ข้าม STD เพราะ Thread Plug Gauge ใช้ Major, Pitch, Plug
                    if ($label === 'STD') continue;

                    $valMin = $specItem['min'] ?? null;
                    $valMax = $specItem['max'] ?? null;

                    $readingItem['specs'][] = [
                        'label' => $label,
                        'min_spec' => $valMin !== null ? rtrim(rtrim(number_format((float)$valMin, 8, '.', ''), '0'), '.') : null,
                        'max_spec' => $valMax !== null ? rtrim(rtrim(number_format((float)$valMax, 8, '.', ''), '0'), '.') : null,
                        'measurements' => [['value' => null], ['value' => null], ['value' => null], ['value' => null]], // 4 ช่องกรอกค่า
                        'reading' => null,
                        'error' => null,
                        'Judgement' => null,
                        'grade' => null,
                    ];
                }

                if (!empty($readingItem['specs'])) {
                    $readings[] = $readingItem;
                }
            }

            $set('calibration_type', 'ThreadPlugGauge');
            $set('calibration_data.calibration_type', 'ThreadPlugGauge');
            $set('calibration_data.readings', $readings);
        }
    }

    // 🔥 คำนวณผลลัพธ์ของแต่ละ Spec (Thread Plug Gauge)
    protected static function calculateSpecResultThreadPlugGauge(Get $get, Set $set)
    {
        $readings = $get('../../../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../../../instrument_id');

        if (!$instrumentId || empty($readings)) return;

        // ตรวจสอบว่ากรอกครบทุก spec ทุก point หรือยัง
        $allFilled = true;
        foreach ($readings as $reading) {
            $specs = $reading['specs'] ?? [];
            foreach ($specs as $spec) {
                $measurements = $spec['measurements'] ?? [];
                if (empty($measurements)) {
                    $allFilled = false;
                    break 2;
                }
                foreach ($measurements as $m) {
                    if (!isset($m['value']) || $m['value'] === '' || $m['value'] === null) {
                        $allFilled = false;
                        break 3;
                    }
                }
            }
        }

        if (!$allFilled) return;

        // ถ้ากรอกครบแล้ว → คำนวณทั้งหมด
        self::calculateAllSpecsThreadPlugGauge($get, $set);
    }

    // 🔥 คำนวณทุก Spec ทุก Point (Thread Plug Gauge)
    protected static function calculateAllSpecsThreadPlugGauge(Get $get, Set $set)
    {
        $readings = $get('../../../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../../../instrument_id');

        if (!$instrumentId || empty($readings)) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $percentAdj = (float) ($instrument->percent_adj ?? 10);
        $allGrades = [];

        // คำนวณแต่ละ Point
        foreach ($readings as $pointIndex => $reading) {
            $trend = $reading['trend'] ?? 'Smaller';
            $specs = $reading['specs'] ?? [];

            foreach ($specs as $specIndex => $spec) {
                // คำนวณค่าเฉลี่ยจาก measurements
                $measurements = $spec['measurements'] ?? [];
                $values = collect($measurements)
                    ->pluck('value')
                    ->filter(fn ($v) => !is_null($v) && $v !== '' && is_numeric($v))
                    ->map(fn ($v) => (float) $v);

                if ($values->isEmpty()) continue;

                $readingValue = $values->avg();
                $formattedAvg = rtrim(rtrim(number_format($readingValue, 6, '.', ''), '0'), '.');

                $minSpec = (float) ($spec['min_spec'] ?? 0);
                $maxSpec = (float) ($spec['max_spec'] ?? 0);

                $range = $maxSpec - $minSpec;
                $tolerance = $range * ($percentAdj / 100);

                $grade = 'C';
                $error = 0;
                $judgement = 'Reject';

                if ($trend === 'Smaller') {
                    $error = $readingValue - $minSpec;
                    $thresholdA = $minSpec + $tolerance;

                    if ($readingValue < $minSpec || $readingValue > $maxSpec) {
                        $grade = 'C';
                    } elseif ($readingValue >= $thresholdA && $readingValue <= $maxSpec) {
                        $grade = 'A';
                    } else {
                        $grade = 'B';
                    }
                } elseif ($trend === 'Bigger') {
                    $error = $readingValue - $maxSpec;
                    $thresholdA = $maxSpec - $tolerance;

                    if ($readingValue < $minSpec || $readingValue > $maxSpec) {
                        $grade = 'C';
                    } elseif ($readingValue <= $thresholdA && $readingValue >= $minSpec) {
                        $grade = 'A';
                    } else {
                        $grade = 'B';
                    }
                }

                $judgement = ($grade === 'C') ? 'Reject' : 'Pass';
                $allGrades[] = $grade;

                // Set ค่าผลลัพธ์
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.reading", $formattedAvg);
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.error", number_format($error, 4));
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.Judgement", $judgement);
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.grade", $grade);
            }
        }

        // คำนวณ Overall Status
        self::calculateOverallStatusThreadPlugGauge($get, $set, $allGrades);
    }

    // 🔥 คำนวณสถานะรวม (Thread Plug Gauge)
    protected static function calculateOverallStatusThreadPlugGauge(Get $get, Set $set, array $allGrades)
    {
        $instrumentId = $get('../../../../../../../instrument_id');
        if (!$instrumentId || empty($allGrades)) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $grades = collect($allGrades)->filter();

        $level = 'A';
        if ($grades->contains('C')) {
            $level = 'C';
        } elseif ($grades->contains('B')) {
            $level = 'B';
        }

        $status = $grades->contains('C') ? 'Reject' : 'Pass';

        $set('../../../../../../../result_status', $status);
        $set('../../../../../../../cal_level', $level);

        // Update Next Cal Date
        $calDate = $get('../../../../../../../cal_date');
        if ($calDate) {
            $nextDate = match($level) {
                'A' => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
                'B' => \Carbon\Carbon::parse($calDate)->addMonth()->endOfMonth(),
                'C' => null,
                default => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
            };

            if ($nextDate) {
                $set('../../../../../../../next_cal_date', $nextDate->format('Y-m-d'));
            }
        }
    }

    // 🔥 Logic สำหรับ Thread Ring Gauge - ใช้ 'วัดเกลียว' เท่านั้น (no calculation)
    protected static function onInstrumentSelectedThreadRingGauge($state, Set $set, Get $get)
    {
        if (!$state) return;

        $instrument = Instrument::with('toolType', 'department')->find($state);
        if (!$instrument) return;

        $set('next_cal_date', now()->addMonths($instrument->cal_freq_months ?? 6));
        $set('instrument_size', $instrument->toolType?->size ?? '-');
        $set('instrument_name', $instrument->toolType?->name ?? '-');
        $set('instrument_department', $instrument->department?->name ?? '-');
        $set('instrument_serial', $instrument->serial_no ?? '-');
        $set('instrument_drawing', $instrument->toolType?->drawing_no ?? '-');

        if ($instrument->toolType && $instrument->toolType->dimension_specs) {
            $dimensionSpecs = $instrument->toolType->dimension_specs;
            $readings = [];

            foreach ($dimensionSpecs as $spec) {
                $point = $spec['point'] ?? null;
                if (!$point) continue;

                // ค้นหา spec ที่เป็น 'วัดเกลียว'
                $allSpecs = $spec['specs'] ?? [];
                foreach ($allSpecs as $specItem) {
                    $label = $specItem['label'] ?? '';

                    if ($label === 'วัดเกลียว') {
                        $readings[] = [
                            'point' => $point,
                            'label' => 'วัดเกลียว',
                            'standard_value' => $specItem['standard_value'] ?? '-',
                            'trend' => $spec['trend'] ?? '-',
                            'measurement' => null,
                            'result' => null,
                        ];
                    }
                }
            }

            $set('calibration_type', 'ThreadRingGauge');
            $set('calibration_data.calibration_type', 'ThreadRingGauge');
            $set('calibration_data.readings', $readings);
        }
    }

    // 🔥 Logic สำหรับ Thread Plug Gauge Fit & Wear - รวม Major, Pitch, Plug ไว้ใน Point เดียวกัน
    protected static function onInstrumentSelectedThreadPlugGaugeFitWear($state, Set $set, Get $get)
    {
        if (!$state) return;

        $instrument = Instrument::with('toolType', 'department')->find($state);
        if (!$instrument) return;

        $set('next_cal_date', now()->addMonths($instrument->cal_freq_months ?? 6));
        $set('instrument_size', $instrument->toolType?->size ?? '-');
        $set('instrument_name', $instrument->toolType?->name ?? '-');
        $set('instrument_department', $instrument->department?->name ?? '-');
        $set('instrument_serial', $instrument->serial_no ?? '-');
        $set('instrument_drawing', $instrument->toolType?->drawing_no ?? '-');

        if ($instrument->toolType && $instrument->toolType->dimension_specs) {
            $dimensionSpecs = $instrument->toolType->dimension_specs;
            $readings = [];

            foreach ($dimensionSpecs as $pointIndex => $spec) {
                $point = $spec['point'] ?? null;
                if (!$point) continue;

                $readingItem = [
                    'point' => $point,
                    'trend' => $spec['trend'] ?? 'Smaller',
                    'specs' => [],
                ];

                $allSpecs = $spec['specs'] ?? [];
                foreach ($allSpecs as $specItem) {
                    $label = $specItem['label'] ?? '';

                    if ($label === 'STD') continue;

                    $valMin = $specItem['min'] ?? null;
                    $valMax = $specItem['max'] ?? null;

                    $readingItem['specs'][] = [
                        'label' => $label,
                        'min_spec' => $valMin !== null ? rtrim(rtrim(number_format((float)$valMin, 8, '.', ''), '0'), '.') : null,
                        'max_spec' => $valMax !== null ? rtrim(rtrim(number_format((float)$valMax, 8, '.', ''), '0'), '.') : null,
                        'measurements' => [['value' => null], ['value' => null], ['value' => null], ['value' => null]],
                        'reading' => null,
                        'error' => null,
                        'Judgement' => null,
                        'grade' => null,
                    ];
                }

                if (!empty($readingItem['specs'])) {
                    $readings[] = $readingItem;
                }
            }

            $set('calibration_type', 'ThreadPlugGaugeFitWear');
            $set('calibration_data.calibration_type', 'ThreadPlugGaugeFitWear');
            $set('calibration_data.readings', $readings);
        }
    }

    // 🔥 คำนวณผลลัพธ์ของแต่ละ Spec (Thread Plug Gauge Fit & Wear)
    protected static function calculateSpecResultThreadPlugGaugeFitWear(Get $get, Set $set)
    {
        $readings = $get('../../../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../../../instrument_id');

        if (!$instrumentId || empty($readings)) return;

        $allFilled = true;
        foreach ($readings as $reading) {
            $specs = $reading['specs'] ?? [];
            foreach ($specs as $spec) {
                $measurements = $spec['measurements'] ?? [];
                if (empty($measurements)) {
                    $allFilled = false;
                    break 2;
                }
                foreach ($measurements as $m) {
                    if (!isset($m['value']) || $m['value'] === '' || $m['value'] === null) {
                        $allFilled = false;
                        break 3;
                    }
                }
            }
        }

        if (!$allFilled) return;

        self::calculateAllSpecsThreadPlugGaugeFitWear($get, $set);
    }

    // 🔥 คำนวณทุก Spec ทุก Point (Thread Plug Gauge Fit & Wear)
    protected static function calculateAllSpecsThreadPlugGaugeFitWear(Get $get, Set $set)
    {
        $readings = $get('../../../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../../../instrument_id');

        if (!$instrumentId || empty($readings)) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $percentAdj = (float) ($instrument->percent_adj ?? 10);
        $allGrades = [];

        foreach ($readings as $pointIndex => $reading) {
            $trend = $reading['trend'] ?? 'Smaller';
            $specs = $reading['specs'] ?? [];

            foreach ($specs as $specIndex => $spec) {
                $measurements = $spec['measurements'] ?? [];
                $values = collect($measurements)
                    ->pluck('value')
                    ->filter(fn ($v) => !is_null($v) && $v !== '' && is_numeric($v))
                    ->map(fn ($v) => (float) $v);

                if ($values->isEmpty()) continue;

                $readingValue = $values->avg();
                $formattedAvg = rtrim(rtrim(number_format($readingValue, 6, '.', ''), '0'), '.');

                $minSpec = (float) ($spec['min_spec'] ?? 0);
                $maxSpec = (float) ($spec['max_spec'] ?? 0);

                $range = $maxSpec - $minSpec;
                $tolerance = $range * ($percentAdj / 100);

                $grade = 'C';
                $error = 0;
                $judgement = 'Reject';

                if ($trend === 'Smaller') {
                    $error = $readingValue - $minSpec;
                    $thresholdA = $minSpec + $tolerance;

                    if ($readingValue < $minSpec || $readingValue > $maxSpec) {
                        $grade = 'C';
                    } elseif ($readingValue >= $thresholdA && $readingValue <= $maxSpec) {
                        $grade = 'A';
                    } else {
                        $grade = 'B';
                    }
                } elseif ($trend === 'Bigger') {
                    $error = $readingValue - $maxSpec;
                    $thresholdA = $maxSpec - $tolerance;

                    if ($readingValue < $minSpec || $readingValue > $maxSpec) {
                        $grade = 'C';
                    } elseif ($readingValue <= $thresholdA && $readingValue >= $minSpec) {
                        $grade = 'A';
                    } else {
                        $grade = 'B';
                    }
                }

                $judgement = ($grade === 'C') ? 'Reject' : 'Pass';
                $allGrades[] = $grade;

                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.reading", $formattedAvg);
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.error", number_format($error, 4));
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.Judgement", $judgement);
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.grade", $grade);
            }
        }

        self::calculateOverallStatusThreadPlugGaugeFitWear($get, $set, $allGrades);
    }

    // 🔥 คำนวณสถานะรวม (Thread Plug Gauge Fit & Wear)
    protected static function calculateOverallStatusThreadPlugGaugeFitWear(Get $get, Set $set, array $allGrades)
    {
        $instrumentId = $get('../../../../../../../instrument_id');
        if (!$instrumentId || empty($allGrades)) return;

        $instrument = Instrument::find($instrumentId);
        if (!$instrument) return;

        $grades = collect($allGrades)->filter();

        $level = 'A';
        if ($grades->contains('C')) {
            $level = 'C';
        } elseif ($grades->contains('B')) {
            $level = 'B';
        }

        $status = $grades->contains('C') ? 'Reject' : 'Pass';

        $set('../../../../../../../result_status', $status);
        $set('../../../../../../../cal_level', $level);

        $calDate = $get('../../../../../../../cal_date');
        if ($calDate) {
            $nextDate = match($level) {
                'A' => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
                'B' => \Carbon\Carbon::parse($calDate)->addMonth()->endOfMonth(),
                'C' => null,
                default => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
            };

            if ($nextDate) {
                $set('../../../../../../../next_cal_date', $nextDate->format('Y-m-d'));
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->deferLoading()
            ->columns([
                TextColumn::make('instrument.code_no')
                    ->label('ID Code Instrument')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('instrument.toolType.name')
                    ->label('Type Name')
                    ->searchable(),

                TextColumn::make('cal_date')
                    ->label('Cal Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('next_cal_date')
                    ->label('Next Cal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('result_status')
                    ->label('ผลการ Cal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pass' => 'success',
                        'Reject' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('cal_level')
                    ->label('Level')
                    ->color(fn (string $state): string => match ($state) {
                        'A' => 'success',
                        'B' => 'warning',
                        'C' => 'danger',
                        default => 'gray',
                    })
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('calibration_type')
                    ->label('ประเภทการสอบเทียบ')
                    ->options(collect(self::$gaugeTypes)->mapWithKeys(fn ($v, $k) => [$k => $v['label']])->toArray())
                    ->searchable()
                    ->columnSpan(2)
                    ->native(false)
                    ->preload(),
                Tables\Filters\Filter::make('cal_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('จากวันที่'),
                        Forms\Components\DatePicker::make('until')
                            ->label('ถึงวันที่'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('cal_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('cal_date', '<=', $date));
                    }),
                Tables\Filters\SelectFilter::make('result_status')
                    ->label('ผลการ Cal')
                    ->options([
                        'Pass' => 'Pass',
                        'Reject' => 'Reject',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('cal_level')
                    ->label('Level')
                    ->options([
                        'A' => 'Level A',
                        'B' => 'Level B',
                        'C' => 'Level C',
                    ])
                    ->native(false),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->color('warning'),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGaugeCalibrations::route('/'),
            'create' => Pages\CreateGaugeCalibration::route('/create'),
            'view' => Pages\ViewGaugeCalibration::route('/{record}'),
            'edit' => Pages\EditGaugeCalibration::route('/{record}/edit'),
        ];
    }
}
