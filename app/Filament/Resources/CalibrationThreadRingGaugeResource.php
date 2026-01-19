<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalibrationThreadRingGaugeResource\Pages;
use App\Models\CalibrationRecord;
use App\Models\Instrument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
use App\Filament\Clusters\CalibrationReport;

class CalibrationThreadRingGaugeResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;
    protected static ?string $slug = 'calibration-thread-ring-gauge'; // 🔥 กำหนด slug สำหรับ URL
    protected static ?string $navigationLabel = 'Thread & Serration Ring Gauge';
    protected static ?string $navigationGroup = 'Gauge Cal Report & Data';
    protected static ?string $cluster = CalibrationReport::class;
    protected static ?string $modelLabel = 'Thread & Serration Ring Gauge';
    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        // 🔥 กรอง Thread Ring Gauge: รองรับทั้ง 2 ประเภท
        return parent::getEloquentQuery()
            ->with(['instrument.toolType'])
            ->whereIn('calibration_type', ['ThreadRingGauge', 'SerrationRingGauge']);
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
                                        // 🔥 ค้นหา Thread Ring Gauge: code_no 8-04-%, 8-05-%, 8-07-%
                                        return \App\Models\Instrument::query()
                                            ->where(function ($q) {
                                                $q->where('code_no', 'LIKE', '8-04-%')
                                                  ->orWhere('code_no', 'LIKE', '8-05-%')
                                                  ->orWhere('code_no', 'LIKE', '8-07-%');
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
                                 
                                        // 🔥 สำหรับ Thread Ring Gauge - ใช้ 'วัดเกลียว' เท่านั้น
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
                                                            'trend' => $spec['trend'] ?? '-', // trend อยู่ที่ระดับ point
                                                            'measurement' => null, // ค่าวัด (ข้อความ)
                                                            'result' => null, // ผล Pass/Reject
                                                        ];
                                                    }
                                                }
                                            }
                                    
                                            // 🔥 เพิ่ม calibration_type สำหรับแยกประเภท (ทั้ง column และ JSON)
                                            $set('calibration_type', 'ThreadRingGauge');
                                            $set('calibration_data.calibration_type', 'ThreadRingGauge');
                                            $set('calibration_data.readings', $readings);
                                        }
                                    }),
                                DatePicker::make('cal_date')
                                    ->label('วันที่สอบเทียบ')
                                    ->default(now())
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $instrumentId = $get('instrument_id');
                                        if (!$instrumentId) return;
                                        
                                        $instrument = Instrument::find($instrumentId);
                                        if ($instrument && $state) {
                                            $set('next_cal_date', \Carbon\Carbon::parse($state)->addMonths($instrument->cal_freq_months ?? 6)->endOfMonth());
                                        }
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

                // 🔥 Section สำหรับวัดเกลียว - ง่าย ไม่มีการคำนวณ
                Section::make('ผลการวัดเกลียว (Thread Measurement)')
                    ->description('กรอกค่าวัดเกลียวเป็นข้อความ - ไม่มีการคำนวณ')
                    ->schema([
                        Repeater::make('calibration_data.readings')
                            ->label('รายการจุดตรวจสอบ')
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?') . ' - วัดเกลียว')
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
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    if ($state) {
                                        self::updateNextCalDate($set, $get, $state);
                                    }
                                })
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

    // 🔥 อัปเดต Next Cal Date ตาม Level
    protected static function updateNextCalDate(Set $set, Get $get, string $level)
    {
        // 🔥 ลอง path ต่างๆ เพราะ context อาจต่างกัน
        $calDate = $get('cal_date');
        $instrumentId = $get('instrument_id');
        
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
            $set('next_cal_date', $nextDate->format('Y-m-d'));
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
                    ->label('ประเภท')
                    ->options([
                        'ThreadRingGauge' => 'Thread Ring Gauge',
                        'SerrationRingGauge' => 'Serration Ring Gauge',
                    ])
                    ->native(false),
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
            'index' => Pages\ListCalibrationThreadRingGauges::route('/'),
            'create' => Pages\CreateCalibrationThreadRingGauge::route('/create'),
            'view' => Pages\ViewCalibrationThreadRingGauge::route('/{record}'),
            'edit' => Pages\EditCalibrationThreadRingGauge::route('/{record}/edit'),
        ];
    }
}
