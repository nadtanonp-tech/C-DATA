<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalibrationSnapGaugeResource\Pages;
use App\Filament\Resources\CalibrationSnapGaugeResource\RelationManagers;
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

class CalibrationSnapGaugeResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;

    protected static ?string $navigationLabel = 'Snap Gauge';
    protected static ?string $navigationGroup = 'Gauge Calibration';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        // 🔥 กรองเฉพาะ Snap Gauge โดยใช้ code_no pattern
        return parent::getEloquentQuery()
            ->with(['instrument.toolType']) // 🔥 แก้ N+1 Query
            ->whereHas('instrument', function ($query) {
                $query->where('code_no', 'LIKE', '8-02-%');
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
                                        // 🔥 ค้นหาเฉพาะ Snap Gauge ที่มี code_no ขึ้นต้นด้วย "8-02-"
                                        return \App\Models\Instrument::query()
                                            ->where('code_no', 'LIKE', '8-02-%') // กรองเฉพาะ Snap Gauge
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
                                    
                                            foreach ($dimensionSpecs as $spec) {
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
                    ->description('กรอกค่าตามจุดตรวจสอบ (A, B, C...)')
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?'))
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
                                        // ->native(false)
                                        ->disabled()
                                        ->dehydrated(),
                                    Forms\Components\Hidden::make('std_label')
                                        ->dehydrated(),

                                    TextInput::make('min_spec')
                                        ->label(fn (Get $get) => ($get('std_label') === 'วัดเกลียว') ? 'Standard' : 'Min')
                                        ->columnSpan(fn (Get $get) => ($get('std_label') === 'วัดเกลียว') ? 2 : 1)
                                        ->disabled()
                                        ->dehydrated(),
                                    
                                    TextInput::make('max_spec')
                                        ->label('Max')
                                        ->numeric()
                                        ->disabled()
                                        ->hidden(fn (Get $get) => ($get('std_label') === 'วัดเกลียว'))
                                        ->dehydrated(),
                                        
                                    TextInput::make('reading')
                                        ->label('ค่าที่วัดได้')
                                        ->columnSpan(fn (Get $get) => ($get('std_label') === 'วัดเกลียว') ? 2 : 1)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            // ตรวจสอบว่ากรอกครบทุก Point หรือยัง
                                            $readings = $get('../../../calibration_data.readings') ?? [];
                                            $allFilled = true;
                                            
                                            foreach ($readings as $reading) {
                                                if (empty($reading['reading']) || $reading['reading'] == 0) {
                                                    $allFilled = false;
                                                    break;
                                                }
                                            }
                                            
                                            // ถ้ากรอกครบทุก Point → คำนวณทั้งหมด
                                            if ($allFilled) {
                                                self::calculateAllPointsAuto($get, $set);
                                            }
                                        }),
                                    
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
                                        ->label('Grade Result')
                                        ->columnSpan(2)
                                        ->disabled(fn (Get $get) => ($get('std_label') ?? '') !== 'วัดเกลียว')
                                        ->options([
                                            'A' => 'Grade A (Pass)',
                                            'B' => 'Grade B (Warning)',
                                            'C' => 'Grade C (Fail)',
                                        ])
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
                                ->required(),
                            
                            TextInput::make('remark')
                                ->label('หมายเหตุ (Remark)'),
                        ]),
                    ]),
        ]);
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
                    ->badge(),
            ])
            ->filters([])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
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
            'index' => Pages\ListCalibrationSnapGauges::route('/'),
            'create' => Pages\CreateCalibrationSnapGauge::route('/create'),
            'view' => Pages\ViewCalibrationSnapGauge::route('/{record}'),
            'edit' => Pages\EditCalibrationSnapGauge::route('/{record}/edit'),
        ];
    }
}
