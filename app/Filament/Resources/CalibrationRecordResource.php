<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalibrationRecordResource\Pages;
use App\Models\CalibrationRecord;
use App\Models\Instrument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions;

class CalibrationRecordResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Instrument Calibration';
    protected static ?string $modelLabel = 'Calibration Record';
    protected static ?string $navigationGroup = 'Instrument Cal Report & Data';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'instrument-calibration';

    /**
     * 🔥 Filter ข้อมูล: แสดงเฉพาะ -10- (เช่น 8-10-%, 6-10-%, 1-10-% ฯลฯ)
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('instrument', function ($q) {
                $q->where('code_no', 'LIKE', '%-10-%');
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
                                Select::make('instrument_id')
                                    ->label('เลือกเครื่องมือ (Code No)')
                                    ->searchable()
                                    ->required()
                                    ->placeholder('รหัสเครื่องมือ หรือ รหัสประเภทเครื่องมือ')
                                    ->columnSpan(2)
                                    ->reactive()
                                    ->getSearchResultsUsing(function (string $search) {
                                        return \App\Models\Instrument::query()
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
                                        $instrument = \App\Models\Instrument::find($value);
                                        return $instrument ? "{$instrument->code_no} - {$instrument->name}" : '';
                                    })
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!$state) return;
                                
                                        $instrument = Instrument::with('toolType', 'department')->find($state);
                                        if (!$instrument) return;
                                
                                        $set('next_cal_date', now()->addMonths($instrument->cal_freq_months ?? 6));
                                        $set('instrument_size', $instrument->toolType?->size ?? '-');
                                        $set('instrument_name', $instrument->toolType?->name ?? '-');
                                        $set('instrument_department', $instrument->department?->name ?? '-');
                                        $set('instrument_serial', $instrument->serial_no ?? '-');
                                        $set('instrument_drawing', $instrument->toolType?->drawing_no ?? '-');
                                        
                                        // 🔥 ดึง criteria_1, criteria_2 จาก ToolType
                                        $criteriaUnit = $instrument->toolType?->criteria_unit ?? [];
                                        $criteria1 = '0.00';
                                        $criteria2 = '-0.00';
                                        $unit = 'mm.';
                                        
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
                                        
                                        $set('criteria_1', $criteria1);
                                        $set('criteria_2', $criteria2);
                                        $set('criteria_unit', $unit);
                                        
                                        // Load dimension specs - ดึงเฉพาะ S และ Cs
                                        if ($instrument->toolType && $instrument->toolType->dimension_specs) {
                                            $dimensionSpecs = $instrument->toolType->dimension_specs;
                                            $readings = [];
                                            $readingsInner = []; // 🔥 สำหรับ Section 2
                                            $readingsDepth = []; // 🔥 สำหรับ Section 3
                                            $readingsParallelism = []; // 🔥 สำหรับ Section 4
                                    
                                            foreach ($dimensionSpecs as $spec) {
                                                $point = $spec['point'] ?? null;
                                                if (!$point) continue;
                                                
                                                $csValue = 0;
                                                $sSpecs = [];
                                                
                                                // รวบรวม S และ Cs specs
                                                if (isset($spec['specs']) && is_array($spec['specs'])) {
                                                    foreach ($spec['specs'] as $specItem) {
                                                        $label = $specItem['label'] ?? '';
                                                        
                                                        if ($label === 'S') {
                                                            $sSpecs[] = [
                                                                'label' => 'S',
                                                                's_value' => $specItem['s_std'] ?? null,
                                                                'measurements' => [['value' => null], ['value' => null], ['value' => null], ['value' => null]],
                                                                'average' => null,
                                                                'sd' => null,
                                                            ];
                                                        } elseif ($label === 'Cs') {
                                                            $csValue = $specItem['cs_std'] ?? 0;
                                                        }
                                                    }
                                                }
                                                
                                                // Section 1: สเกลวัดนอก (4 ค่าวัด)
                                                if (!empty($sSpecs)) {
                                                    $readings[] = [
                                                        'point' => $point,
                                                        'cs_value' => $csValue,
                                                        'specs' => $sSpecs,
                                                    ];
                                                    
                                                    // Section 2: สเกลวัดใน (2 ค่าวัด)
                                                    $sSpecsInner = [];
                                                    foreach ($sSpecs as $sSpec) {
                                                        $sSpecsInner[] = [
                                                            'label' => 'S',
                                                            's_value' => $sSpec['s_value'],
                                                            'measurements' => [['value' => null], ['value' => null]], // 2 ค่า
                                                            'average' => null,
                                                            'sd' => null,
                                                        ];
                                                    }
                                                    
                                                    $readingsInner[] = [
                                                        'point' => $point,
                                                        'cs_value' => $csValue,
                                                        'specs' => $sSpecsInner,
                                                    ];
                                                    
                                                    // Section 3: สเกลวัดลึก (2 ค่าวัด)
                                                    $sSpecsDepth = [];
                                                    foreach ($sSpecs as $sSpec) {
                                                        $sSpecsDepth[] = [
                                                            'label' => 'S',
                                                            's_value' => $sSpec['s_value'],
                                                            'measurements' => [['value' => null], ['value' => null]], // 2 ค่า
                                                            'average' => null,
                                                            'sd' => null,
                                                        ];
                                                    }
                                                    
                                                    $readingsDepth[] = [
                                                        'point' => $point,
                                                        'cs_value' => $csValue,
                                                        'specs' => $sSpecsDepth,
                                                    ];
                                                    
                                                    // Section 4: ความขนาน (ใช้แต่ละ S value)
                                                    foreach ($sSpecs as $sSpec) {
                                                        $readingsParallelism[] = [
                                                            'point' => $point,
                                                            's_value' => $sSpec['s_value'],
                                                            'position_start' => null,
                                                            'position_middle' => null,
                                                            'position_end' => null,
                                                            'parallelism' => null,
                                                            'Judgement' => null,
                                                            'level' => null,
                                                        ];
                                                    }
                                                }
                                            }
                                    
                                            $set('calibration_data.readings', $readings);
                                            $set('calibration_data.readings_inner', $readingsInner);
                                            $set('calibration_data.readings_depth', $readingsDepth);
                                            $set('calibration_data.readings_parallelism', $readingsParallelism);
                                        }
                                    }),

                                DatePicker::make('cal_date')
                                    ->label('วันที่สอบเทียบ')
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
                                    ->columnSpan(2)
                                    ->dehydrated(false),

                                TextInput::make('instrument_drawing')
                                    ->label('Drawing No.')
                                    ->disabled()
                                    ->dehydrated(false),    

                                TextInput::make('instrument_size')
                                    ->label('Size')
                                    ->disabled()
                                    ->columnSpan(2)
                                    ->dehydrated(false),
                                
                                TextInput::make('instrument_department')
                                    ->label('แผนก')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('criteria_1')
                                    ->label('เกณฑ์ค่าบวก (Criteria +)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->suffix(fn (Get $get) => $get('criteria_unit') ?? 'mm.')
                                    ->extraAttributes([
                                        'style' => 'text-align: center;'
                                    ]),
                                TextInput::make('criteria_2')
                                    ->label('เกณฑ์ค่าลบ (Criteria -)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->suffix(fn (Get $get) => $get('criteria_unit') ?? 'mm.')
                                    ->extraAttributes([
                                        'style' => 'text-align: center;'
                                    ]),
                                Forms\Components\Hidden::make('criteria_unit')->dehydrated(false),
                                
                                TextInput::make('instrument_serial')
                                    ->label('Serial No.')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                            ]),
                            Grid::make(3)->schema([
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
                                
                                $instrument = \App\Models\Instrument::with('toolType.masters')->find($instrumentId);
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

                Section::make('1. ตรวจสอบความถูกต้องของสเกล')
                    ->description('กรอกค่าตามจุดตรวจสอบ')
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?'))
                            ->schema([
                                // Hidden fields for Point level
                                Forms\Components\Hidden::make('point')->dehydrated(),
                                Forms\Components\Hidden::make('cs_value')->dehydrated(),

                                // 🔥 Nested Repeater สำหรับ specs (S values)
                                Repeater::make('specs')
                                    ->label('รายการ Specs')
                                    ->schema([
                                        // Hidden fields
                                        Forms\Components\Hidden::make('label')->dehydrated(),
                                        Forms\Components\Hidden::make('s_value')->dehydrated(),

                                        // Spec Info Display
                                        Placeholder::make('spec_info')
                                            ->label('')
                                            ->content(fn (Get $get) => view('filament.components.instrument-spec-info', [
                                                'label' => $get('label'),
                                                'sValue' => $get('s_value'),
                                                'csValue' => $get('../../cs_value'),
                                            ])),

                                        // 🔥 Nested Repeater สำหรับหลายค่าวัด
                                        Repeater::make('measurements')
                                            ->hiddenLabel()
                                            ->schema([
                                                TextInput::make('value')
                                                    ->hiddenLabel()
                                                    ->numeric()
                                                    ->placeholder('0.00')
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        self::calculateSpecResult($get, $set);
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
                                            ->itemLabel(fn (): string => 'ค่าที่อ่านได้จากสเกล'),

                                        // 🔥 Result Section
                                        Section::make('ผลลัพธ์')
                                            ->compact()
                                            ->schema([
                                                Grid::make(5)->schema([
                                                    TextInput::make('average')
                                                        ->label('ค่าเฉลี่ยที่อ่านได้จากสเกล X̄')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 700; text-align: center; background-color: #e0f2fe; color: #0369a1;'
                                                        ]),

                                                    TextInput::make('sd')
                                                        ->label('ค่าเบี่ยงเบนมาตรฐาน (SD)')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 600; text-align: center;'
                                                        ]),

                                                    TextInput::make('correction')
                                                        ->label('ค่าแก้สเกล S+Cs-X̄')
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

                                                    Select::make('level')
                                                        ->label('Level')
                                                        ->disabled()
                                                        ->options([
                                                            'A' => 'Level A',
                                                            'B' => 'Level B',
                                                            'C' => 'Level C',
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
                                    ->itemLabel(fn (array $state): ?string => 'S = ' . ($state['s_value'] ?? '?')),
                            ])
                            ->collapsible()
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->cloneable(false)
                            ->defaultItems(0)
                            ->columns(1),
                    ]),

                // 🔥 Section 2: ตรวจสอบความถูกต้องของสเกลวัดใน
                Section::make('2. ตรวจสอบความถูกต้องของสเกลวัดใน')
                    ->description('กรอกค่าตามจุดตรวจสอบ - สเกลวัดใน')
                    ->schema([
                        Repeater::make('calibration_data.readings_inner')
                            ->label('รายการจุดตรวจสอบ (สเกลวัดใน)')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?'))
                            ->schema([
                                // Hidden fields for Point level
                                Forms\Components\Hidden::make('point')->dehydrated(),
                                Forms\Components\Hidden::make('cs_value')->dehydrated(),

                                // 🔥 Nested Repeater สำหรับ specs (S values)
                                Repeater::make('specs')
                                    ->label('รายการ Specs')
                                    ->schema([
                                        // Hidden fields
                                        Forms\Components\Hidden::make('label')->dehydrated(),
                                        Forms\Components\Hidden::make('s_value')->dehydrated(),

                                        // Spec Info Display
                                        Placeholder::make('spec_info')
                                            ->label('')
                                            ->content(fn (Get $get) => view('filament.components.instrument-spec-info', [
                                                'label' => $get('label'),
                                                'sValue' => $get('s_value'),
                                                'csValue' => $get('../../cs_value'),
                                            ])),

                                        // 🔥 Nested Repeater สำหรับหลายค่าวัด (2 ค่าตามรูป)
                                        Repeater::make('measurements')
                                            ->hiddenLabel()
                                            ->schema([
                                                TextInput::make('value')
                                                    ->hiddenLabel()
                                                    ->numeric()
                                                    ->placeholder('0.00')
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        self::calculateInnerSpecResult($get, $set);
                                                    })
                                                    ->extraAttributes([
                                                        'style' => 'font-family: monospace; text-align: center; font-weight: 600;'
                                                    ]),
                                            ])
                                            ->addActionLabel('+ เพิ่มค่าวัด')
                                            ->reorderable(false)
                                            ->cloneable(false)
                                            ->defaultItems(2)
                                            ->minItems(1)
                                            ->grid(4)
                                            ->itemLabel(fn (): string => 'ค่าที่อ่านได้จากสเกล'),

                                        // 🔥 Result Section
                                        Section::make('ผลลัพธ์')
                                            ->compact()
                                            ->schema([
                                                Grid::make(5)->schema([
                                                    TextInput::make('average')
                                                        ->label('ค่าเฉลี่ยที่อ่านได้จากสเกล X̄')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 700; text-align: center; background-color: #e0f2fe; color: #0369a1;'
                                                        ]),

                                                    TextInput::make('sd')
                                                        ->label('ค่าเบี่ยงเบนมาตรฐาน (SD)')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 600; text-align: center;'
                                                        ]),

                                                    TextInput::make('correction')
                                                        ->label('ค่าแก้สเกล S+Cs-X̄')
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

                                                    Select::make('level')
                                                        ->label('Level')
                                                        ->disabled()
                                                        ->options([
                                                            'A' => 'Level A',
                                                            'B' => 'Level B',
                                                            'C' => 'Level C',
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
                                    ->itemLabel(fn (array $state): ?string => 'S = ' . ($state['s_value'] ?? '?')),
                            ])
                            ->collapsible()
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->cloneable(false)
                            ->defaultItems(0)
                            ->columns(1),
                    ]),

                // 🔥 Section 3: ตรวจสอบความถูกต้องของสเกลวัดลึก
                Section::make('3. ตรวจสอบความถูกต้องของสเกลวัดลึก')
                    ->description('กรอกค่าตามจุดตรวจสอบ - สเกลวัดลึก')
                    ->schema([
                        Repeater::make('calibration_data.readings_depth')
                            ->label('รายการจุดตรวจสอบ (สเกลวัดลึก)')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?'))
                            ->schema([
                                // Hidden fields for Point level
                                Forms\Components\Hidden::make('point')->dehydrated(),
                                Forms\Components\Hidden::make('cs_value')->dehydrated(),

                                // 🔥 Nested Repeater สำหรับ specs (S values)
                                Repeater::make('specs')
                                    ->label('รายการ Specs')
                                    ->schema([
                                        // Hidden fields
                                        Forms\Components\Hidden::make('label')->dehydrated(),
                                        Forms\Components\Hidden::make('s_value')->dehydrated(),

                                        // Spec Info Display
                                        Placeholder::make('spec_info')
                                            ->label('')
                                            ->content(fn (Get $get) => view('filament.components.instrument-spec-info', [
                                                'label' => $get('label'),
                                                'sValue' => $get('s_value'),
                                                'csValue' => $get('../../cs_value'),
                                            ])),

                                        // 🔥 Nested Repeater สำหรับหลายค่าวัด (2 ค่าตามรูป)
                                        Repeater::make('measurements')
                                            ->hiddenLabel()
                                            ->schema([
                                                TextInput::make('value')
                                                    ->hiddenLabel()
                                                    ->numeric()
                                                    ->placeholder('0.00')
                                                    ->live(debounce: 500)
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        self::calculateDepthSpecResult($get, $set);
                                                    })
                                                    ->extraAttributes([
                                                        'style' => 'font-family: monospace; text-align: center; font-weight: 600;'
                                                    ]),
                                            ])
                                            ->addActionLabel('+ เพิ่มค่าวัด')
                                            ->reorderable(false)
                                            ->cloneable(false)
                                            ->defaultItems(2)
                                            ->minItems(1)
                                            ->grid(4)
                                            ->itemLabel(fn (): string => 'ค่าที่อ่านได้จากสเกล'),

                                        // 🔥 Result Section
                                        Section::make('ผลลัพธ์')
                                            ->compact()
                                            ->schema([
                                                Grid::make(5)->schema([
                                                    TextInput::make('average')
                                                        ->label('ค่าเฉลี่ยที่อ่านได้จากสเกล X̄')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 700; text-align: center; background-color: #e0f2fe; color: #0369a1;'
                                                        ]),

                                                    TextInput::make('sd')
                                                        ->label('ค่าเบี่ยงเบนมาตรฐาน (SD)')
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->extraAttributes([
                                                            'style' => 'font-family: monospace; font-weight: 600; text-align: center;'
                                                        ]),

                                                    TextInput::make('correction')
                                                        ->label('ค่าแก้สเกล S+Cs-X̄')
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

                                                    Select::make('level')
                                                        ->label('Level')
                                                        ->disabled()
                                                        ->options([
                                                            'A' => 'Level A',
                                                            'B' => 'Level B',
                                                            'C' => 'Level C',
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
                                    ->itemLabel(fn (array $state): ?string => 'S = ' . ($state['s_value'] ?? '?')),
                            ])
                            ->collapsible()
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->cloneable(false)
                            ->defaultItems(0)
                            ->columns(1),
                    ]),

                // 🔥 Section 4: ตรวจสอบความเรียบและความขนาน
                Section::make('4. ตรวจสอบความเรียบและความขนาน')
                    ->description('ตรวจสอบความเรียบของพื้นผิวและความขนานของขากรรไกร')
                    ->schema([
                        // ตรวจสอบความเรียบ (TextInput)
                        TextInput::make('calibration_data.flatness_check')
                            ->label('ตรวจสอบความเรียบ')
                            ->placeholder('กรอกผลการตรวจสอบ เช่น ไม่มีแสงรอดผ่าน')
                            ->dehydrated()
                            ->extraAttributes([
                                'style' => 'font-family: monospace; font-weight: 600;'
                            ]),

                        // Repeater สำหรับวัดความขนาน
                        Repeater::make('calibration_data.readings_parallelism')
                            ->label('รายการตรวจสอบความขนาน')
                            ->itemLabel(fn (array $state): ?string => 'S = ' . ($state['s_value'] ?? '?'))
                            ->schema([
                                Forms\Components\Hidden::make('point')->dehydrated(),
                                Forms\Components\Hidden::make('s_value')->dehydrated(),

                                Grid::make(6)->schema([

                                    // ตำแหน่งต้น
                                    TextInput::make('position_start')
                                        ->label('ตำแหน่งต้น')
                                        ->numeric()
                                        ->placeholder('0.00')
                                        ->dehydrated()
                                        ->extraAttributes([
                                            'style' => 'font-family: monospace; text-align: center; font-weight: 600;'
                                        ]),

                                    // ตำแหน่งกลาง
                                    TextInput::make('position_middle')
                                        ->label('ตำแหน่งกลาง')
                                        ->numeric()
                                        ->placeholder('0.00')
                                        ->dehydrated()
                                        ->extraAttributes([
                                            'style' => 'font-family: monospace; text-align: center; font-weight: 600;'
                                        ]),

                                    // ตำแหน่งปลาย
                                    TextInput::make('position_end')
                                        ->label('ตำแหน่งปลาย')
                                        ->numeric()
                                        ->placeholder('0.00')
                                        ->dehydrated()
                                        ->extraAttributes([
                                            'style' => 'font-family: monospace; text-align: center; font-weight: 600;'
                                        ]),

                                    // ความขนาน (ช่องให้กรอก)
                                    TextInput::make('parallelism')
                                        ->label('ความขนาน')
                                        ->numeric()
                                        ->placeholder('0.00')
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            self::calculateParallelism($get, $set);
                                        })
                                        ->dehydrated()
                                        ->extraAttributes([
                                            'style' => 'font-family: monospace; font-weight: 700; text-align: center;'
                                        ]),

                                    // Judgement
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

                                    // Level
                                    Select::make('level')
                                        ->label('Level')
                                        ->disabled()
                                        ->options([
                                            'A' => 'Level A',
                                            'B' => 'Level B',
                                            'C' => 'Level C',
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
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Get $get) {
                                    $calDate = $get('cal_date');
                                    $instrumentId = $get('instrument_id');
                                    
                                    if (!$calDate || !$state || !$instrumentId) return;
                                    
                                    $instrument = \App\Models\Instrument::find($instrumentId);
                                    if (!$instrument) return;
                                    
                                    if (empty($instrument->cal_freq_months) || $instrument->cal_freq_months == 0) {
                                        $calDateCarbon = \Carbon\Carbon::parse($calDate);
                                        $nextDateCarbon = \Carbon\Carbon::parse($state);
                                        
                                        $diffMonths = (int) round($calDateCarbon->diffInMonths($nextDateCarbon));
                                        
                                        if ($diffMonths > 0) {
                                            $instrument->update(['cal_freq_months' => $diffMonths]);
                                            
                                            \Filament\Notifications\Notification::make()
                                                ->title('อัปเดตความถี่สำเร็จ')
                                                ->body("บันทึกความถี่ {$diffMonths} เดือน ให้กับ {$instrument->code_no}")
                                                ->success()
                                                ->send();
                                        }
                                    }
                                }),
                            
                            TextInput::make('remark')
                                ->label('หมายเหตุ (Remark)'),
                        ]),
                    ]),
            ]);
    }

    // 🔥 คำนวณผลลัพธ์ของแต่ละ Spec
    protected static function calculateSpecResult(Get $get, Set $set)
    {
        // 🔥 ลองหลาย path เพื่อดึง readings
        $readings = $get('../../../../../../../calibration_data.readings') 
            ?? $get('../../../../../../calibration_data.readings')
            ?? $get('../../../../../calibration_data.readings')
            ?? $get('../../../../calibration_data.readings')
            ?? [];
        
        $instrumentId = $get('../../../../../../../instrument_id')
            ?? $get('../../../../../../instrument_id')
            ?? $get('../../../../../instrument_id')
            ?? $get('../../../../instrument_id')
            ?? null;
        
        if (!$instrumentId || empty($readings)) return;
        
        // ตรวจสอบว่ากรอกครบทุก spec ทุก point หรือยัง
        $allFilled = true;
        $totalPoints = 0;
        $filledPoints = 0;
        
        foreach ($readings as $reading) {
            $specs = $reading['specs'] ?? [];
            foreach ($specs as $spec) {
                $totalPoints++;
                $measurements = $spec['measurements'] ?? [];
                
                if (empty($measurements)) {
                    $allFilled = false;
                    continue;
                }
                
                $specFilled = true;
                foreach ($measurements as $m) {
                    if (!isset($m['value']) || $m['value'] === '' || $m['value'] === null) {
                        $specFilled = false;
                        $allFilled = false;
                        break;
                    }
                }
                
                if ($specFilled) {
                    $filledPoints++;
                }
            }
        }
        
        // 🔥 คำนวณก็ต่อเมื่อกรอกครบทุก point ทุก spec
        if (!$allFilled || $filledPoints < $totalPoints) return;
        
        // ถ้ากรอกครบแล้ว → คำนวณทั้งหมด
        self::calculateAllSpecs($get, $set);
    }
    
    // 🔥 คำนวณทุก Spec ทุก Point
    protected static function calculateAllSpecs(Get $get, Set $set)
    {
        $readings = $get('../../../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../../../instrument_id');
        
        if (!$instrumentId || empty($readings)) return;
        
        $instrument = \App\Models\Instrument::with('toolType')->find($instrumentId);
        if (!$instrument) return;
        
        // 🔥 ดึง criteria_1 และ criteria_2 จาก ToolType
        $criteriaUnit = $instrument->toolType?->criteria_unit ?? [];
        $criteria1 = 0;
        $criteria2 = 0;
        
        if (is_array($criteriaUnit)) {
            foreach ($criteriaUnit as $item) {
                if (($item['index'] ?? 0) == 1) {
                    $criteria1 = abs((float) ($item['criteria_1'] ?? 0));
                    $criteria2 = abs((float) ($item['criteria_2'] ?? 0));
                    break;
                }
            }
        }
        
        $allLevels = [];
        
        // คำนวณแต่ละ Point
        foreach ($readings as $pointIndex => $reading) {
            $csValue = (float) ($reading['cs_value'] ?? 0);
            $specs = $reading['specs'] ?? [];
            
            foreach ($specs as $specIndex => $spec) {
                $sValue = (float) ($spec['s_value'] ?? 0);
                
                // 🔥 คำนวณค่าเฉลี่ยจาก measurements
                $measurements = $spec['measurements'] ?? [];
                $values = collect($measurements)
                    ->pluck('value')
                    ->filter(fn ($v) => !is_null($v) && $v !== '' && is_numeric($v))
                    ->map(fn ($v) => (float) $v);
                
                if ($values->isEmpty()) continue;
                
                $average = $values->avg();
                
                // คำนวณ SD
                $variance = $values->map(fn ($v) => pow($v - $average, 2))->sum();
                $sd = $values->count() > 1 ? sqrt($variance / $values->count()) : 0;
                
                // คำนวณค่าแก้สเกล = S + Cs - X̄
                $correction = $sValue + $csValue - $average;
                
                // 🔥 กำหนด Level โดยเทียบ (X̄ - S) กับ criteria
                // difference = ค่าที่วัดได้ - ค่ามาตรฐาน
                $difference = $average - $sValue;
                $absDiff = abs($difference);
                $level = 'A';
                
                // ใช้ epsilon สำหรับเปรียบเทียบ float (0.0001)
                $epsilon = 0.0001;
                
                // Logic:
                // - ถ้า |difference| < criteria → Grade A (อยู่ในเกณฑ์)
                // - ถ้า |difference| = criteria (±epsilon) → Grade B (ที่ขอบเกณฑ์)
                // - ถ้า |difference| > criteria → Grade C (เกินเกณฑ์)
                
                if ($difference > 0 && $criteria1 > 0) {
                    // ค่าบวก: เทียบกับ criteria_1
                    if ($absDiff > $criteria1 + $epsilon) {
                        $level = 'C';
                    } elseif (abs($absDiff - $criteria1) <= $epsilon) {
                        $level = 'B';
                    }
                } elseif ($difference < 0 && $criteria2 > 0) {
                    // ค่าลบ: เทียบกับ criteria_2
                    if ($absDiff > $criteria2 + $epsilon) {
                        $level = 'C';
                    } elseif (abs($absDiff - $criteria2) <= $epsilon) {
                        $level = 'B';
                    }
                }
                
                $judgement = ($level === 'C') ? 'Reject' : 'Pass';
                $allLevels[] = $level;
                
                // Set ค่าผลลัพธ์
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.average", number_format($average, 3));
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.sd", number_format($sd, 3));
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.correction", number_format($correction, 5));
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.Judgement", $judgement);
                $set("../../../../../../../calibration_data.readings.{$pointIndex}.specs.{$specIndex}.level", $level);
            }
        }
        
        // คำนวณ Overall Status
        self::calculateOverallStatus($get, $set, $allLevels);
    }
    
    // 🔥 คำนวณสถานะรวม
    protected static function calculateOverallStatus(Get $get, Set $set, array $allLevels)
    {
        $instrumentId = $get('../../../../../../../instrument_id');
        if (!$instrumentId || empty($allLevels)) return;
        
        $instrument = \App\Models\Instrument::find($instrumentId);
        if (!$instrument) return;
        
        $levels = collect($allLevels)->filter();
        
        $level = 'A';
        if ($levels->contains('C')) {
            $level = 'C';
        } elseif ($levels->contains('B')) {
            $level = 'B';
        }
        
        $status = $levels->contains('C') ? 'Reject' : 'Pass';
        
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

    // 🔥 คำนวณผลลัพธ์ของ Section 2 (สเกลวัดใน)
    protected static function calculateInnerSpecResult(Get $get, Set $set)
    {
        // 🔥 ลองหลาย path เพื่อดึง readings
        $readings = $get('../../../../../../../calibration_data.readings_inner') 
            ?? $get('../../../../../../calibration_data.readings_inner')
            ?? $get('../../../../../calibration_data.readings_inner')
            ?? $get('../../../../calibration_data.readings_inner')
            ?? [];
        
        $instrumentId = $get('../../../../../../../instrument_id')
            ?? $get('../../../../../../instrument_id')
            ?? $get('../../../../../instrument_id')
            ?? $get('../../../../instrument_id')
            ?? null;
        
        if (!$instrumentId || empty($readings)) return;
        
        // ตรวจสอบว่ากรอกครบทุก spec ทุก point หรือยัง
        $allFilled = true;
        $totalPoints = 0;
        $filledPoints = 0;
        
        foreach ($readings as $reading) {
            $specs = $reading['specs'] ?? [];
            foreach ($specs as $spec) {
                $totalPoints++;
                $measurements = $spec['measurements'] ?? [];
                
                if (empty($measurements)) {
                    $allFilled = false;
                    continue;
                }
                
                $specFilled = true;
                foreach ($measurements as $m) {
                    if (!isset($m['value']) || $m['value'] === '' || $m['value'] === null) {
                        $specFilled = false;
                        $allFilled = false;
                        break;
                    }
                }
                
                if ($specFilled) {
                    $filledPoints++;
                }
            }
        }
        
        // 🔥 คำนวณก็ต่อเมื่อกรอกครบทุก point ทุก spec
        if (!$allFilled || $filledPoints < $totalPoints) return;
        
        // ถ้ากรอกครบแล้ว → คำนวณทั้งหมด
        self::calculateAllInnerSpecs($get, $set);
    }

    // 🔥 คำนวณทุก Spec ทุก Point สำหรับ Section 2
    protected static function calculateAllInnerSpecs(Get $get, Set $set)
    {
        $readings = $get('../../../../../../../calibration_data.readings_inner') ?? [];
        $instrumentId = $get('../../../../../../../instrument_id');
        
        if (!$instrumentId || empty($readings)) return;
        
        $instrument = \App\Models\Instrument::with('toolType')->find($instrumentId);
        if (!$instrument) return;
        
        // 🔥 ดึง criteria_1 และ criteria_2 จาก ToolType
        $criteriaUnit = $instrument->toolType?->criteria_unit ?? [];
        $criteria1 = 0;
        $criteria2 = 0;
        
        if (is_array($criteriaUnit)) {
            foreach ($criteriaUnit as $item) {
                if (($item['index'] ?? 0) == 1) {
                    $criteria1 = abs((float) ($item['criteria_1'] ?? 0));
                    $criteria2 = abs((float) ($item['criteria_2'] ?? 0));
                    break;
                }
            }
        }
        
        $allLevels = [];
        
        // คำนวณแต่ละ Point
        foreach ($readings as $pointIndex => $reading) {
            $csValue = (float) ($reading['cs_value'] ?? 0);
            $specs = $reading['specs'] ?? [];
            
            foreach ($specs as $specIndex => $spec) {
                $sValue = (float) ($spec['s_value'] ?? 0);
                
                // 🔥 คำนวณค่าเฉลี่ยจาก measurements
                $measurements = $spec['measurements'] ?? [];
                $values = collect($measurements)
                    ->pluck('value')
                    ->filter(fn ($v) => !is_null($v) && $v !== '' && is_numeric($v))
                    ->map(fn ($v) => (float) $v);
                
                if ($values->isEmpty()) continue;
                
                $average = $values->avg();
                
                // คำนวณ SD
                $variance = $values->map(fn ($v) => pow($v - $average, 2))->sum();
                $sd = $values->count() > 1 ? sqrt($variance / $values->count()) : 0;
                
                // คำนวณค่าแก้สเกล = S + Cs - X̄
                $correction = $sValue + $csValue - $average;
                
                // 🔥 กำหนด Level โดยเทียบ (X̄ - S) กับ criteria
                // difference = ค่าที่วัดได้ - ค่ามาตรฐาน
                $difference = $average - $sValue;
                $absDiff = abs($difference);
                $level = 'A';
                
                // ใช้ epsilon สำหรับเปรียบเทียบ float (0.0001)
                $epsilon = 0.0001;
                
                // Logic:
                // - ถ้า |difference| < criteria → Grade A (อยู่ในเกณฑ์)
                // - ถ้า |difference| = criteria (±epsilon) → Grade B (ที่ขอบเกณฑ์)
                // - ถ้า |difference| > criteria → Grade C (เกินเกณฑ์)
                
                if ($difference > 0 && $criteria1 > 0) {
                    // ค่าบวก: เทียบกับ criteria_1
                    if ($absDiff > $criteria1 + $epsilon) {
                        $level = 'C';
                    } elseif (abs($absDiff - $criteria1) <= $epsilon) {
                        $level = 'B';
                    }
                } elseif ($difference < 0 && $criteria2 > 0) {
                    // ค่าลบ: เทียบกับ criteria_2
                    if ($absDiff > $criteria2 + $epsilon) {
                        $level = 'C';
                    } elseif (abs($absDiff - $criteria2) <= $epsilon) {
                        $level = 'B';
                    }
                }
                
                $judgement = ($level === 'C') ? 'Reject' : 'Pass';
                $allLevels[] = $level;
                
                // Set ค่าผลลัพธ์
                $set("../../../../../../../calibration_data.readings_inner.{$pointIndex}.specs.{$specIndex}.average", number_format($average, 3));
                $set("../../../../../../../calibration_data.readings_inner.{$pointIndex}.specs.{$specIndex}.sd", number_format($sd, 3));
                $set("../../../../../../../calibration_data.readings_inner.{$pointIndex}.specs.{$specIndex}.correction", number_format($correction, 5));
                $set("../../../../../../../calibration_data.readings_inner.{$pointIndex}.specs.{$specIndex}.Judgement", $judgement);
                $set("../../../../../../../calibration_data.readings_inner.{$pointIndex}.specs.{$specIndex}.level", $level);
            }
        }
        
        // รวม Level จาก Section 1 ด้วย
        $readings1 = $get('../../../../../../../calibration_data.readings') ?? [];
        foreach ($readings1 as $reading) {
            foreach ($reading['specs'] ?? [] as $spec) {
                if (!empty($spec['level'])) {
                    $allLevels[] = $spec['level'];
                }
            }
        }
        
        // คำนวณ Overall Status รวมทั้ง 2 sections
        self::calculateOverallStatus($get, $set, $allLevels);
    }

    // 🔥 คำนวณผลลัพธ์ของ Section 3 (สเกลวัดลึก)
    protected static function calculateDepthSpecResult(Get $get, Set $set)
    {
        // 🔥 ลองหลาย path เพื่อดึง readings
        $readings = $get('../../../../../../../calibration_data.readings_depth') 
            ?? $get('../../../../../../calibration_data.readings_depth')
            ?? $get('../../../../../calibration_data.readings_depth')
            ?? $get('../../../../calibration_data.readings_depth')
            ?? [];
        
        $instrumentId = $get('../../../../../../../instrument_id')
            ?? $get('../../../../../../instrument_id')
            ?? $get('../../../../../instrument_id')
            ?? $get('../../../../instrument_id')
            ?? null;
        
        if (!$instrumentId || empty($readings)) return;
        
        // ตรวจสอบว่ากรอกครบทุก spec ทุก point หรือยัง
        $allFilled = true;
        $totalPoints = 0;
        $filledPoints = 0;
        
        foreach ($readings as $reading) {
            $specs = $reading['specs'] ?? [];
            foreach ($specs as $spec) {
                $totalPoints++;
                $measurements = $spec['measurements'] ?? [];
                
                if (empty($measurements)) {
                    $allFilled = false;
                    continue;
                }
                
                $specFilled = true;
                foreach ($measurements as $m) {
                    if (!isset($m['value']) || $m['value'] === '' || $m['value'] === null) {
                        $specFilled = false;
                        $allFilled = false;
                        break;
                    }
                }
                
                if ($specFilled) {
                    $filledPoints++;
                }
            }
        }
        
        // 🔥 คำนวณก็ต่อเมื่อกรอกครบทุก point ทุก spec
        if (!$allFilled || $filledPoints < $totalPoints) return;
        
        // ถ้ากรอกครบแล้ว → คำนวณทั้งหมด
        self::calculateAllDepthSpecs($get, $set);
    }

    // 🔥 คำนวณทุก Spec ทุก Point สำหรับ Section 3 (สเกลวัดลึก)
    protected static function calculateAllDepthSpecs(Get $get, Set $set)
    {
        $readings = $get('../../../../../../../calibration_data.readings_depth') ?? [];
        $instrumentId = $get('../../../../../../../instrument_id');
        
        if (!$instrumentId || empty($readings)) return;
        
        $instrument = \App\Models\Instrument::with('toolType')->find($instrumentId);
        if (!$instrument) return;
        
        // 🔥 ดึง criteria_1 และ criteria_2 จาก ToolType
        $criteriaUnit = $instrument->toolType?->criteria_unit ?? [];
        $criteria1 = 0;
        $criteria2 = 0;
        
        if (is_array($criteriaUnit)) {
            foreach ($criteriaUnit as $item) {
                if (($item['index'] ?? 0) == 1) {
                    $criteria1 = abs((float) ($item['criteria_1'] ?? 0));
                    $criteria2 = abs((float) ($item['criteria_2'] ?? 0));
                    break;
                }
            }
        }
        
        $allLevels = [];
        
        // คำนวณแต่ละ Point
        foreach ($readings as $pointIndex => $reading) {
            $csValue = (float) ($reading['cs_value'] ?? 0);
            $specs = $reading['specs'] ?? [];
            
            foreach ($specs as $specIndex => $spec) {
                $sValue = (float) ($spec['s_value'] ?? 0);
                
                // 🔥 คำนวณค่าเฉลี่ยจาก measurements
                $measurements = $spec['measurements'] ?? [];
                $values = collect($measurements)
                    ->pluck('value')
                    ->filter(fn ($v) => !is_null($v) && $v !== '' && is_numeric($v))
                    ->map(fn ($v) => (float) $v);
                
                if ($values->isEmpty()) continue;
                
                $average = $values->avg();
                
                // คำนวณ SD
                $variance = $values->map(fn ($v) => pow($v - $average, 2))->sum();
                $sd = $values->count() > 1 ? sqrt($variance / $values->count()) : 0;
                
                // คำนวณค่าแก้สเกล = S + Cs - X̄
                $correction = $sValue + $csValue - $average;
                
                // 🔥 กำหนด Level โดยเทียบ (X̄ - S) กับ criteria
                $difference = $average - $sValue;
                $absDiff = abs($difference);
                $level = 'A';
                
                $epsilon = 0.0001;
                
                if ($difference > 0 && $criteria1 > 0) {
                    if ($absDiff > $criteria1 + $epsilon) {
                        $level = 'C';
                    } elseif (abs($absDiff - $criteria1) <= $epsilon) {
                        $level = 'B';
                    }
                } elseif ($difference < 0 && $criteria2 > 0) {
                    if ($absDiff > $criteria2 + $epsilon) {
                        $level = 'C';
                    } elseif (abs($absDiff - $criteria2) <= $epsilon) {
                        $level = 'B';
                    }
                }
                
                $judgement = ($level === 'C') ? 'Reject' : 'Pass';
                $allLevels[] = $level;
                
                // Set ค่าผลลัพธ์
                $set("../../../../../../../calibration_data.readings_depth.{$pointIndex}.specs.{$specIndex}.average", number_format($average, 3));
                $set("../../../../../../../calibration_data.readings_depth.{$pointIndex}.specs.{$specIndex}.sd", number_format($sd, 3));
                $set("../../../../../../../calibration_data.readings_depth.{$pointIndex}.specs.{$specIndex}.correction", number_format($correction, 5));
                $set("../../../../../../../calibration_data.readings_depth.{$pointIndex}.specs.{$specIndex}.Judgement", $judgement);
                $set("../../../../../../../calibration_data.readings_depth.{$pointIndex}.specs.{$specIndex}.level", $level);
            }
        }
        
        // รวม Level จาก Section 1 และ 2 ด้วย
        $readings1 = $get('../../../../../../../calibration_data.readings') ?? [];
        foreach ($readings1 as $reading) {
            foreach ($reading['specs'] ?? [] as $spec) {
                if (!empty($spec['level'])) {
                    $allLevels[] = $spec['level'];
                }
            }
        }
        
        $readings2 = $get('../../../../../../../calibration_data.readings_inner') ?? [];
        foreach ($readings2 as $reading) {
            foreach ($reading['specs'] ?? [] as $spec) {
                if (!empty($spec['level'])) {
                    $allLevels[] = $spec['level'];
                }
            }
        }
        
        // คำนวณ Overall Status รวมทั้ง 3 sections
        self::calculateOverallStatus($get, $set, $allLevels);
    }

    // 🔥 คำนวณความขนาน (Section 4) - ใช้ค่าความขนานที่กรอกตัดสิน Grade
    protected static function calculateParallelism(Get $get, Set $set)
    {
        // ดึงค่าความขนานที่กรอก
        $parallelism = $get('parallelism');
        
        // ถ้ายังไม่มีค่าความขนาน ให้ return
        if ($parallelism === null || $parallelism === '') {
            return;
        }
        
        // 🔥 เช็คว่ากรอก parallelism ครบทุก row ใน Section 4 หรือยัง
        $readings4 = $get('../../../calibration_data.readings_parallelism') 
            ?? $get('../../calibration_data.readings_parallelism')
            ?? $get('../calibration_data.readings_parallelism')
            ?? $get('calibration_data.readings_parallelism')
            ?? [];
        
        if (empty($readings4)) {
            return;
        }
        
        // เช็คว่ากรอกครบทุก row หรือยัง
        foreach ($readings4 as $reading) {
            $paraValue = $reading['parallelism'] ?? null;
            if ($paraValue === null || $paraValue === '') {
                // ยังกรอกไม่ครบ → ไม่คำนวณ
                return;
            }
        }
        
        // 🔥 กรอกครบแล้ว → คำนวณทุก row!
        // ดึง criteria_1 และ criteria_2
        $criteria1 = $get('../../criteria_1') 
            ?? $get('../../../criteria_1')
            ?? $get('../../../../criteria_1')
            ?? $get('../criteria_1')
            ?? 0;
        
        $criteria2 = $get('../../criteria_2') 
            ?? $get('../../../criteria_2')
            ?? $get('../../../../criteria_2')
            ?? $get('../criteria_2')
            ?? 0;
        
        $criteria1Value = abs((float) $criteria1);
        $criteria2Value = abs((float) $criteria2);
        $epsilon = 0.0001;
        
        // หา base path สำหรับ set ค่า
        $basePaths = [
            '../../../calibration_data.readings_parallelism',
            '../../calibration_data.readings_parallelism',
            '../calibration_data.readings_parallelism',
        ];
        
        $workingBasePath = null;
        foreach ($basePaths as $testPath) {
            $testData = $get($testPath);
            if (!empty($testData)) {
                $workingBasePath = $testPath;
                break;
            }
        }
        
        if (!$workingBasePath) {
            return;
        }
        
        // 🔥 Loop คำนวณทุก row
        foreach ($readings4 as $index => $reading) {
            $paraValue = (float) ($reading['parallelism'] ?? 0);
            $absPara = abs($paraValue);
            $level = 'A';
            
            if ($paraValue > 0 && $criteria1Value > 0) {
                if ($absPara > $criteria1Value + $epsilon) {
                    $level = 'C';
                } elseif (abs($absPara - $criteria1Value) <= $epsilon) {
                    $level = 'B';
                }
            } elseif ($paraValue < 0 && $criteria2Value > 0) {
                if ($absPara > $criteria2Value + $epsilon) {
                    $level = 'C';
                } elseif (abs($absPara - $criteria2Value) <= $epsilon) {
                    $level = 'B';
                }
            }
            
            $judgement = ($level === 'C') ? 'Reject' : 'Pass';
            
            // Set ค่าผลลัพธ์สำหรับ row นี้
            $set("{$workingBasePath}.{$index}.Judgement", $judgement);
            $set("{$workingBasePath}.{$index}.level", $level);
        }
        
        // คำนวณ Overall Status รวมจากทุก Section
        self::calculateAllParallelismOverall($get, $set);
    }
    
    // 🔥 คำนวณ Overall Status รวมจาก Section 4
    protected static function calculateAllParallelismOverall(Get $get, Set $set)
    {
        $allLevels = [];
        
        // รวม Level จาก Section 4 - ลองหลาย paths
        $readings4 = $get('../../../calibration_data.readings_parallelism') 
            ?? $get('../../calibration_data.readings_parallelism')
            ?? $get('../calibration_data.readings_parallelism')
            ?? $get('calibration_data.readings_parallelism')
            ?? [];
        foreach ($readings4 as $reading) {
            if (!empty($reading['level'])) {
                $allLevels[] = $reading['level'];
            }
        }
        
        // รวม Level จาก Section 1
        $readings1 = $get('../../../calibration_data.readings') 
            ?? $get('../../calibration_data.readings')
            ?? $get('../calibration_data.readings')
            ?? [];
        foreach ($readings1 as $reading) {
            foreach ($reading['specs'] ?? [] as $spec) {
                if (!empty($spec['level'])) {
                    $allLevels[] = $spec['level'];
                }
            }
        }
        
        // รวม Level จาก Section 2
        $readings2 = $get('../../../calibration_data.readings_inner') 
            ?? $get('../../calibration_data.readings_inner')
            ?? $get('../calibration_data.readings_inner')
            ?? [];
        foreach ($readings2 as $reading) {
            foreach ($reading['specs'] ?? [] as $spec) {
                if (!empty($spec['level'])) {
                    $allLevels[] = $spec['level'];
                }
            }
        }
        
        // รวม Level จาก Section 3
        $readings3 = $get('../../../calibration_data.readings_depth') 
            ?? $get('../../calibration_data.readings_depth')
            ?? $get('../calibration_data.readings_depth')
            ?? [];
        foreach ($readings3 as $reading) {
            foreach ($reading['specs'] ?? [] as $spec) {
                if (!empty($spec['level'])) {
                    $allLevels[] = $spec['level'];
                }
            }
        }
        
        // คำนวณ Overall Status
        if (!empty($allLevels)) {
            $worstLevel = 'A';
            foreach ($allLevels as $l) {
                if ($l === 'C') {
                    $worstLevel = 'C';
                    break;
                }
                if ($l === 'B') {
                    $worstLevel = 'B';
                }
            }
            
            $overallStatus = ($worstLevel === 'C') ? 'Reject' : 'Pass';
            
            // ลองหลาย paths เพื่อ set result_status และ cal_level
            $pathsToTry = ['../../../result_status', '../../result_status', '../result_status', 'result_status'];
            foreach ($pathsToTry as $path) {
                try {
                    $set($path, $overallStatus);
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            $pathsToTryLevel = ['../../../cal_level', '../../cal_level', '../cal_level', 'cal_level'];
            foreach ($pathsToTryLevel as $path) {
                try {
                    $set($path, $worstLevel);
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    // 🔥 อัปเดต Next Cal Date ตาม Level
    protected static function updateNextCalDate(Set $set, Get $get, string $level)
    {
        $calDate = $get('../../../cal_date') ?? $get('cal_date');
        $instrumentId = $get('../../../instrument_id') ?? $get('instrument_id');
        
        if (!$calDate || !$instrumentId) return;
        
        $instrument = \App\Models\Instrument::find($instrumentId);
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('instrument.code_no')
                    ->label('ID Code')
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
            ->filters([])
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
            'index' => Pages\ListCalibrationRecords::route('/'),
            'create' => Pages\CreateCalibrationRecord::route('/create'),
            'view' => Pages\ViewCalibrationRecord::route('/{record}'),
            'edit' => Pages\EditCalibrationRecord::route('/{record}/edit'),
        ];
    }
}