<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalibrationPlugGaugeResource\Pages;
use App\Filament\Resources\CalibrationPlugGaugeResource\RelationManagers;
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

class CalibrationPlugGaugeResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;
    protected static ?string $slug = 'calibration-plug-gauge'; // 🔥 กำหนด slug สำหรับ URL

    protected static ?string $navigationLabel = 'Plug Gauge';
    protected static ?string $navigationGroup = 'Gauge Cal Report & Data';
    protected static ?string $modelLabel = 'Plug Gauge';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        // 🔥 กรองเฉพาะ Plug Gauge โดยใช้ code_no pattern
        return parent::getEloquentQuery()
            ->with(['instrument.toolType']) // 🔥 แก้ N+1 Query
            ->whereHas('instrument', function ($query) {
                $query->where('code_no', 'LIKE', '8-03-%');
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
                                        // 🔥 ค้นหาเฉพาะ Plug Gauge ที่มี code_no ขึ้นต้นด้วย "8-03-"
                                        return \App\Models\Instrument::query()
                                            ->where('code_no', 'LIKE', '8-03-%') // กรองเฉพาะ Plug Gauge
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
                                                    
                                                    if (($mainSpec['label'] ?? '') === 'วัดเกลียว') {
                                                        $readingItem['min_spec'] = $mainSpec['standard_value'] ?? null;
                                                        $readingItem['max_spec'] = null;
                                                    // 🔥 ตั้งค่า Default ให้ Link กับหน้าจอ
                                                        $readingItem['Judgement'] = 'Pass';
                                                        
                                                    } else {
                                                        $valMin = $mainSpec['min'] ?? null;
                                                        $valMax = $mainSpec['max'] ?? null;
                                                        // Format Scientific Notation
                                                        $readingItem['min_spec'] = $valMin !== null ? rtrim(rtrim(number_format((float)$valMin, 8, '.', ''), '0'), '.') : null;
                                                        $readingItem['max_spec'] = $valMax !== null ? rtrim(rtrim(number_format((float)$valMax, 8, '.', ''), '0'), '.') : null;
                                                    }
                                                }
                                        
                                                if (isset($spec['specs'])) {
                                                    $readingItem['all_specs'] = $spec['specs'];
                                                }
                                                
                                                // 🔥 กำหนดจำนวน default measurements ตามลำดับ Point
                                                // Point แรก (index 0) = 3 ช่อง, Point ที่สอง (index 1) = 2 ช่อง, ที่เหลือ = 1 ช่อง
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
                                    }),
                                DatePicker::make('cal_date')
                                    ->label('วันที่สอบเทียบ')
                                    ->default(now())
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        // Trigger recalculation when date changes
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
                    ->description('กรอกค่าตามจุดตรวจสอบ - สามารถเพิ่มหลายค่าต่อจุด และใช้ค่าเฉลี่ยในการคำนวณ')
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?') . ' - STD')
                            ->schema([
                                Grid::make(12)->schema([
                                    // 🔥 Hidden fields
                                    Forms\Components\Hidden::make('point')->dehydrated(),
                                    Forms\Components\Hidden::make('std_label')->dehydrated(),
                                    Forms\Components\Hidden::make('trend')->dehydrated(),
                                    Forms\Components\Hidden::make('min_spec')->dehydrated(),
                                    Forms\Components\Hidden::make('max_spec')->dehydrated(),
                                    Forms\Components\Hidden::make('all_specs')->dehydrated(),

                                    // 🔥 Display Info Row
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

                                    // 🔥 Nested Repeater สำหรับหลายค่า Measurements
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
                                                    // คำนวณค่าเฉลี่ยจาก measurements ทั้งหมด
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

                                    // 🔥 Result Section
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
                                                    ->hidden(fn (Get $get) => ($get('std_label') === 'วัดเกลียว'))
                                                    ->dehydrated()
                                                    ->extraAttributes(fn ($state) => [
                                                        'style' => 'font-family: monospace; font-weight: 600; text-align: center;'
                                                    ]),
                                                
                                                TextInput::make('Judgement')
                                                    ->label('Judgement')
                                                    ->disabled()
                                                    ->hidden(fn (Get $get) => ($get('std_label') === 'วัดเกลียว'))
                                                    ->dehydrated()
                                                    ->extraAttributes(fn ($state) => [
                                                        'style' => match($state) {
                                                            'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                                                            'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                                                            default => 'text-align: center;'
                                                        }
                                                    ]),

                                                Select::make('Judgement_manual')
                                                    ->label('Judgement')
                                                    ->options([
                                                        'Pass' => 'Pass',
                                                        'Reject' => 'Reject',
                                                    ])
                                                    ->default('Pass')
                                                    ->selectablePlaceholder(false)
                                                    ->hidden(fn (Get $get) => ($get('std_label') !== 'วัดเกลียว'))
                                                    ->live()
                                                    ->afterStateHydrated(fn ($component, Get $get) => $component->state($get('Judgement') ?: 'Pass'))
                                                    ->afterStateUpdated(fn (Set $set, $state) => $set('Judgement', $state))
                                                    ->dehydrated(false)
                                                    ->extraAttributes(fn ($state) => [
                                                        'style' => match($state) {
                                                            'Pass' => 'background-color: #dcfce7 !important; color: #166534 !important; font-weight: bold !important; text-align: center;',
                                                            'Reject' => 'background-color: #fee2e2 !important; color: #991b1b !important; font-weight: bold !important; text-align: center;',
                                                            default => 'text-align: center;'
                                                        }
                                                    ]),

                                                Select::make('grade')
                                                    ->label('Grade')
                                                    ->disabled(fn (Get $get) => ($get('std_label') ?? '') !== 'วัดเกลียว')
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
                                    // คำนวณความถี่จาก cal_date และ next_cal_date
                                    $calDate = $get('cal_date');
                                    $instrumentId = $get('instrument_id');
                                    
                                    if (!$calDate || !$state || !$instrumentId) return;
                                    
                                    $instrument = \App\Models\Instrument::find($instrumentId);
                                    if (!$instrument) return;
                                    
                                    // 🔥 คำนวณและ save ทุกครั้ง
                                    $calDateCarbon = \Carbon\Carbon::parse($calDate);
                                    $nextDateCarbon = \Carbon\Carbon::parse($state);
                                    
                                    // คำนวณจำนวนเดือนที่ต่างกัน (ใช้ floor เพื่อปัดลง)
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
                                // 🔥 ขยายเป็น 2 columns เมื่อ next_cal_date หายไป
                                ->columnSpan(fn (Get $get) => ($get('result_status') === 'Reject' || $get('cal_level') === 'C') ? 2 : 1),
                        ]),
                    ]),
        ]);
    }

    // 🔥 ฟังก์ชันคำนวณค่าเฉลี่ยจาก measurements
    protected static function calculateAverageReading(Get $get, Set $set)
    {
        // 🔥 ตรวจสอบว่ากรอก value ครบ **ทุกช่อง** ของ **ทุก Point** หรือยัง
        $readings = $get('../../../../../calibration_data.readings') ?? [];
        
        $allValuesFilled = true;
        foreach ($readings as $reading) {
            $pointMeasurements = $reading['measurements'] ?? [];
            
            // ถ้า point นี้ไม่มี measurements เลย → ยังไม่ครบ
            if (empty($pointMeasurements)) {
                $allValuesFilled = false;
                break;
            }
            
            // ตรวจสอบว่า **ทุก value** ใน point นี้ถูกกรอกแล้วหรือยัง
            foreach ($pointMeasurements as $m) {
                if (!isset($m['value']) || $m['value'] === '' || $m['value'] === null) {
                    $allValuesFilled = false;
                    break 2; // ออกจากทั้ง 2 loops
                }
            }
        }
        
        // 🔥 ถ้ายังกรอกไม่ครบ → ไม่คำนวณอะไรเลย
        if (!$allValuesFilled) {
            return;
        }
        
        // 🔥 ถ้ากรอกครบทุกช่องแล้ว → คำนวณทั้งหมด
        self::calculateAllPointsFromMeasurements($get, $set);
    }
    
    // 🔥 ฟังก์ชันคำนวณทุก Point พร้อมกัน (เมื่อกรอกครบแล้ว)
    protected static function calculateAllPointsFromMeasurements(Get $get, Set $set)
    {
        $readings = $get('../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../instrument_id');
        
        if (!$instrumentId || empty($readings)) return;
        
        $instrument = \App\Models\Instrument::find($instrumentId);
        if (!$instrument) return;
        
        $percentAdj = (float) ($instrument->percent_adj ?? 10);
        
        // คำนวณแต่ละ Point
        foreach ($readings as $index => $reading) {
            $stdLabel = $reading['std_label'] ?? '';
            
            // ถ้าเป็น 'วัดเกลียว' ให้ข้ามการคำนวณ
            if ($stdLabel === 'วัดเกลียว') {
                continue;
            }
            
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
            
            // 🔥 Format ค่าเฉลี่ย
            $formattedAvg = rtrim(rtrim(number_format($readingValue, 6, '.', ''), '0'), '.');
            
            // Set ค่าให้แต่ละ Point (รวมถึงค่าเฉลี่ย)
            $set("../../../../../calibration_data.readings.{$index}.reading", $formattedAvg);
            $set("../../../../../calibration_data.readings.{$index}.error", number_format($error, 4));
            $set("../../../../../calibration_data.readings.{$index}.Judgement", $judgement);
            $set("../../../../../calibration_data.readings.{$index}.grade", $grade);
        }
        
        // คำนวณ Overall Status และ Level
        self::calculateOverallStatus($get, $set);
    }
    
    // 🔥 ฟังก์ชันคำนวณสถานะรวม
    protected static function calculateOverallStatus(Get $get, Set $set)
    {
        $readings = $get('../../../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../../../instrument_id');
        
        if (!$instrumentId || empty($readings)) return;
        
        // 🔥 ตรวจสอบว่ากรอกครบทุก Point หรือยัง
        $allPointsFilled = true;
        foreach ($readings as $reading) {
            $readingValue = $reading['reading'] ?? null;
            if (is_null($readingValue) || $readingValue === '' || $readingValue == 0) {
                $allPointsFilled = false;
                break;
            }
        }
        
        // ถ้ายังไม่ครบ ไม่ต้องคำนวณ Overall Status
        if (!$allPointsFilled) {
            return;
        }
        
        $instrument = \App\Models\Instrument::find($instrumentId);
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

    // 🔥 ฟังก์ชันคำนวณทั้งหมดอัตโนมัติ (Auto Calculate All Points)
    protected static function calculateAllPointsAuto(Get $get, Set $set)
    {
        $readings = $get('../../../calibration_data.readings') ?? [];
        $instrumentId = $get('../../../instrument_id');
        
        if (!$instrumentId || empty($readings)) return;
        
        $instrument = \App\Models\Instrument::find($instrumentId);
        if (!$instrument) return;
        
        $percentAdj = (float) ($instrument->percent_adj ?? 10);
        
        // คำนวณแต่ละ Point
        foreach ($readings as $index => $reading) {
            $readingValue = (float) ($reading['reading'] ?? 0);
            
             // 🔥 ถ้าเป็น 'วัดเกลียว' ให้ข้ามการคำนวณ (ใช้ค่าที่ User เลือกเอง)
             if (($reading['std_label'] ?? '') === 'วัดเกลียว') {
                continue;
            }

            // ข้าม Point ที่ยังไม่ได้กรอกค่า
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
            
            // Set ค่าให้แต่ละ Point
            $set("../../../calibration_data.readings.{$index}.error", number_format($error, 4));
            $set("../../../calibration_data.readings.{$index}.Judgement", $judgement);
            $set("../../../calibration_data.readings.{$index}.grade", $grade);
        }
        
        // คำนวณ Overall Status และ Level
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
        
        // Update Next Cal Date
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

    // 🔥 ฟังก์ชันคำนวณทั้งหมด (Calculate All Points - สำหรับปุ่ม)
    protected static function calculateAllPoints(Get $get, Set $set)
    {
        $readings = $get('calibration_data.readings') ?? [];
        $instrumentId = $get('instrument_id');
        
        if (!$instrumentId || empty($readings)) return;
        
        $instrument = \App\Models\Instrument::find($instrumentId);
        if (!$instrument) return;
        
        $percentAdj = (float) ($instrument->percent_adj ?? 10);
        
        // คำนวณแต่ละ Point
        foreach ($readings as $index => $reading) {
            $readingValue = (float) ($reading['reading'] ?? 0);
            
             // 🔥 ถ้าเป็น 'วัดเกลียว' ให้ข้ามการคำนวณ (ใช้ค่าที่ User เลือกเอง)
             if (($reading['std_label'] ?? '') === 'วัดเกลียว') {
                continue;
            }

            // ข้าม Point ที่ยังไม่ได้กรอกค่า
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
            
            // Set ค่าให้แต่ละ Point
            $set("calibration_data.readings.{$index}.error", number_format($error, 4));
            $set("calibration_data.readings.{$index}.Judgement", $judgement);
            $set("calibration_data.readings.{$index}.grade", $grade);
        }
        
        // คำนวณ Overall Status และ Level
        $readings = $get('calibration_data.readings') ?? [];
        $grades = collect($readings)->pluck('grade')->filter();
        
        $level = 'A';
        if ($grades->contains('C')) {
            $level = 'C';
        } elseif ($grades->contains('B')) {
            $level = 'B';
        }
        
        $status = $grades->contains('C') ? 'Reject' : 'Pass';
        
        $set('result_status', $status);
        $set('cal_level', $level);
        
        // Update Next Cal Date
        $calDate = $get('cal_date');
        if ($calDate) {
            $nextDate = match($level) {
                'A' => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12),
                'B' => \Carbon\Carbon::parse($calDate)->addMonth(),
                'C' => null,
                default => \Carbon\Carbon::parse($calDate)->addMonths($instrument->cal_freq_months ?? 12),
            };
            
            if ($nextDate) {
                $set('next_cal_date', $nextDate->format('Y-m-d'));
            }
        }
    }

    // 🔥 อัปเดต Next Cal Date ตาม Level (Fixed paths)
    protected static function updateNextCalDate(Set $set, Get $get, string $level)
    {
        // 🔥 FIX: อ่านจาก root level
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
            // 🔥 FIX: Set ที่ root level
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
            'index' => Pages\ListCalibrationPlugGauges::route('/'),
            'create' => Pages\CreateCalibrationPlugGauge::route('/create'),
            'view' => Pages\ViewCalibrationPlugGauge::route('/{record}'),
            'edit' => Pages\EditCalibrationPlugGauge::route('/{record}/edit'),
        ];
    }
}
