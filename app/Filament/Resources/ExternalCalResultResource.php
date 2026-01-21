<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExternalCalResultResource\Pages;
use App\Models\CalibrationRecord;
use App\Models\Instrument;
use App\Models\PurchasingRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Carbon\Carbon;

class ExternalCalResultResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'ผลการสอบเทียบ (External Cal)';
    protected static ?string $modelLabel = 'ผลการสอบเทียบภายนอก';
    protected static ?string $pluralModelLabel = 'ผลการสอบเทียบภายนอก';
    protected static ?string $navigationGroup = 'สอบเทียบภายนอก (External)';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('cal_place', 'External')
            ->with(['instrument.toolType', 'purchasingRecord']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Hidden fields for purchasing link
                Hidden::make('cal_place')->default('External'),
                Hidden::make('purchasing_record_id'),

                // 🔥 เพิ่ม hidden field สำหรับ calibration_type ทั้งใน column และ JSON
                Hidden::make('calibration_type')->default('ExternalCal')->dehydrated(),
                Hidden::make('calibration_data.calibration_type')->default('ExternalCal')->dehydrated(),

                // Section 1: ข้อมูลเครื่องมือ
                Section::make('ข้อมูลเครื่องมือ')
                    ->description('เลือกเครื่องมือที่ต้องการสอบเทียบ')
                    ->collapsible()
                    ->schema([
                        Grid::make(10)->schema([
                            Select::make('instrument_id')
                                ->label('เลือกเครื่องมือ (Code No)')
                                ->placeholder('รหัสเครื่องมือ หรือ รหัสประเภทเครื่องมือ')
                                ->relationship(
                                    'instrument',
                                    'code_no',
                                    fn (Builder $query) => $query
                                        ->where(function (Builder $q) {
                                            $q->where('cal_place', 'External')
                                              ->orWhere('cal_place', 'ExternalCal');
                                        })
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->code_no)
                                ->searchable(['code_no'])
                                ->preload()
                                ->required()
                                ->live()
                                ->columnSpan(4)
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if ($state) {
                                        $instrument = Instrument::with(['toolType', 'department'])->find($state);
                                        if ($instrument) {
                                            $set('instrument_name', $instrument->toolType?->name ?? '-');
                                            $set('instrument_size', $instrument->toolType?->size ?? '-');
                                            $set('instrument_serial', $instrument->serial_no ?? '-');
                                            $set('instrument_department', $instrument->department?->name ?? '-');
                                            
                                            // ดึงข้อมูลจาก dimension_specs ของ ToolType และแปลงเป็นรูปแบบ Repeater
                                            // สำหรับ External Cal ใช้เฉพาะ specs ที่มี criteria (cri_plus/cri_minus)
                                            $dimensionSpecs = $instrument->toolType?->dimension_specs ?? [];
                                            $ranges = [];
                                            
                                            foreach ($dimensionSpecs as $point) {
                                                // แต่ละ point อาจมีหลาย specs
                                                $specs = $point['specs'] ?? [];
                                                foreach ($specs as $spec) {
                                                    // กรองเฉพาะ specs ที่มี criteria (สำหรับ External Cal)
                                                    // ถ้าไม่มี cri_plus และ cri_minus = เป็น spec สำหรับ Internal Cal
                                                    if (empty($spec['cri_plus']) && empty($spec['cri_minus'])) {
                                                        continue; // ข้าม specs ที่ไม่มี criteria
                                                    }
                                                    
                                                    $ranges[] = [
                                                        'range_name' => $point['point'] ?? '',
                                                        'label' => $spec['label'] ?? '',
                                                        'criteria_plus' => $spec['cri_plus'] ?? null,
                                                        'criteria_minus' => $spec['cri_minus'] ?? null,
                                                        'unit' => $spec['cri_unit'] ?? 'um',
                                                        'error_max' => null, // ให้ user กรอกเอง
                                                        'index' => null,
                                                    ];
                                                }
                                            }
                                            
                                            // Pre-fill Repeater ด้วยข้อมูล Range/Criteria
                                            $set('calibration_data.ranges', $ranges);
                                            
                                            // ดึงข้อมูลจาก Record ก่อนหน้า (ถ้ามี)
                                            $lastRecord = \App\Models\CalibrationRecord::where('instrument_id', $state)
                                                ->where('cal_place', 'External')
                                                ->orderBy('cal_date', 'desc')
                                                ->first();
                                                
                                            if ($lastRecord) {
                                                $set('last_cal_date', $lastRecord->cal_date?->format('Y-m-d'));
                                                $set('last_cal_date_display', $lastRecord->cal_date?->format('d/m/Y'));
                                                $lastCalData = $lastRecord->calibration_data ?? [];
                                                // ตรวจสอบหลายชื่อ field เพราะข้อมูลเก่าอาจใช้ ErrorMaxNow
                                                $lastErrorMax = $lastCalData['error_max_now'] 
                                                    ?? $lastCalData['ErrorMaxNow'] 
                                                    ?? $lastCalData['drift_rate']
                                                    ?? null;
                                                $set('last_error_max', $lastErrorMax);
                                            }
                                        }
                                    }
                                }),

                            TextInput::make('instrument_name')
                                ->label('Name')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(3),

                            TextInput::make('instrument_size')
                                ->label('Size')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(3),
                        ]),

                        Grid::make(10)->schema([
                            TextInput::make('instrument_serial')
                                ->label('Serial No')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),

                            TextInput::make('instrument_department')
                                ->label('แผนก')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),
                        ]),
                    ]),
                
                 // Section 3: ข้อมูลการส่งสอบเทียบจริง (Sync to PurchasingRecord)
                Section::make('ข้อมูลการส่งสอบเทียบจริง')
                    ->description('ข้อมูลนี้จะถูกบันทึกไปยัง รายการส่งสอบเทียบ โดยอัตโนมัติ')
                    ->collapsible()
                    ->schema([
                        Grid::make(6)->schema([
                            TextInput::make('purchasing_cal_place')
                                ->label('สถานที่สอบเทียบจริง')
                                ->placeholder('บริษัทที่ส่งไปจริง')
                                ->columnSpan(2),

                            TextInput::make('purchasing_net_price')
                                ->label('Price (ราคาจริง)')
                                ->numeric()
                                ->prefix('฿')
                                ->columnSpan(2),

                            DatePicker::make('purchasing_send_date')
                                ->label('วันที่ส่งจริง')
                                ->displayFormat('d/m/Y')
                                ->columnSpan(2),
                        ]),

                        Grid::make(6)->schema([
                            TextInput::make('calibration_data.cer_no')
                                ->label('Cer No (Certificate No)')
                                ->columnSpan(3),

                            TextInput::make('calibration_data.trace_place')
                                ->label('TracePlace')
                                ->columnSpan(3),
                        ]),
                    ]),

                // Section 2: ข้อมูลการสอบเทียบ
                Section::make('ข้อมูลการสอบเทียบ')
                    ->description('ระบบได้ดึงข้อมูล LastCal ไว้เเล้ว กรุณาเลือกวันที่ Cal เพื่อให้ระบบคํานวณ FreqCal แบบอัตโนมัติ')
                    ->collapsible()
                    ->schema([
                        Grid::make(9)->schema([
                            DatePicker::make('cal_date')
                                ->label('Cal Date')
                                ->displayFormat('d/m/Y')
                                ->required()
                                ->live()
                                ->columnSpan(2)
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                    static::calculateFreqCal($set, $get);
                                }),

                            TextInput::make('last_cal_date_display')
                                ->label('LastCalDate')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),


                        ]),
                    ]),

               

                // Section 4: คำนวณหา ErrorMax (Drift Rate)
                Section::make('คำนวณหา ErrorMax')
                    ->description('กรอก ErrorMaxNow ระบบจะทําการดึง LastCal มาคํานวณ ErrorMax(Drift Rate) แบบอัตโนมัติ')
                    ->collapsible()
                    ->schema([
                        Grid::make(6)->schema([
                            TextInput::make('calibration_data.error_max_now')
                                ->label('ErrorMaxNow')
                                ->numeric()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, Get $get) {
                                    static::calculateDriftRate($set, $get);
                                })
                                ->columnSpan(2),

                             TextInput::make('calibration_data.last_error_max') // Changed to calibration_data.last_error_max
                                ->label('LastErrorMax')
                                ->disabled()
                                ->dehydrated() // Changed from dehydrated(false)
                                ->columnSpan(1),

                             TextInput::make('calibration_data.freq_cal') // Changed to calibration_data.freq_cal
                                ->label('FreqCal')
                                ->disabled()
                                ->dehydrated() // Changed from dehydrated(false)
                                ->prefix('/')
                                ->afterStateHydrated(function ($component, $state, Set $set) {
                                    $set('freq_cal_raw2', $state);
                                })
                                ->formatStateUsing(fn ($state) => $state ? number_format(floatval($state), 2, '.', '') : null)
                                ->dehydrateStateUsing(fn ($state, Get $get) => $get('freq_cal_raw2') ? floatval($get('freq_cal_raw2')) : $state)
                                ->columnSpan(1)
                                ->extraInputAttributes(fn (Get $get) => [
                                    'data-full-value' => $get('freq_cal_raw2') ? number_format(floatval($get('freq_cal_raw2')), 6, '.', '') : null,
                                    'x-data' => '{}',
                                    'x-on:mouseover' => '
                                        $el.dataset.original = $el.value; 
                                        if($el.dataset.fullValue) {
                                            $el.value = $el.dataset.fullValue; 
                                        }
                                    ',
                                    'x-on:mouseout' => '
                                        if($el.dataset.original) {
                                            $el.value = $el.dataset.original;
                                        }
                                    ',
                                    'style' => 'cursor: help;', 
                                ]),

                            Hidden::make('freq_cal_raw2'),

                            TextInput::make('calibration_data.drift_rate')
                                ->label('ErrorMax (Drift Rate)')
                                ->disabled()
                                ->dehydrated()
                                ->prefix('=')
                                ->afterStateHydrated(function ($component, $state, Set $set) {
                                    $set('drift_rate_raw', $state);
                                })
                                ->formatStateUsing(fn ($state) => $state ? number_format(floatval($state), 2, '.', '') : null)
                                ->columnSpan(2)
                                ->extraInputAttributes(fn (Get $get) => [
                                    'data-full-value' => $get('drift_rate_raw') ? number_format(floatval($get('drift_rate_raw')), 6, '.', '') : null,
                                    'x-data' => '{}',
                                    'x-on:mouseover' => '
                                        $el.dataset.original = $el.value; 
                                        if($el.dataset.fullValue) {
                                            $el.value = $el.dataset.fullValue; 
                                        }
                                    ',
                                    'x-on:mouseout' => '
                                        if($el.dataset.original) {
                                            $el.value = $el.dataset.original;
                                        }
                                    ',
                                    'style' => 'cursor: help;', 
                                ]),

                            Hidden::make('drift_rate_raw'),
                        ]),
                    ]),

                // Section 4: Range & Criteria (Repeater)
                Section::make('Range & Criteria')
                    ->description('กรอกค่า Error max ตามจุดตรวจสอบ (Range1, Range2, Range3...) เพื่อคำนวณ Index')
                    ->collapsible()
                    ->schema([
                        Repeater::make('calibration_data.ranges')
                            ->label('รายการ Range')
                            ->schema([
                                Grid::make(8)->schema([
                                    TextInput::make('range_name')
                                        ->label('Range')
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(1),

                                    TextInput::make('label')
                                        ->label('การใช้งาน')
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(2),

                                    TextInput::make('criteria_plus')
                                        ->label('Criteria+')
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(1),

                                    TextInput::make('criteria_minus')
                                        ->label('Criteria-')
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(1),

                                    TextInput::make('unit')
                                        ->label('Unit')
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(1),

                                       TextInput::make('error_max')
                                        // ... (ส่วนตั้งค่าอื่นๆ)
                                        ->live(onBlur: true) 
                                        // เพิ่ม $component เข้ามาใน function arguments
                                        ->afterStateUpdated(function (Set $set, Get $get, $state, $component) { 
        
                                        $ranges = $get('../../ranges') ?? [];
                                        $ranges = $get('../../ranges') ?? [];
                                        $freqCal = floatval($get('../../freq_cal_raw2') ?: ($get('../../../freq_cal_raw2') ?: ($get('../../calibration_data.freq_cal') ?: ($get('../../../calibration_data.freq_cal') ?: 1))));

                                        // --- [ส่วนที่เพิ่ม] : อัปเดตค่าปัจจุบันลงไปใน Array ก่อนคำนวณ ---
                                        // หาว่าเรากำลังพิมพ์อยู่ที่บรรทัดไหน (Key ไหน)
                                        $pathParts = explode('.', $component->getStatePath());
                                        $currentKey = $pathParts[count($pathParts) - 2]; 

                                        // ยัดค่าใหม่ ($state) ลงไปในข้อมูลชุดนั้นทันที
                                        if (isset($ranges[$currentKey])) {
                                            $ranges[$currentKey]['error_max'] = $state;
                                        }
                                        // -----------------------------------------------------------

                                        $indices = [];
                                        $isReject = false; // 🔥 ตัวแปรเช็คว่า Reject หรือไม่
                                        
                                        foreach ($ranges as $key => $range) {
                                            $criteriaPlus = floatval($range['criteria_plus'] ?? 0);
                                            $criteriaMinus = floatval($range['criteria_minus'] ?? 0);
                                            $criteria = max(abs($criteriaPlus), abs($criteriaMinus));
            
                                            // ตอนนี้ $range['error_max'] จะเป็นค่าใหม่ 0.1 แล้ว (เฉพาะบรรทัดที่พิมพ์)
                                            $errorMax = floatval($range['error_max'] ?? 0);
                                            $errorMaxAbs = abs($errorMax);
            
                                            if ($errorMaxAbs != 0 && $criteria > 0) {
                                                $index = ($criteria / $errorMaxAbs) * $freqCal;
                                                $indices[] = $index;
                                                $set("../../ranges.{$key}.index", (floor($index) == $index) ? number_format($index, 0, '.', '') : number_format($index, 2, '.', ''));
                                                $set("../../ranges.{$key}.index_raw", round($index, 6)); // Raw: 6 decimals
                                            } else {
                                                $indices[] = 5.00;
                                                $set("../../ranges.{$key}.index", "5");
                                                $set("../../ranges.{$key}.index_raw", 5);
                                            }
                                            
                                            // 🔥 เช็ค Pass/Reject: ถ้า error_max เกินช่วง criteria = Reject ทันที
                                            // criteria_plus เป็นค่าบวก (เช่น +5), criteria_minus เป็นค่าลบ (เช่น -5)
                                            if ($errorMax != 0) {
                                                if ($errorMax > $criteriaPlus || $errorMax < $criteriaMinus) {
                                                    $isReject = true;
                                                }
                                            }
                                        }

                                        // ... (ส่วนคำนวณ Index รวม / NewIndex / AmountDay ด้านล่าง เหมือนเดิม)
        
                                        // คำนวณ Index รวม
                                        $indexCombined = min($indices); 
                                        $set('../../index_combined', (floor($indexCombined) == $indexCombined) ? number_format($indexCombined, 0, '.', '') : number_format($indexCombined, 2, '.', ''));
                                        $set('../../../index_combined_raw', round($indexCombined, 6)); // Raw: 6 decimals

                                        // คำนวณ NewIndex
                                        if ($indexCombined >= 2.00 && $indexCombined < 5.00) {
                                            $newIndex = $indexCombined - 1.00;
                                        } else {
                                            $newIndex = $indexCombined;
                                        }
                                        $newIndex = max(0, $newIndex);
                                        $set('../../new_index', (floor($newIndex) == $newIndex) ? number_format($newIndex, 0, '.', '') : number_format($newIndex, 2, '.', ''));
                                        $set('../../../new_index_raw', round($newIndex, 6)); // Raw: 6 decimals

                                        // คำนวณ AmountDay
                                        $amountDay = round($newIndex * 365, 2);
                                        $set('../../amount_day', (floor($amountDay) == $amountDay) ? number_format($amountDay, 0, '.', '') : number_format($amountDay, 2, '.', ''));
                                        $set('../../../amount_day_raw', round($amountDay, 6)); // Raw: 6 decimals

                                        // คำนวณ Next Cal
                                        $calDate = $get('../../../cal_date');
                                        if ($calDate && $amountDay > 0) {
                                            $nextCal = \Carbon\Carbon::parse($calDate)->addDays((int)$amountDay)->format('Y-m-d');
                                            $set('../../../next_cal_date', $nextCal);
                                        }
                                        
                                        // 🔥 ตั้งค่า result_status อัตโนมัติ
                                        $set('../../../result_status', $isReject ? 'Reject' : 'Pass');
                                    }),

                                    TextInput::make('index')
                                        ->label('Index')
                                        ->disabled()
                                        ->dehydrated()
                                        ->afterStateHydrated(function ($component, $state, Set $set) {
                                            $set('index_raw', $state);
                                        })
                                        ->formatStateUsing(fn ($state) => $state ? number_format(floatval($state), 2, '.', '') : null)
                                        ->dehydrateStateUsing(fn ($state, Get $get) => $get('index_raw') ? floatval($get('index_raw')) : $state)
                                        ->columnSpan(1)
                                        ->extraInputAttributes(fn (Get $get) => [
                                            'data-full-value' => $get('index_raw') !== null ? (float)$get('index_raw') : null,
                                            'x-data' => '{}',
                                            'x-on:mouseover' => '
                                                $el.dataset.original = $el.value; 
                                                if($el.dataset.fullValue) {
                                                    $el.value = $el.dataset.fullValue; 
                                                }
                                            ',
                                            'x-on:mouseout' => '
                                                if($el.dataset.original) {
                                                    $el.value = $el.dataset.original;
                                                }
                                            ',
                                            'style' => 'cursor: help;', 
                                        ]),

                                    Hidden::make('index_raw'),
                                ]),
                            ])
                            ->maxItems(5)
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                // Section 5: ผลการคำนวณ
                Section::make('ผลการคำนวณ & สรุปผล (Conclusion)')
                    ->collapsible()
                    ->schema([
                        Grid::make(6)->schema([
                            TextInput::make('calibration_data.index_combined')
                                ->label('Index (รวม)')
                                ->disabled()
                                ->dehydrated()
                                ->afterStateHydrated(function ($component, $state, Set $set) {
                                    $set('index_combined_raw', $state);
                                })
                                ->formatStateUsing(fn ($state) => $state ? number_format(floatval($state), 2, '.', '') : null)
                                ->dehydrateStateUsing(fn ($state, Get $get) => $get('index_combined_raw') ? floatval($get('index_combined_raw')) : $state)
                                ->columnSpan(2)
                                ->extraInputAttributes(fn (Get $get) => [
                                    'data-full-value' => $get('index_combined_raw') !== null ? (float)$get('index_combined_raw') : null,
                                    'x-data' => '{}',
                                    'x-on:mouseover' => '
                                        $el.dataset.original = $el.value; 
                                        if($el.dataset.fullValue) {
                                            $el.value = $el.dataset.fullValue; 
                                        }
                                    ',
                                    'x-on:mouseout' => '
                                        if($el.dataset.original) {
                                            $el.value = $el.dataset.original;
                                        }
                                    ',
                                    'style' => 'cursor: help;', 
                                ]),

                            Hidden::make('index_combined_raw'),

                            TextInput::make('calibration_data.new_index')
                                ->label('NewIndex')
                                ->disabled()
                                ->dehydrated()
                                ->afterStateHydrated(function ($component, $state, Set $set) {
                                    $set('new_index_raw', $state);
                                })
                                ->formatStateUsing(fn ($state) => $state ? number_format(floatval($state), 2, '.', '') : null)
                                ->dehydrateStateUsing(fn ($state, Get $get) => $get('new_index_raw') ? floatval($get('new_index_raw')) : $state)
                                ->columnSpan(2)
                                ->extraInputAttributes(fn (Get $get) => [
                                    'data-full-value' => $get('new_index_raw') !== null ? (float)$get('new_index_raw') : null,
                                    'x-data' => '{}',
                                    'x-on:mouseover' => '
                                        $el.dataset.original = $el.value; 
                                        if($el.dataset.fullValue) {
                                            $el.value = $el.dataset.fullValue; 
                                        }
                                    ',
                                    'x-on:mouseout' => '
                                        if($el.dataset.original) {
                                            $el.value = $el.dataset.original;
                                        }
                                    ',
                                    'style' => 'cursor: help;', 
                                ]),

                            Hidden::make('new_index_raw'),

                            TextInput::make('calibration_data.amount_day')
                                ->label('AmountDay')
                                ->disabled()
                                ->dehydrated()
                                ->suffix('วัน')
                                ->afterStateHydrated(function ($component, $state, Set $set) {
                                    $set('amount_day_raw', $state);
                                })
                                ->formatStateUsing(fn ($state) => $state ? ((floatval($state) == intval($state)) ? number_format(floatval($state), 0, '.', '') : number_format(floatval($state), 2, '.', '')) : null)
                                ->dehydrateStateUsing(fn ($state, Get $get) => $get('amount_day_raw') ? floatval($get('amount_day_raw')) : $state)
                                ->columnSpan(2)
                                ->extraInputAttributes(fn (Get $get) => [
                                    'data-full-value' => $get('amount_day_raw') !== null ? (float)$get('amount_day_raw') : null,
                                    'x-data' => '{}',
                                    'x-on:mouseover' => '
                                        $el.dataset.original = $el.value; 
                                        if($el.dataset.fullValue) {
                                            $el.value = $el.dataset.fullValue; 
                                        }
                                    ',
                                    'x-on:mouseout' => '
                                        if($el.dataset.original) {
                                            $el.value = $el.dataset.original;
                                        }
                                    ',
                                    'style' => 'cursor: help;', 
                                ]),
                            
                            Hidden::make('amount_day_raw'),
                        ]),

                        Grid::make(6)->schema([
                            Select::make('result_status')
                                ->label('Result')
                                ->options([
                                    'Pass' => 'Pass',
                                    'Reject' => 'Reject',
                                ])
                                ->native(false)
                                ->columnSpan(2),

                            DatePicker::make('next_cal_date')
                                ->label('Next Cal')
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->columnSpan(2),
                        ]),

                        Textarea::make('remark')
                            ->label('Remark')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                // Section 6: Certificate
                Section::make('Certificate')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('certificate_file')
                            ->label('อัพโหลด Certificate PDF')
                            ->disk('public')
                            ->directory('external-certificates')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * คำนวณ FreqCal
     */
   protected static function calculateFreqCal(Set $set, Get $get): void
    {
        $calDate = $get('cal_date');
        $lastCalDate = $get('last_cal_date');
        
        if ($calDate && $lastCalDate) {
            try {
                $calDateCarbon = \Carbon\Carbon::parse($calDate);
                $lastCalDateCarbon = \Carbon\Carbon::parse($lastCalDate);
                $diffDays = abs($calDateCarbon->diffInDays($lastCalDateCarbon)); 
                
                // แก้เป็น 6 ตำแหน่ง
                $freqCal = round($diffDays / 365, 6); 
                $set('calibration_data.freq_cal', round($freqCal, 2)); // UI: 2 decimals
                $set('freq_cal_raw2', $freqCal); // Raw: 6 decimals
            } catch (\Exception $e) {
                // If parsing fails, don't update
            }
        }
    }

    /**
     * คำนวณ Drift Rate (ErrorMax) และ recalculate NewIndex/AmountDay/NextCal
     * สูตร: ErrorMax = (ErrorMaxNow - LastErrorMax) / FreqCal
     */
    protected static function calculateDriftRate(Set $set, Get $get): void
    {
        $errorMaxNow = abs(floatval($get('calibration_data.error_max_now') ?? 0));
        $lastErrorMax = abs(floatval($get('calibration_data.last_error_max') ?? 0));
        $freqCal = abs(floatval($get('calibration_data.freq_cal') ?: 1));
        
        if ($freqCal > 0 && $errorMaxNow != 0) {
            $driftRate = ($errorMaxNow - $lastErrorMax) / $freqCal;
            // แก้เป็น 6 ตำแหน่ง
            $set('calibration_data.drift_rate', number_format($driftRate, 2, '.', ''));
            $set('drift_rate_raw', round($driftRate, 6)); // Raw: 6 decimals
        }
        
        $indexCombined = floatval($get('calibration_data.index_combined') ?? 0);
        
        if ($indexCombined > 0) {
            // Logic: ถ้า Index >= 2 ให้ลบ 1
            if ($indexCombined >= 2.00) {
                $newIndex = $indexCombined - 1.00;
            } else {
                $newIndex = $indexCombined;
            }
            
            $newIndex = max(0, $newIndex);
            // แก้เป็น 6 ตำแหน่ง
            // ถ้าเป็นจำนวนเต็ม ให้แสดงไม่มีทศนิยม ถ้ามีทศนิยมให้แสดง 2 ตำแหน่ง
            $set('calibration_data.new_index', (floor($newIndex) == $newIndex) ? number_format($newIndex, 0, '.', '') : number_format($newIndex, 2, '.', ''));
            $set('new_index_raw', round($newIndex, 6)); // Raw: 6 decimals
            
            // AmountDay
            $amountDay = $newIndex * 365;
            // ถ้าเป็นจำนวนเต็ม ให้แสดงไม่มีทศนิยม ถ้ามีทศนิยมให้แสดง 2 ตำแหน่ง
            $set('calibration_data.amount_day', (floor($amountDay) == $amountDay) ? number_format($amountDay, 0, '.', '') : number_format($amountDay, 2, '.', ''));
            $set('amount_day_raw', round($amountDay, 6)); // Raw: 6 decimals
            
            // Next Cal Date (ใช้ int สำหรับการบวกวัน)
            $calDate = $get('cal_date');
            if ($calDate && $amountDay > 0) {
                $nextCal = \Carbon\Carbon::parse($calDate)->addDays((int)round($amountDay))->format('Y-m-d');
                $set('next_cal_date', $nextCal);
            } else {
                $set('next_cal_date', null);
            }
        }
    }


    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('cal_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('instrument.code_no')
                    ->label('Code No')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('instrument.toolType.name')
                    ->label('Name')
                    ->limit(25)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('cal_date')
                    ->label('Cal Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('calibration_data.cer_no')
                    ->label('Cer No')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('result_status')
                    ->label('Result')
                    ->colors([
                        'success' => 'Pass',
                        'danger' => 'Reject',
                    ]),

                Tables\Columns\TextColumn::make('next_cal_date')
                    ->label('Next Cal')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('calibration_data.place_cal')
                    ->label('PlaceCAL')
                    ->limit(20),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('result_status')
                    ->label('ผลการสอบเทียบ')
                    ->options([
                        'Pass' => 'Pass',
                        'Reject' => 'Reject',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->color('gray'),
                Tables\Actions\EditAction::make()->color('warning'),
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
            'index' => Pages\ListExternalCalResults::route('/'),
            'create' => Pages\CreateExternalCalResult::route('/create'),
            'view' => Pages\ViewExternalCalResult::route('/{record}'),
            'edit' => Pages\EditExternalCalResult::route('/{record}/edit'),
        ];
    }
}
