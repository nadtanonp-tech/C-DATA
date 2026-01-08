<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalibrationThreadPlugGaugeFitWearResource\Pages;
use App\Filament\Resources\CalibrationThreadPlugGaugeFitWearResource\RelationManagers;
use App\Models\CalibrationRecord;
use App\Models\Instrument;
use App\Models\Master;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CalibrationThreadPlugGaugeFitWearResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;
    protected static ?string $slug = 'calibration-thread-plug-gauge-fit-wear'; // 🔥 กำหนด slug สำหรับ URL

    protected static ?string $navigationLabel = 'Plug Gauge (Fit/Wear)';
    protected static ?string $navigationGroup = 'Gauge Cal Report & Data';
    protected static ?string $modelLabel = 'Plug Gauge (Fit/Wear)';
    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        // 🔥 กรอง Thread Plug Gauge Fit Wear: ใช้ calibration_type ใน JSON
        return parent::getEloquentQuery()
            ->with(['instrument.toolType'])
            ->whereRaw("calibration_data->>'calibration_type' = 'ThreadPlugGaugeFitWear'");
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
                                        // 🔥 ค้นหา Thread Plug Gauge Fit Wear: code_no 5-08-%, 5-09-%, 8-08-%, 8-09-%
                                        return \App\Models\Instrument::query()
                                            ->where(function ($q) {
                                                $q->where('code_no', 'LIKE', '5-08-%')
                                                  ->orWhere('code_no', 'LIKE', '5-09-%')
                                                  ->orWhere('code_no', 'LIKE', '8-08-%')
                                                  ->orWhere('code_no', 'LIKE', '8-09-%');
                                            })
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
                                 
                                        if ($instrument->toolType && $instrument->toolType->dimension_specs) {
                                            $dimensionSpecs = $instrument->toolType->dimension_specs;
                                            $readings = [];
                                    
                                            // 🔥 แบบใหม่: รวม Major, Pitch, Plug ไว้ใน Point เดียวกัน
                                            foreach ($dimensionSpecs as $pointIndex => $spec) {
                                                $point = $spec['point'] ?? null;
                                                if (!$point) continue;
                                        
                                                $readingItem = [
                                                    'point' => $point,
                                                    'trend' => $spec['trend'] ?? 'Smaller',
                                                    'specs' => [], // 🔥 เก็บ specs ทั้งหมดไว้ในนี้
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
                                                        'measurements' => [['value' => null], ['value' => null], ['value' => null], ['value' => null]], // 🔥 4 ช่องกรอกค่า
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
                                    
                                            // 🔥 เพิ่ม calibration_type สำหรับแยกประเภท
                                            $set('calibration_data.calibration_type', 'ThreadPlugGaugeFitWear');
                                            $set('calibration_data.readings', $readings);
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
                                    ->columnSpan(3)
                                    ->dehydrated(false),

                                TextInput::make('instrument_size')
                                    ->label('Size')
                                    ->disabled()
                                    ->columnSpan(3)
                                    ->dehydrated(false),
                            
                                TextInput::make('instrument_department')
                                    ->label('แผนก')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                TextInput::make('instrument_serial')
                                    ->label('Serial No.')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                TextInput::make('instrument_drawing')
                                    ->label('Drawing No.')
                                    ->disabled()
                                    ->dehydrated(false),
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

                Section::make('ผลการวัด (Measurement Results)')
                    ->description('กรอกค่าตามจุดตรวจสอบ - Thread Plug Gauge Fit Wear รวม Major, Pitch, Plug ไว้ในแต่ละ Point')
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?') . ' - Major - Pitch - Plug')
                            ->schema([
                                // 🔥 Hidden fields for Point level
                                Forms\Components\Hidden::make('point')->dehydrated(),
                                Forms\Components\Hidden::make('trend')->dehydrated(),

                                // 🔥 Nested Repeater สำหรับ specs (Major, Pitch, Plug)
                                Repeater::make('specs')
                                    ->label('รายการ Specs')
                                    ->schema([
                                        // Hidden fields
                                        Forms\Components\Hidden::make('label')->dehydrated(),
                                        Forms\Components\Hidden::make('min_spec')->dehydrated(),
                                        Forms\Components\Hidden::make('max_spec')->dehydrated(),

                                        // Spec Info Display (รวม Trend ไว้ด้วย)
                                        Placeholder::make('spec_info')
                                            ->label('')
                                            ->content(fn (Get $get) => view('filament.components.thread-plug-spec-info', [
                                                'label' => $get('label'),
                                                'minSpec' => $get('min_spec'),
                                                'maxSpec' => $get('max_spec'),
                                                'trend' => $get('../../trend'), // 🔥 ดึง trend จาก parent
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
                                // 🔥 ซ่อนและไม่บังคับถ้าเป็น Reject หรือ Level C
                                ->visible(fn (Get $get) => $get('result_status') !== 'Reject' && $get('cal_level') !== 'C')
                                ->required(fn (Get $get) => $get('result_status') !== 'Reject' && $get('cal_level') !== 'C')
                                ->live()
                                ->afterStateUpdated(function ($state, Get $get) {
                                    $calDate = $get('cal_date');
                                    $instrumentId = $get('instrument_id');
                                    
                                    if (!$calDate || !$state || !$instrumentId) return;
                                    
                                    $instrument = \App\Models\Instrument::find($instrumentId);
                                    if (!$instrument) return;
                                    
                                    // 🔥 คำนวณและ save ทุกครั้ง
                                    $calDateCarbon = \Carbon\Carbon::parse($calDate);
                                    $nextDateCarbon = \Carbon\Carbon::parse($state);
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
                                ->columnSpan(fn (Get $get) => ($get('result_status') === 'Reject' || $get('cal_level') === 'C') ? 2 : 1),
                        ]),
                    ]),
        ]);
    }

    // 🔥 คำนวณผลลัพธ์ของแต่ละ Spec (Major, Pitch, Plug)
    protected static function calculateSpecResult(Get $get, Set $set)
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
        self::calculateAllSpecs($get, $set);
    }
    
    // 🔥 คำนวณทุก Spec ทุก Point
    protected static function calculateAllSpecs(Get $get, Set $set)
    {
        $readings = $get('../../../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../../../instrument_id');
        
        if (!$instrumentId || empty($readings)) return;
        
        $instrument = \App\Models\Instrument::find($instrumentId);
        if (!$instrument) return;
        
        $percentAdj = (float) ($instrument->percent_adj ?? 10);
        $allGrades = [];
        
        // คำนวณแต่ละ Point
        foreach ($readings as $pointIndex => $reading) {
            $trend = $reading['trend'] ?? 'Smaller';
            $specs = $reading['specs'] ?? [];
            
            foreach ($specs as $specIndex => $spec) {
                // 🔥 คำนวณค่าเฉลี่ยจาก measurements
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
        self::calculateOverallStatus($get, $set, $allGrades);
    }
    
    // 🔥 คำนวณสถานะรวม
    protected static function calculateOverallStatus(Get $get, Set $set, array $allGrades)
    {
        $instrumentId = $get('../../../../../../../instrument_id');
        if (!$instrumentId || empty($allGrades)) return;
        
        $instrument = \App\Models\Instrument::find($instrumentId);
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
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25])
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
            'index' => Pages\ListCalibrationThreadPlugGaugeFitWears::route('/'),
            'create' => Pages\CreateCalibrationThreadPlugGaugeFitWear::route('/create'),
            'view' => Pages\ViewCalibrationThreadPlugGaugeFitWear::route('/{record}'),
            'edit' => Pages\EditCalibrationThreadPlugGaugeFitWear::route('/{record}/edit'),
        ];
    }
}
