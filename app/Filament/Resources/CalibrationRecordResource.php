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
use App\Filament\Clusters\CalibrationReport;

class CalibrationRecordResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Instrument Calibration';
    protected static ?string $modelLabel = 'Instrument Calibration';
    protected static ?string $navigationGroup = 'Instrument Cal Report & Data';
    protected static ?string $cluster = CalibrationReport::class; 
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'instrument-calibration';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['instrument.toolType'])
            ->whereHas('instrument', function ($q) {
                $q->where('code_no', 'NOT LIKE', '%-01-%')
                  ->where('code_no', 'NOT LIKE', '%-02-%')
                  ->where('code_no', 'NOT LIKE', '%-03-%')
                  ->where('code_no', 'NOT LIKE', '%-04-%')
                  ->where('code_no', 'NOT LIKE', '%-05-%')
                  ->where('code_no', 'NOT LIKE', '%-06-%')
                  ->where('code_no', 'NOT LIKE', '%-07-%')
                  ->where('code_no', 'NOT LIKE', '%-08-%')
                  ->where('code_no', 'NOT LIKE', '%-09-%');
            });
    }

    public static function form(Form $form): Form
    {
        return $form->schema(self::getFormSchema());
    }

    protected static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Section::make('ข้อมูลการสอบเทียบ (Calibration Info)')
                    ->schema(self::getCalibrationInfoSchema())
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
                ->schema([self::getMastersPlaceholder()]),

            Section::make('1. ตรวจสอบความถูกต้องของสเกล')
                ->description('กรอกค่าตามจุดตรวจสอบ')
                ->visible(fn (Get $get) => $get('calibration_data.calibration_type') !== 'PressureGauge')
                ->schema([self::getReadingsRepeater('calibration_data.readings', 4, 'calculateSpecResult')]),

            // 🔥 Section ใหม่: แสดงเฉพาะ types อื่นๆ (ไม่ใช่ Vernier Caliper/Digital)
            Section::make('2. ตรวจสอบความเรียบของผิวสัมผัส')
                ->description('กรอกผลการตรวจสอบ')
                ->visible(fn (Get $get) => !in_array($get('calibration_data.calibration_type'), ['VernierCaliper', 'VernierCaliperDigital', 'PressureGauge']))
                ->schema([
                    TextInput::make('calibration_data.flatness_check')
                        ->label('ผลการตรวจสอบ')
                        ->placeholder('กรอกผลการตรวจสอบ เช่น ไม่มีแสงรอดผ่าน')
                        ->dehydrated()
                        ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600;']),
                ]),

            // 🔥 Sections สำหรับ Vernier Caliper / Vernier Digital เท่านั้น
            Section::make('2. ตรวจสอบความถูกต้องของสเกลวัดใน')
                ->description('กรอกค่าตามจุดตรวจสอบ - สเกลวัดใน')
                ->visible(fn (Get $get) => in_array($get('calibration_data.calibration_type'), ['VernierCaliper', 'VernierCaliperDigital']))
                ->schema([self::getReadingsRepeater('calibration_data.readings_inner', 2, 'calculateInnerSpecResult')]),

            Section::make('3. ตรวจสอบความถูกต้องของสเกลวัดลึก')
                ->description('กรอกค่าตามจุดตรวจสอบ - สเกลวัดลึก')
                ->visible(fn (Get $get) => in_array($get('calibration_data.calibration_type'), ['VernierCaliper', 'VernierCaliperDigital']))
                ->schema([self::getReadingsRepeater('calibration_data.readings_depth', 2, 'calculateDepthSpecResult')]),

            Section::make('4. ตรวจสอบความเรียบและความขนาน')
                ->description('ตรวจสอบความเรียบของพื้นผิวและความขนานของขากรรไกร')
                ->visible(fn (Get $get) => in_array($get('calibration_data.calibration_type'), ['VernierCaliper', 'VernierCaliperDigital']))
                ->schema(self::getParallelismSchema()),

            // 🔥 Section สำหรับ Pressure Gauge เท่านั้น
            Section::make('ตรวจสอบค่า Pressure Gauge')
                ->description('เปรียบเทียบค่าจาก Pressure Gauge กับ Master')
                ->visible(fn (Get $get) => $get('calibration_data.calibration_type') === 'PressureGauge')
                ->schema([self::getPressureGaugeRepeater()]),

            Section::make('สรุปผล (Conclusion)')
                ->schema([self::getConclusionSchema()]),
        ];
    }

    protected static function getCalibrationInfoSchema(): array
    {
        return [
            Grid::make(5)->schema([
                Select::make('instrument_id')
                    ->label('เลือกเครื่องมือ (Code No)')
                    ->searchable()
                    ->required()
                    ->placeholder('รหัสเครื่องมือ หรือ รหัสประเภทเครื่องมือ')
                    ->columnSpan(3)
                    ->reactive()
                    ->getSearchResultsUsing(fn (string $search) => self::searchInstruments($search))
                    ->getOptionLabelUsing(fn ($value) => self::getInstrumentLabel($value))
                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::onInstrumentSelected($state, $set, $get)),

                DatePicker::make('cal_date')
                    ->label('วันที่สอบเทียบ')
                    ->default(now())
                    ->required()
                    ->reactive()
                    ->columnSpan(2)
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $level = $get('cal_level') ?? 'A';
                        self::updateNextCalDate($set, $get, $level);
                    }),
            ]),

            Grid::make(4)->schema([
                TextInput::make('instrument_name')->label('Name')->disabled()->columnSpan(3)->dehydrated(false),
                TextInput::make('instrument_brand')->label('ยี่ห้อ')->disabled()->columnSpan(1)->dehydrated(false),
                TextInput::make('instrument_size')->label('Size')->disabled()->columnSpan(3)->dehydrated(false),
                TextInput::make('instrument_department')->label('แผนก')->disabled()->columnSpan(1)->dehydrated(false),
                TextInput::make('instrument_serial')->label('Serial No.')->disabled()->columnSpan(2)->dehydrated(false),
                TextInput::make('instrument_drawing')->label('Drawing No.')->disabled()->columnSpan(2)->dehydrated(false),
            ]),

            Grid::make(3)->schema([
                TextInput::make('criteria_1')->label('เกณฑ์ค่าบวก (Criteria +)')->disabled()->columnSpan(1)->dehydrated(false)
                    ->suffix(fn (Get $get) => $get('criteria_unit') ?? 'mm.')
                    ->extraAttributes(['style' => 'text-align: center;']),
                TextInput::make('criteria_2')->label('เกณฑ์ค่าลบ (Criteria -)')->disabled()->columnSpan(1)->dehydrated(false)
                    ->suffix(fn (Get $get) => $get('criteria_unit') ?? 'mm.')
                    ->extraAttributes(['style' => 'text-align: center;']),
                Forms\Components\Hidden::make('criteria_unit')->dehydrated(false),
                TextInput::make('instrument_machine')
                    ->label('เครื่องจักร')
                    ->disabled()
                    ->columnSpan(1)
                    ->visible(fn (Get $get) => $get('calibration_data.calibration_type') === 'PressureGauge')
                    ->dehydrated(false),
            ]),

            Grid::make(3)->schema([
                TextInput::make('environment.temperature')->label('อุณหภูมิ (°C)')->numeric()->default(null),
                TextInput::make('environment.humidity')->label('ความชื้น (%)')->numeric()->default(null),
                Select::make('calibration_data.measurement_point')
                    ->label('จุดวัด')
                    ->options([
                        'inner' => 'วัดความโตใน',
                        'outer' => 'วัดความโตนอก',
                        'none' => 'ไม่มี',
                    ])
                    ->default('none')
                    ->native(false)
                    ->visible(fn (Get $get) => !in_array($get('calibration_data.calibration_type'), ['VernierCaliper', 'VernierCaliperDigital', 'PressureGauge']))
                    ->dehydrated(),
                // 🔥 Hidden field เก็บ calibration_type
                // ใช้ afterStateHydrated เพื่อ set ค่าเฉพาะตอน Create (ค่าว่าง)
                // ถ้า Edit/View จะใช้ค่าจาก database
                Forms\Components\Hidden::make('calibration_data.calibration_type')
                    ->afterStateHydrated(function ($state, $set) {
                        // ถ้ามีค่าอยู่แล้ว (จาก database) ไม่ต้องทำอะไร
                        if (!empty($state)) {
                            return;
                        }
                        
                        // ถ้าค่าว่าง (Create mode) ให้ set จาก URL parameter
                        $type = request()->get('type');
                        $calibrationType = match ($type) {
                            'vernier_special' => 'VernierSpecial',
                            'vernier_digital' => 'VernierCaliperDigital',
                            'vernier_caliper' => 'VernierCaliper',
                            'depth_vernier' => 'DepthVernier',
                            'vernier_hight_gauge' => 'VernierHightGauge',
                            'dial_vernier_hight_gauge' => 'DialVernierHightGauge',
                            'micro_meter' => 'MicroMeter',
                            'dial_caliper' => 'DialCaliper',
                            'dial_indicator' => 'DialIndicator',
                            'dial_test_indicator' => 'DialTestIndicator',
                            'thickness_gauge' => 'ThicknessGauge',
                            'thickness_caliper' => 'ThicknessCaliper',
                            'cylinder_gauge' => 'CylinderGauge',
                            'chamfer_gauge' => 'ChamferGauge',
                            'pressure_gauge' => 'PressureGauge',
                            default => null,
                        };
                        
                        if ($calibrationType) {
                            $set('calibration_type', $calibrationType); // 🔥 column ใหม่
                            $set('calibration_data.calibration_type', $calibrationType);
                        }
                    })
                    ->dehydrated(),
            ]),
        ];
    }

    protected static function searchInstruments(string $search): array
    {
        return \App\Models\Instrument::query()
            ->where(fn($q) => $q->where('code_no', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
            ->limit(50)->get()
            ->mapWithKeys(fn ($i) => [$i->id => "{$i->code_no} - {$i->name}"])->toArray();
    }

    protected static function getInstrumentLabel($value): string
    {
        $instrument = \App\Models\Instrument::find($value);
        return $instrument ? "{$instrument->code_no} - {$instrument->name}" : '';
    }

    protected static function onInstrumentSelected($state, Set $set, Get $get): void
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
        $set('instrument_brand', $instrument->brand ?? '-');
        $set('instrument_machine', $instrument->machine_name ?? '-');
        
        // 🔥 โหลด criteria จาก Instrument แทน ToolType
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
        $set('criteria_1', $criteria1);
        $set('criteria_2', $criteria2);
        $set('criteria_unit', $unit);
        
        self::loadDimensionSpecs($instrument, $set);
    }

    protected static function loadDimensionSpecs($instrument, Set $set): void
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

        $set('calibration_data.readings', $readings);
        $set('calibration_data.readings_inner', $readingsInner);
        $set('calibration_data.readings_depth', $readingsDepth);
        $set('calibration_data.readings_parallelism', $readingsParallelism);
        
        // 🔥 Pressure Gauge: สร้าง readings_pressure จาก dimension_specs
        $readingsPressure = [];
        foreach ($dimensionSpecs as $spec) {
            if (isset($spec['specs']) && is_array($spec['specs'])) {
                foreach ($spec['specs'] as $s) {
                    // ใช้ s_std แทน s_value เพราะ Pressure Gauge ใช้ s_std
                    $sValue = $s['s_std'] ?? $s['s_value'] ?? null;
                    if ($sValue !== null) {
                        $readingsPressure[] = [
                            's_value' => $sValue,
                            'master_value' => null,
                            'error' => null,
                            'percent_error' => null,
                            'Judgement' => null,
                            'level' => null,
                        ];
                    }
                }
            }
        }
        $set('calibration_data.readings_pressure', $readingsPressure);
    }

    protected static function getMastersPlaceholder(): Placeholder
    {
        return Placeholder::make('masters_reference')->label('')
            ->content(function (Get $get) {
                $instrumentId = $get('instrument_id');
                if (!$instrumentId) return view('filament.components.masters-placeholder', ['message' => 'กรุณาเลือกเครื่องมือก่อน']);
                $instrument = \App\Models\Instrument::with('toolType.masters')->find($instrumentId);
                if (!$instrument?->toolType) return view('filament.components.masters-placeholder', ['message' => 'ไม่พบข้อมูล Tool Type']);
                $masters = $instrument->toolType->masters;
                if ($masters->isEmpty()) return view('filament.components.masters-placeholder', ['message' => 'ยังไม่มี Master กำหนดไว้']);
                return view('filament.components.masters-table', ['masters' => $masters]);
            });
    }

    protected static function getReadingsRepeater(string $name, int $measurementCount, string $calcMethod): Repeater
    {
        return Repeater::make($name)
            ->label('รายการจุดตรวจสอบ')
            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?'))
            ->schema([
                Forms\Components\Hidden::make('point')->dehydrated(),
                Forms\Components\Hidden::make('cs_value')->dehydrated(),
                Repeater::make('specs')->label('รายการ Specs')
                    ->schema(self::getSpecSchema($measurementCount, $calcMethod))
                    ->reorderable(false)->addable(false)->deletable(false)->cloneable(false)->defaultItems(0)->columns(1)
                    ->itemLabel(fn (array $state): ?string => 'S = ' . ($state['s_value'] ?? '?')),
            ])
            ->collapsible()->reorderable(false)->addable(false)->deletable(false)->cloneable(false)->defaultItems(0)->columns(1);
    }

    protected static function getSpecSchema(int $measurementCount, string $calcMethod): array
    {
        return [
            Forms\Components\Hidden::make('label')->dehydrated(),
            Forms\Components\Hidden::make('s_value')->dehydrated(),
            Placeholder::make('spec_info')->label('')
                ->content(fn (Get $get) => view('filament.components.instrument-spec-info', [
                    'label' => $get('label'), 'sValue' => $get('s_value'), 'csValue' => $get('../../cs_value'),
                ])),
            Repeater::make('measurements')->hiddenLabel()
                ->schema([
                    TextInput::make('value')->hiddenLabel()->numeric()->placeholder('0.00')
                        ->live(debounce: 500)
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::$calcMethod($get, $set))
                        ->extraAttributes(['style' => 'font-family: monospace; text-align: center; font-weight: 600;']),
                ])
                ->addActionLabel('+ เพิ่มค่าวัด')->reorderable(false)->cloneable(false)
                ->defaultItems($measurementCount)->minItems(1)->grid(4)->itemLabel(fn (): string => 'ค่าที่อ่านได้จากสเกล'),
            Section::make('ผลลัพธ์')->compact()->schema([self::getResultGrid()]),
        ];
    }

    protected static function getResultGrid(): Grid
    {
        return Grid::make(5)->schema([
            TextInput::make('average')->label('ค่าเฉลี่ยที่อ่านได้จากสเกล X̄')->disabled()->dehydrated()
                ->extraAttributes(['style' => 'font-family: monospace; font-weight: 700; text-align: center; background-color: #e0f2fe; color: #0369a1;']),
            TextInput::make('sd')->label('ค่าเบี่ยงเบนมาตรฐาน (SD)')->disabled()->dehydrated()
                ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600; text-align: center;']),
            TextInput::make('correction')->label('ค่าแก้สเกล S+Cs-X̄')->disabled()->dehydrated()
                ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600; text-align: center;']),
            TextInput::make('Judgement')->label('Judgement')->disabled()->dehydrated()
                ->extraAttributes(fn ($state) => ['style' => match($state) {
                    'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                    'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                    default => 'text-align: center;'
                }]),
            Select::make('level')->label('Level')->disabled()->dehydrated()
                ->options(['A' => 'Level A', 'B' => 'Level B', 'C' => 'Level C'])
                ->extraAttributes(fn ($state) => ['style' => match($state) {
                    'A' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important;',
                    'B' => 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: bold !important;',
                    'C' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important;',
                    default => ''
                }]),
        ]);
    }

    protected static function getParallelismSchema(): array
    {
        return [
            TextInput::make('calibration_data.flatness_check')
                ->label('ตรวจสอบความเรียบ')
                ->placeholder('กรอกผลการตรวจสอบ เช่น ไม่มีแสงรอดผ่าน')
                ->dehydrated()
                ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600;']),

            Repeater::make('calibration_data.readings_parallelism')
                ->label('รายการตรวจสอบความขนาน')
                ->itemLabel(fn (array $state): ?string => 'S = ' . ($state['s_value'] ?? '?'))
                ->schema([
                    Forms\Components\Hidden::make('point')->dehydrated(),
                    Forms\Components\Hidden::make('s_value')->dehydrated(),
                    Grid::make(6)->schema([
                        TextInput::make('position_start')->label('ตำแหน่งต้น')->numeric()->placeholder('0.00')->dehydrated()
                            ->extraAttributes(['style' => 'font-family: monospace; text-align: center; font-weight: 600;']),
                        TextInput::make('position_middle')->label('ตำแหน่งกลาง')->numeric()->placeholder('0.00')->dehydrated()
                            ->extraAttributes(['style' => 'font-family: monospace; text-align: center; font-weight: 600;']),
                        TextInput::make('position_end')->label('ตำแหน่งปลาย')->numeric()->placeholder('0.00')->dehydrated()
                            ->extraAttributes(['style' => 'font-family: monospace; text-align: center; font-weight: 600;']),
                        TextInput::make('parallelism')->label('ความขนาน')->numeric()->placeholder('0.00')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::calculateParallelism($get, $set))
                            ->dehydrated()->extraAttributes(['style' => 'font-family: monospace; font-weight: 700; text-align: center;']),
                        TextInput::make('Judgement')->label('Judgement')->disabled()->dehydrated()
                            ->extraAttributes(fn ($state) => ['style' => match($state) {
                                'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                                'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                                default => 'text-align: center;'
                            }]),
                        Select::make('level')->label('Level')->disabled()->dehydrated()
                            ->options(['A' => 'Level A', 'B' => 'Level B', 'C' => 'Level C'])
                            ->extraAttributes(fn ($state) => ['style' => match($state) {
                                'A' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important;',
                                'B' => 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: bold !important;',
                                'C' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important;',
                                default => ''
                            }]),
                    ]),
                ])
                ->collapsible()->reorderable(false)->addable(false)->deletable(false)->cloneable(false)->defaultItems(0)->columns(1),
        ];
    }

    // 🔥 Pressure Gauge Repeater
    protected static function getPressureGaugeRepeater(): Repeater
    {
        return Repeater::make('calibration_data.readings_pressure')
            ->label('รายการจุดตรวจสอบ')
            ->itemLabel(fn (array $state): ?string => 'Point: ' . ($state['s_value'] ?? '?'))
            ->schema([
                Grid::make(6)->schema([
                    TextInput::make('s_value')
                        ->label('ค่าจาก Pressure Gauge')
                        ->disabled()
                        ->dehydrated()
                        ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600; text-align: center; background-color: #e0f2fe;']),
                    TextInput::make('master_value')
                        ->label('ค่าจาก Master')
                        ->numeric()
                        ->placeholder('0.0000')
                        ->dehydrated()
                        ->live(debounce: 500)
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::calculatePressureGaugeResult($get, $set))
                        ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600; text-align: center;']),
                    TextInput::make('error')
                        ->label('ERROR')
                        ->disabled()
                        ->dehydrated() 
                        ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600; text-align: center;']),
                    TextInput::make('percent_error')
                        ->label('% ERROR (จาก Range)')
                        ->disabled()
                        ->dehydrated()
                        ->extraAttributes(['style' => 'font-family: monospace; font-weight: 600; text-align: center;']),
                    TextInput::make('Judgement')
                        ->label('Judgement')
                        ->disabled()
                        ->dehydrated()
                        ->extraAttributes(fn ($state) => ['style' => match($state) {
                            'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                            'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                            default => 'text-align: center;'
                        }]),
                    Select::make('level')
                        ->label('Level')
                        ->disabled()
                        ->dehydrated()
                        ->options(['A' => 'Level A', 'B' => 'Level B', 'C' => 'Level C'])
                        ->extraAttributes(fn ($state) => ['style' => match($state) {
                            'A' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important;',
                            'B' => 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: bold !important;',
                            'C' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important;',
                            default => ''
                        }]),
                ]),
            ])
            ->reorderable(false)->addable(false)->deletable(false)->cloneable(false)->defaultItems(0)->columns(1);
    }

    protected static function getConclusionSchema(): Grid
    {
        return Grid::make(4)->schema([
            Select::make('result_status')->label('ผลการสอบเทียบ (Status)')
                ->options(['Pass' => 'ผ่าน (Pass)', 'Reject' => 'ไม่ผ่าน (Reject)'])->dehydrated()->native(false)
                ->extraAttributes(fn ($state) => ['style' => match($state) {
                    'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; border: 2px solid #86efac !important;',
                    'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; border: 2px solid #fca5a5 !important;',
                    default => ''
                }]),
            Select::make('cal_level')->label('ระดับการสอบเทียบ (Level)')
                ->options(['A' => 'ระดับ A', 'B' => 'ระดับ B', 'C' => 'ระดับ C'])->dehydrated()->native(false)
                ->extraAttributes(fn ($state) => ['style' => match($state) {
                    'A' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; border: 2px solid #86efac !important;',
                    'B' => 'background-color: #fef3c7 !important; color: #92400e !important; font-weight: bold !important; border: 2px solid #fde047 !important;',
                    'C' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; border: 2px solid #fca5a5 !important;',
                    default => ''
                }]),
            DatePicker::make('next_cal_date')
                ->label('วันครบกำหนดครั้งถัดไป (Next Cal)')
                ->dehydrated()
                // 🔥 ซ่อนและไม่บังคับถ้าเป็น Reject หรือ Level C
                ->visible(fn (Get $get) => $get('result_status') !== 'Reject' && $get('cal_level') !== 'C')
                ->required(fn (Get $get) => $get('result_status') !== 'Reject' && $get('cal_level') !== 'C')
                ->live()
                ->afterStateUpdated(function ($state, Get $get) {
                    // คำนวณความถี่จาก cal_date และ next_cal_date
                    $calDate = $get('cal_date');
                    $instrumentId = $get('instrument_id');
                    
                    if (!$calDate || !$state || !$instrumentId) return;
                    
                    $instrument = \App\Models\Instrument::find($instrumentId);
                    if (!$instrument) return;
                    
                    // 🔥 คำนวณและ save ทุกครั้ง (ไม่ว่าจะมีค่าอยู่แล้วหรือไม่)
                    $calDateCarbon = \Carbon\Carbon::parse($calDate);
                    $nextDateCarbon = \Carbon\Carbon::parse($state);
                    
                    // คำนวณจำนวนเดือนที่ต่างกัน (ใช้ floor เพื่อปัดลง เช่น 12.7 → 12)
                    $diffMonths = (int) floor($calDateCarbon->floatDiffInMonths($nextDateCarbon));
                    
                    if ($diffMonths > 0 && $diffMonths !== $instrument->cal_freq_months) {
                        $oldFreq = $instrument->cal_freq_months ?? 0;
                        $instrument->update(['cal_freq_months' => $diffMonths]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('อัปเดตความถี่สำเร็จ')
                            ->body("เปลี่ยนความถี่ {$oldFreq} → {$diffMonths} เดือน สำหรับ {$instrument->code_no}")
                            ->success()
                            ->send();
                    }
                }),
            TextInput::make('remark')
                ->label('หมายเหตุ (Remark)')
                // 🔥 ขยายเป็น 2 columns เมื่อ next_cal_date หายไป (Reject/Level C)
                ->columnSpan(fn (Get $get) => ($get('result_status') === 'Reject' || $get('cal_level') === 'C') ? 2 : 1),
        ]);
    }

    // ============ CALCULATION METHODS ============

    protected static function calculateSpecResult(Get $get, Set $set): void
    {
        self::calculateSectionResult($get, $set, 'readings', 'calibration_data.readings');
    }

    protected static function calculateInnerSpecResult(Get $get, Set $set): void
    {
        self::calculateSectionResult($get, $set, 'readings_inner', 'calibration_data.readings_inner');
    }

    protected static function calculateDepthSpecResult(Get $get, Set $set): void
    {
        self::calculateSectionResult($get, $set, 'readings_depth', 'calibration_data.readings_depth');
    }

    // 🔥 Pressure Gauge Calculation
    protected static function calculatePressureGaugeResult(Get $get, Set $set): void
    {
        $paths = ['../../../../../../../', '../../../../../../', '../../../../../', '../../../../', '../../../', '../../', '../', ''];
        $readings = null; $instrumentId = null; $basePath = '';
        
        foreach ($paths as $p) {
            $readings = $get($p . 'calibration_data.readings_pressure');
            $instrumentId = $get($p . 'instrument_id');
            if (!empty($readings) && $instrumentId) { $basePath = $p; break; }
        }
        
        if (!$instrumentId || empty($readings)) return;
        
        // 🔥 ตรวจสอบว่ากรอก master_value ครบทุก Point หรือยัง
        foreach ($readings as $reading) {
            $masterValue = $reading['master_value'] ?? null;
            if ($masterValue === null || $masterValue === '') {
                return; // ยังไม่ครบ ให้รอกรอกก่อน
            }
        }
        
        $instrument = \App\Models\Instrument::with('toolType')->find($instrumentId);
        if (!$instrument) return;

        // ดึง criteria จาก Instrument
        $criteriaUnit = $instrument->criteria_unit ?? [];
        $criteria1 = 0;
        $percentAdj = floatval($instrument->percent_adj ?? 10);
        
        if (is_array($criteriaUnit) && !empty($criteriaUnit)) {
            $firstCriteria = $criteriaUnit[0] ?? [];
            $criteria1 = abs(floatval($firstCriteria['criteria_1'] ?? 0));
        }

        $updated = false;
        $allJudgements = [];
        $allLevels = [];
        
        foreach ($readings as $index => $reading) {
            $sValue = floatval($reading['s_value'] ?? 0);
            $masterValue = isset($reading['master_value']) && $reading['master_value'] !== '' && $reading['master_value'] !== null
                ? floatval($reading['master_value']) : null;
            
            if ($masterValue === null) continue;
            
            // ERROR = ค่า Pressure Gauge - ค่า Master
            $error = $sValue - $masterValue;
            
            // % ERROR = (|ERROR| / ค่า Pressure Gauge) × 100
            $percentError = $sValue != 0 ? (abs($error) / $sValue) * 100 : 0;
            
            // Judgement & Level based on %ERROR vs criteria
            $judgement = $percentError <= $criteria1 ? 'Pass' : 'Reject';
            
            // Level based on % of criteria
            $level = 'A';
            if ($criteria1 > 0) {
                $ratio = ($percentError / $criteria1) * 100;
                if ($ratio <= (100 - $percentAdj)) {
                    $level = 'A';
                } elseif ($ratio <= 100) {
                    $level = 'B';
                } else {
                    $level = 'C';
                }
            }
            
            $set($basePath . "calibration_data.readings_pressure.{$index}.error", number_format($error, 4));
            $set($basePath . "calibration_data.readings_pressure.{$index}.percent_error", number_format($percentError, 4));
            $set($basePath . "calibration_data.readings_pressure.{$index}.Judgement", $judgement);
            $set($basePath . "calibration_data.readings_pressure.{$index}.level", $level);
            
            $allJudgements[] = $judgement;
            $allLevels[] = $level;
            $updated = true;
        }
        
        // Update overall status and level
        if ($updated && !empty($allJudgements)) {
            $overallStatus = in_array('Reject', $allJudgements) ? 'Reject' : 'Pass';
            $overallLevel = 'A';
            if (in_array('C', $allLevels)) $overallLevel = 'C';
            elseif (in_array('B', $allLevels)) $overallLevel = 'B';
            
            $set($basePath . 'result_status', $overallStatus);
            $set($basePath . 'cal_level', $overallLevel);
            self::updateNextCalDate($set, $get, $overallLevel, $basePath);
        }
    }

    protected static function calculateSectionResult(Get $get, Set $set, string $key, string $path): void
    {
        $paths = ['../../../../../../../', '../../../../../../', '../../../../../', '../../../../', '../../../', '../../', '../', ''];
        $readings = null; $instrumentId = null; $basePath = '';
        
        foreach ($paths as $p) {
            $readings = $get($p . $path); $instrumentId = $get($p . 'instrument_id');
            if (!empty($readings) && $instrumentId) { $basePath = $p; break; }
        }
        
        if (!$instrumentId || empty($readings)) return;
        if (!self::checkAllFilled($readings)) return;
        
        $instrument = \App\Models\Instrument::with('toolType')->find($instrumentId);
        if (!$instrument) return;
        
        [$criteria1, $criteria2] = self::getCriteria($instrument);
        $allLevels = [];
        
        foreach ($readings as $pointIndex => $reading) {
            $csValue = (float) ($reading['cs_value'] ?? 0);
            foreach ($reading['specs'] ?? [] as $specIndex => $spec) {
                $result = self::calculateSpecValues($spec, $csValue, $criteria1, $criteria2);
                if (!$result) continue;
                
                $allLevels[] = $result['level'];
                $set("{$basePath}{$path}.{$pointIndex}.specs.{$specIndex}.average", $result['average']);
                $set("{$basePath}{$path}.{$pointIndex}.specs.{$specIndex}.sd", $result['sd']);
                $set("{$basePath}{$path}.{$pointIndex}.specs.{$specIndex}.correction", $result['correction']);
                $set("{$basePath}{$path}.{$pointIndex}.specs.{$specIndex}.Judgement", $result['judgement']);
                $set("{$basePath}{$path}.{$pointIndex}.specs.{$specIndex}.level", $result['level']);
            }
        }
        
        self::collectAllLevelsAndUpdate($get, $set, $basePath, $allLevels);
    }

    protected static function checkAllFilled(array $readings): bool
    {
        foreach ($readings as $reading) {
            foreach ($reading['specs'] ?? [] as $spec) {
                foreach ($spec['measurements'] ?? [] as $m) {
                    if (!isset($m['value']) || $m['value'] === '' || $m['value'] === null) return false;
                }
            }
        }
        return true;
    }

    protected static function getCriteria($instrument): array
    {
        $criteriaUnit = $instrument->toolType?->criteria_unit ?? [];
        $criteria1 = $criteria2 = 0;
        if (is_array($criteriaUnit)) {
            foreach ($criteriaUnit as $item) {
                if (($item['index'] ?? 0) == 1) {
                    $criteria1 = abs((float) ($item['criteria_1'] ?? 0));
                    $criteria2 = abs((float) ($item['criteria_2'] ?? 0));
                    break;
                }
            }
        }
        return [$criteria1, $criteria2];
    }

    protected static function calculateSpecValues(array $spec, float $csValue, float $criteria1, float $criteria2): ?array
    {
        $sValue = (float) ($spec['s_value'] ?? 0);
        $values = collect($spec['measurements'] ?? [])->pluck('value')
            ->filter(fn ($v) => !is_null($v) && $v !== '' && is_numeric($v))->map(fn ($v) => (float) $v);
        
        if ($values->isEmpty()) return null;
        
        $average = $values->avg();
        $variance = $values->map(fn ($v) => pow($v - $average, 2))->sum();
        $sd = $values->count() > 1 ? sqrt($variance / $values->count()) : 0;
        $correction = $sValue + $csValue - $average;
        
        $difference = $average - $sValue;
        $absDiff = abs($difference);
        $level = 'A';
        $epsilon = 0.0001;
        
        if ($difference > 0 && $criteria1 > 0) {
            if ($absDiff > $criteria1 + $epsilon) $level = 'C';
            elseif (abs($absDiff - $criteria1) <= $epsilon) $level = 'B';
        } elseif ($difference < 0 && $criteria2 > 0) {
            if ($absDiff > $criteria2 + $epsilon) $level = 'C';
            elseif (abs($absDiff - $criteria2) <= $epsilon) $level = 'B';
        }
        
        return [
            'average' => number_format($average, 3),
            'sd' => number_format($sd, 3),
            'correction' => number_format($correction, 5),
            'judgement' => ($level === 'C') ? 'Reject' : 'Pass',
            'level' => $level,
        ];
    }

    protected static function collectAllLevelsAndUpdate(Get $get, Set $set, string $basePath, array $newLevels): void
    {
        $allLevels = $newLevels;
        $sections = ['calibration_data.readings', 'calibration_data.readings_inner', 'calibration_data.readings_depth'];
        
        foreach ($sections as $section) {
            $readings = $get($basePath . $section) ?? [];
            foreach ($readings as $reading) {
                foreach ($reading['specs'] ?? [] as $spec) {
                    if (!empty($spec['level'])) $allLevels[] = $spec['level'];
                }
            }
        }
        
        $readings4 = $get($basePath . 'calibration_data.readings_parallelism') ?? [];
        foreach ($readings4 as $reading) {
            if (!empty($reading['level'])) $allLevels[] = $reading['level'];
        }
        
        self::updateOverallStatus($get, $set, $basePath, $allLevels);
    }

    protected static function updateOverallStatus(Get $get, Set $set, string $basePath, array $allLevels): void
    {
        if (empty($allLevels)) return;
        
        $instrumentId = $get($basePath . 'instrument_id');
        $instrument = \App\Models\Instrument::find($instrumentId);
        if (!$instrument) return;
        
        $level = 'A';
        if (in_array('C', $allLevels)) $level = 'C';
        elseif (in_array('B', $allLevels)) $level = 'B';
        
        $status = ($level === 'C') ? 'Reject' : 'Pass';
        
        $set($basePath . 'result_status', $status);
        $set($basePath . 'cal_level', $level);
        
        $calDate = $get($basePath . 'cal_date');
        if ($calDate) {
            $nextDate = match($level) {
                'A' => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
                'B' => \Carbon\Carbon::parse($calDate)->addMonth()->endOfMonth(),
                'C' => null,
                default => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12)->endOfMonth(),
            };
            if ($nextDate) $set($basePath . 'next_cal_date', $nextDate->format('Y-m-d'));
        }
    }

    // 🔥 อัปเดต Next Cal Date ตาม Level (เหมือน CalibrationKNewResource)
    protected static function updateNextCalDate(Set $set, Get $get, string $level, string $basePath = ''): void
    {
        $calDate = $get($basePath . 'cal_date');
        $instrumentId = $get($basePath . 'instrument_id');
        
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
            $set($basePath . 'next_cal_date', $nextDate->format('Y-m-d'));
        }
    }

    protected static function calculateParallelism(Get $get, Set $set): void
    {
        $parallelism = $get('parallelism');
        if ($parallelism === null || $parallelism === '') return;
        
        $paths = ['../../../', '../../', '../', ''];
        $readings4 = null; $basePath = '';
        
        foreach ($paths as $p) {
            $readings4 = $get($p . 'calibration_data.readings_parallelism');
            if (!empty($readings4)) { $basePath = $p; break; }
        }
        
        if (empty($readings4)) return;
        
        foreach ($readings4 as $reading) {
            if (($reading['parallelism'] ?? null) === null || $reading['parallelism'] === '') return;
        }
        
        $criteria1 = abs((float) ($get($basePath . 'criteria_1') ?? 0));
        $criteria2 = abs((float) ($get($basePath . 'criteria_2') ?? 0));
        $epsilon = 0.0001;
        
        foreach ($readings4 as $index => $reading) {
            $paraValue = (float) ($reading['parallelism'] ?? 0);
            $absPara = abs($paraValue);
            $level = 'A';
            
            if ($paraValue > 0 && $criteria1 > 0) {
                if ($absPara > $criteria1 + $epsilon) $level = 'C';
                elseif (abs($absPara - $criteria1) <= $epsilon) $level = 'B';
            } elseif ($paraValue < 0 && $criteria2 > 0) {
                if ($absPara > $criteria2 + $epsilon) $level = 'C';
                elseif (abs($absPara - $criteria2) <= $epsilon) $level = 'B';
            }
            
            $judgement = ($level === 'C') ? 'Reject' : 'Pass';
            $set("{$basePath}calibration_data.readings_parallelism.{$index}.Judgement", $judgement);
            $set("{$basePath}calibration_data.readings_parallelism.{$index}.level", $level);
        }
        
        self::collectAllLevelsAndUpdate($get, $set, $basePath, []);
    }

    // ============ TABLE & PAGES ============

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->deferLoading()
            ->columns([
                TextColumn::make('instrument.code_no')->label('ID Code')->searchable()->sortable(),
                TextColumn::make('instrument.toolType.name')->label('Type Name')->searchable(),
                TextColumn::make('cal_date')->label('Cal Date')->date('d/m/Y')->sortable(),
                TextColumn::make('next_cal_date')->label('Next Cal')->date('d/m/Y')->sortable(),
                TextColumn::make('result_status')->label('ผลการ Cal')->badge()
                    ->color(fn (string $state): string => match ($state) { 'Pass' => 'success', 'Reject' => 'danger', default => 'gray' }),
                TextColumn::make('cal_level')->label('Level')->badge()
                    ->color(fn (string $state): string => match ($state) { 'A' => 'success', 'B' => 'warning', 'C' => 'danger', default => 'gray' }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('result_status')
                    ->label('ผลการ Cal')
                    ->options([
                        'Pass' => 'Pass',
                        'Reject' => 'Reject',
                    ]),
                Tables\Filters\SelectFilter::make('cal_level')
                    ->label('Level')
                    ->options([
                        'A' => 'Level A',
                        'B' => 'Level B',
                        'C' => 'Level C',
                    ]),
                Tables\Filters\SelectFilter::make('calibration_type')
                    ->label('ประเภทการสอบเทียบ')
                    ->options([
                        'VernierCaliper' => 'Vernier Caliper',
                        'VernierSpecial' => 'Vernier Special',
                        'VernierDigital' => 'Vernier Digital',
                        'Micrometer' => 'Micrometer',
                        'DialCaliper' => 'Dial Caliper',
                        'DialIndicator' => 'Dial Indicator',
                        'DialTestIndicator' => 'Dial Test Indicator',
                        'DialGauge' => 'Dial Gauge',
                        'DepthGauge' => 'Depth Gauge',
                        'HeightGauge' => 'Height Gauge',
                        'ThicknessGauge' => 'Thickness Gauge',
                        'ThicknessCaliper' => 'Thickness Caliper',
                        'PressureGauge' => 'Pressure Gauge',
                        'ChamferGauge' => 'Chamfer Gauge',
                    ])
                    ->searchable()
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
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('cal_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('cal_date', '<=', $date));
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([Actions\ViewAction::make(), Actions\EditAction::make()->color('warning'), Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array { return []; }

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