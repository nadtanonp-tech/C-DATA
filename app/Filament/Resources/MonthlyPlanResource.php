<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonthlyPlanResource\Pages;
use App\Models\MonthlyPlan;
use App\Models\ToolType;
use App\Models\Department;
use App\Models\Instrument;
use App\Models\CalibrationRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class MonthlyPlanResource extends Resource
{
    protected static ?string $model = MonthlyPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Plan & Summary Cal Report';
    protected static ?string $modelLabel = 'Plan & Summary Cal Report';
    protected static ?string $pluralModelLabel = 'Plan & Summary Cal Report';
    protected static ?string $cluster = \App\Filament\Clusters\MonthlyReport::class;

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('ข้อมูลแผนสอบเทียบ') // 🟢 ส่วนที่ 1: ข้อมูลหลักของแผน (เดือน, ประเภท, แผนก)
                    ->schema([
                        Grid::make(5)->schema([
                            DatePicker::make('plan_month')
                                ->label('เดือน')
                                ->displayFormat('F Y')
                                ->required()
                                ->columnSpan(1),

                            Select::make('calibration_type')
                                ->label('ประเภทที่ใช้สอบเทียบ')
                                ->placeholder('เลือกประเภทที่ใช้สอบเทียบ')
                                ->options(array_combine([
                                    'KGauge', 'SnapGauge', 'PlugGauge', 'ThreadPlugGauge', 'SerrationPlugGauge',
                                    'ThreadRingGauge', 'SerrationRingGauge', 'ThreadPlugGaugeFitWear', 'VernierCaliper',
                                    'VernierDigital', 'VernierSpecial', 'DepthVernier', 'VernierHeightGauge',
                                    'DialVernierHeightGauge', 'MicroMeter', 'DialCaliper', 'DialIndicator',
                                    'DialTestIndicator', 'ThicknessGauge', 'ThicknessCaliper', 'CylinderGauge',
                                    'ChamferGauge', 'PressureGauge', 'ExternalCal'
                                ], [
                                    'K Gauge', 'Snap Gauge', 'Plug Gauge', 'Thread Plug Gauge', 'Serration Plug Gauge',
                                    'Thread Ring Gauge', 'Serration Ring Gauge', 'Thread Plug Gauge Fit Wear', 'Vernier Caliper',
                                    'Vernier Digital', 'Vernier Special', 'Depth Vernier', 'Vernier Height Gauge',
                                    'Dial Vernier Height Gauge', 'Micro Meter', 'Dial Caliper', 'Dial Indicator',
                                    'Dial Test Indicator', 'Thickness Gauge', 'Thickness Caliper', 'Cylinder Gauge',
                                    'Chamfer Gauge', 'Pressure Gauge', 'External Cal'
                                ]))
                                ->searchable()
                                ->columnSpan(2),

                            Select::make('department')
                                ->label('แผนก')
                                ->placeholder('เลือกแผนก')
                                ->options(fn () => Department::pluck('name', 'name')->toArray())
                                ->searchable()
                                ->columnSpan(1),

                            Select::make('status')
                                ->label('สถานะ')
                                ->options([
                                    'Plan' => 'Plan',
                                    'Completed' => 'Completed',
                                    'Remain' => 'Remain',
                                ])
                                ->default('Plan')
                                ->required()
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('ยอดแผน/สอบเทียบ') // 🟢 ส่วนที่ 2: ตัวเลขสรุป (เป้าหมาย vs ทำได้จริง)
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('plan_count')
                                ->label('Plan (จำนวนที่ต้องสอบ)')
                                ->numeric()
                                ->default(0)
                                ->live() // 🟢 สั่งให้ทำงานทันทีเมื่อแก้เลขเพื่อคำนวณ Remain
                                ->afterStateUpdated(fn ($state, $set, $get) => 
                                    // 🟢 คำนวณยอดคงเหลือทันที: Plan - Cal
                                    $set('remain_count', max(0, (int)$state - (int)$get('cal_count')))
                                ),

                            TextInput::make('cal_count')
                                ->label('Cal (จํานวนสอบเทียบแล้ว)')
                                ->numeric()
                                ->default(0)
                                ->live()
                                ->afterStateUpdated(fn ($state, $set, $get) => 
                                    $set('remain_count', max(0, (int)$get('plan_count') - (int)$state))
                                ),

                            TextInput::make('remain_count')
                                ->label('Remain (จํานวนคงเหลือ)')
                                ->numeric()
                                ->default(0)
                                ->disabled()
                                ->dehydrated(),
                        ]),
                    ]),

                Section::make('จํานวน Level (ผลการสอบเทียบ)') // 🟢 ส่วนที่ 3: สรุปผล Level A/B/C
                    ->schema([
                        Grid::make(6)->schema([
                            TextInput::make('level_a')
                                ->label('Level A')
                                ->numeric()
                                ->default(0),

                            TextInput::make('level_b')
                                ->label('Level B')
                                ->numeric()
                                ->default(0),

                            TextInput::make('level_c')
                                ->label('Level C')
                                ->numeric()
                                ->default(0),
                        ]),
                    ]),

                Section::make('หมายเหตุ')
                    ->schema([
                        Textarea::make('remark')
                            ->label('Remark')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('plan_month', 'desc')
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->columns([
                Tables\Columns\TextColumn::make('plan_month')
                    ->label('เดือน')
                    ->date('M Y')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(),

                Tables\Columns\TextColumn::make('calibration_type')
                    ->label('ประเภทที่ใช้สอบเทียบ')
                    ->formatStateUsing(fn (string $state): string => \Illuminate\Support\Str::headline($state))
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department')
                    ->label('แผนก')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->getStateUsing(function ($record) {
                        // 🟢 Auto Display: ถ้าเดือนเก่า ไม่เสร็จ -> Remain
                        $planDate = \Carbon\Carbon::parse($record->plan_month);
                        
                        if ($planDate->endOfMonth()->isPast() && $record->status !== 'Completed') {
                            return 'Remain';
                        }
                        
                        return $record->status;
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Plan' => 'warning',
                        'Completed' => 'success',
                        'Remain' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('plan_count')
                    ->label('Set/Pcs')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('cal_count')
                    ->label('Cal')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('level_a')
                    ->label('A')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('level_b')
                    ->label('B')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('level_c')
                    ->label('C')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('remain_count')
                    ->label('Remain')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('remark')
                    ->label('Remark')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(30),
            ])
            ->filters([
                // Filter by Year
                Tables\Filters\SelectFilter::make('plan_year')
                    ->label('ปี (Year)')
                    ->searchable()
                    ->options(function () {
                        return \App\Models\MonthlyPlan::selectRaw('EXTRACT(YEAR FROM plan_month) as year')
                            ->distinct()
                            ->orderByDesc('year')
                            ->pluck('year', 'year')
                            ->toArray();
                    })
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereYear('plan_month', $data['value']);
                        }
                    })
                    ->native(false),

                // Filter by Month
                Tables\Filters\SelectFilter::make('plan_month')
                    ->label('เดือน (Month)')
                    ->options([
                        '1' => 'มกราคม', '2' => 'กุมภาพันธ์', '3' => 'มีนาคม', '4' => 'เมษายน',
                        '5' => 'พฤษภาคม', '6' => 'มิถุนายน', '7' => 'กรกฎาคม', '8' => 'สิงหาคม',
                        '9' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม',
                    ])
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereMonth('plan_month', $data['value']);
                        }
                    })
                    ->native(false),

                // Filter by Calibration Type
                Tables\Filters\SelectFilter::make('calibration_type')
                    ->label('ประเภทที่ใช้สอบเทียบ (Cal Type)')
                    ->options(function () {
                        return \App\Models\MonthlyPlan::select('calibration_type')
                            ->distinct()
                            ->whereNotNull('calibration_type')
                            ->pluck('calibration_type', 'calibration_type')
                            ->toArray();
                    })
                    ->columnSpan(2)
                    ->native(false),

                // Filter by Status
                Tables\Filters\SelectFilter::make('status')
                    ->label('สถานะ (Status)')
                    ->options([
                        'Plan' => 'Plan',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Overdue' => 'Overdue',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('department')
                    ->label('แผนก')
                    ->searchable()
                    ->options(fn () => Department::pluck('name', 'name')->toArray())
                    ->native(false),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->headerActions([  
                // Internal Plan Report Action
                Action::make('internal_plan_report')
                    ->label('Export Internal Plan')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Small)
                    ->centerModal()
                    ->modalHeading('Export Internal Plan')
                    ->modalDescription('เลือกเงื่อนไขสำหรับออกรายงานแผนภายใน (Internal Plan)')
                    ->modalSubmitActionLabel('Submit')
                    ->form([
                        DatePicker::make('month')
                            ->label('เดือน')
                            ->displayFormat('F Y')
                            ->native(false)
                            ->default(now()->startOfMonth())
                            ->required(),
                        Select::make('department')
                            ->label('Department')
                            ->native(false)
                            ->options(fn () => Department::pluck('name', 'name')->toArray())
                            ->placeholder('All Departments'),
                        Select::make('calibration_type')
                            ->label('Calibration Type')
                            ->native(false)
                            ->options(fn () => \App\Models\MonthlyPlan::select('calibration_type')
                                ->distinct()
                                ->whereNotNull('calibration_type')
                                ->pluck('calibration_type', 'calibration_type')
                                ->toArray())
                            ->placeholder('All Types'),
                        Select::make('level')
                            ->label('Level')
                            ->native(false)
                            ->options([
                                'A' => 'Level A',
                                'B' => 'Level B',
                                'C' => 'Level C',
                            ])
                            ->default('A')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $month = Carbon::parse($data['month']);
                        $start = $month->copy()->startOfMonth()->format('Y-m-d');
                        $end = $month->copy()->endOfMonth()->format('Y-m-d');
                        $dept = $data['department'] ?? 'all';
                        $calType = $data['calibration_type'] ?? 'all';
                        $level = $data['level'] ?? 'A';

                        return redirect()->to(route('monthly-plan.pdf', [
                            'pdf_type' => 'internal_plan',
                            'start_date' => $start,
                            'end_date' => $end,
                            'department' => $dept,
                            'calibration_type' => $calType,
                            'level' => $level,
                        ]));
                    })
                    ->openUrlInNewTab(true),

                // Summary Report Action
                Action::make('summary_report')
                    ->label('Export Summary Cal')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Small)
                    ->centerModal()
                    ->modalHeading('Export Summary Calibration')
                    ->modalDescription('เลือกเงื่อนไขสำหรับออกรายงานสรุปผลการสอบเทียบ (Summary Report)')
                    ->modalSubmitActionLabel('Submit')
                    ->form([
                        DatePicker::make('month')
                            ->label('Month')
                            ->native(false)
                            ->displayFormat('F Y')
                            ->default(now()->startOfMonth())
                            ->required(),
                        Select::make('department')
                            ->label('Department')
                            ->native(false)
                            ->options(fn () => Department::pluck('name', 'name')->toArray())
                            ->placeholder('All Departments'),
                        Select::make('calibration_type')
                            ->label('Calibration Type')
                            ->native(false)
                            ->options(fn () => \App\Models\MonthlyPlan::select('calibration_type')
                                ->distinct()
                                ->whereNotNull('calibration_type')
                                ->pluck('calibration_type', 'calibration_type')
                                ->toArray())
                            ->placeholder('All Types'),
                        Select::make('status')
                            ->label('Status')
                            ->native(false)
                            ->options([
                                'Plan' => 'Plan',
                                'Completed' => 'Completed',
                                'Remain' => 'Remain',
                            ])
                            ->placeholder('All Statuses'),
                    ])
                    ->action(function (array $data) {
                        $month = Carbon::parse($data['month']);
                        $start = $month->copy()->startOfMonth()->format('Y-m-d');
                        $end = $month->copy()->endOfMonth()->format('Y-m-d');
                        $dept = $data['department'] ?? 'all';
                        $calType = $data['calibration_type'] ?? 'all';
                        $status = $data['status'] ?? 'all';

                        return redirect()->to(route('monthly-plan.pdf', [
                            'pdf_type' => 'monthly_report',
                            'start_date' => $start,
                            'end_date' => $end,
                            'department' => $dept,
                            'calibration_type' => $calType,
                            'status' => $status,
                        ]));
                    })
                    ->openUrlInNewTab(true),

                    // Sync Data Action
                    Action::make('sync_data') // 🟢 ปุ่มดึงข้อมูลอัตโนมัติจากเครื่องมือและประวัติสอบเทียบ
                    ->label('Sync Data')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Small)
                    ->centerModal()
                    ->modalIcon('heroicon-o-arrow-path')
                    ->modalHeading('Sync Data')
                    ->modalDescription('เลือกเดือนที่ต้องการอัปเดตข้อมูล')
                    ->modalSubmitActionLabel('Start Sync')
                    ->form([
                        DatePicker::make('month')
                            ->hiddenLabel()
                            ->native(false)
                            ->placeholder('Select Month')
                            ->displayFormat('F Y')
                            ->default(now()->startOfMonth())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $month = Carbon::parse($data['month']);
                        MonthlyPlanResource::syncDataForMonth($month);
                        Notification::make()
                            ->title('Sync Data Completed')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->color('warning'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    /**
     * Sync data from real calibration data (OPTIMIZED)
     * 🟢 ฟังก์ชันหลักสำหรับดึงข้อมูลจริงจากระบบมาอัปเดตแผน (ทำงานเบื้องหลัง)
     */
    public static function syncDataForMonth(Carbon $month): void
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        // 🟢 ขั้นตอนที่ 1: หาจำนวน "Remaining" (เครื่องมือที่ยังค้างสอบในเดือนนี้ + ยอดตกค้างจากเดือนก่อน)
        // ดึงจาก latest_calibration_logs ที่มี next_cal_date <= สิ้นเดือนที่เลือก
        // สูตรใหม่: Rolling Backlog (งานเก่าที่ยังไม่ทำ จะถูกทบมาเรื่อยๆ)
        $remainingCounts = DB::table('latest_calibration_logs as lcl')
            ->join('instruments', 'lcl.instrument_id', '=', 'instruments.id')
            ->join('departments', 'instruments.department_id', '=', 'departments.id')
            ->where('lcl.next_cal_date', '<=', $endOfMonth)
            ->where('lcl.calibration_type', '!=', 'ExternalCal') // 🟢 Exclude ExternalCal
            ->whereNotIn('instruments.status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']) // 🔥 Filter Inactive
            ->groupBy('departments.name', 'lcl.calibration_type')
            ->selectRaw('
                departments.name as department,
                lcl.calibration_type,
                COUNT(DISTINCT lcl.instrument_id) as remaining_count
            ')
            ->get()
            ->keyBy(fn($item) => $item->department . '_' . ($item->calibration_type ?? 'NONE'));

        // 🟢 ขั้นตอนที่ 2: หาจำนวน "Actual" (เครื่องมือที่สอบเทียบแล้วในเดือนนี้)
        // ดึงจาก calibration_logs ที่มี cal_date ตกในเดือนที่เลือก
        $calCounts = CalibrationRecord::join('instruments', 'calibration_logs.instrument_id', '=', 'instruments.id')
            ->join('departments', 'instruments.department_id', '=', 'departments.id')
            ->whereBetween('calibration_logs.cal_date', [$startOfMonth, $endOfMonth])
            ->where('calibration_logs.calibration_type', '!=', 'ExternalCal') // 🟢 Exclude ExternalCal
            ->whereNotIn('instruments.status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']) // 🔥 Filter Inactive
            ->groupBy('departments.name', 'calibration_logs.calibration_type')
            ->selectRaw('
                departments.name as department,
                calibration_logs.calibration_type,
                COUNT(*) as cal_count,
                SUM(CASE WHEN calibration_logs.cal_level = \'A\' THEN 1 ELSE 0 END) as level_a,
                SUM(CASE WHEN calibration_logs.cal_level = \'B\' THEN 1 ELSE 0 END) as level_b,
                SUM(CASE WHEN calibration_logs.cal_level = \'C\' THEN 1 ELSE 0 END) as level_c
            ')
            ->get()
            ->keyBy(fn($item) => $item->department . '_' . ($item->calibration_type ?? 'NONE'));

        // 🟢 ขั้นตอนที่ 3: รวมข้อมูล (Set/Pcs = Remaining + Actual)
        $allKeys = $remainingCounts->keys()->merge($calCounts->keys())->unique();

        foreach ($allKeys as $key) {
            $remainingData = $remainingCounts->get($key);
            $calData = $calCounts->get($key);

            // Extract info
            if ($remainingData) {
                $department = $remainingData->department;
                $calibrationType = $remainingData->calibration_type;
            } else {
                $department = $calData->department;
                $calibrationType = $calData->calibration_type;
            }

            if (empty($calibrationType)) continue;

            $remainingCount = $remainingData?->remaining_count ?? 0;  // ยังค้างสอบ
            $calCount = $calData?->cal_count ?? 0;                     // สอบแล้ว
            $planCount = $remainingCount + $calCount;                  // Set/Pcs = Remaining + Actual

            $levelA = $calData?->level_a ?? 0;
            $levelB = $calData?->level_b ?? 0;
            $levelC = $calData?->level_c ?? 0;

            // 🟢 Logic 3 Statuses: Plan, Completed, Remain
            $status = 'Plan';
            $isPast = $startOfMonth->endOfMonth()->isPast();

            if ($calCount >= $planCount && $planCount > 0) {
                $status = 'Completed';
            } elseif ($isPast) {
                $status = 'Remain'; // เลยกำหนด (เดือนเก่าไม่เสร็จ)
            } else {
                $status = 'Plan'; // ยังไม่เริ่ม หรือ กำลังทำ (เดือนปัจจุบัน/อนาคต)
            }

            MonthlyPlan::updateOrCreate(
                [
                    'plan_month' => $startOfMonth->format('Y-m-d'),
                    'department' => $department,
                    'calibration_type' => $calibrationType,
                ],
                [
                    'tool_type_id' => null, 
                    'status' => $status,
                    'plan_count' => $planCount,
                    'cal_count' => $calCount,
                    'remain_count' => $remainingCount,
                    'level_a' => $levelA,
                    'level_b' => $levelB,
                    'level_c' => $levelC,
                ]
            );
        }
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMonthlyPlans::route('/'),
            'create' => Pages\CreateMonthlyPlan::route('/create'),
            'edit' => Pages\EditMonthlyPlan::route('/{record}/edit'),
        ];
    }
}
