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
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class MonthlyPlanResource extends Resource
{
    protected static ?string $model = MonthlyPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'แผนสอบเทียบรายเดือน';
    protected static ?string $modelLabel = 'แผนสอบเทียบ';
    protected static ?string $pluralModelLabel = 'แผนสอบเทียบรายเดือน';
    protected static ?string $navigationGroup = 'รายงาน';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('ข้อมูลแผนสอบเทียบ')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('plan_month')
                                ->label('เดือน')
                                ->displayFormat('F Y')
                                ->native(false)
                                ->required()
                                ->columnSpan(1),

                            Select::make('tool_type_id')
                                ->label('ประเภทเครื่องมือ')
                                ->relationship('toolType', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(1),

                            Select::make('department')
                                ->label('แผนก')
                                ->options(fn () => Department::pluck('name', 'name')->toArray())
                                ->searchable()
                                ->columnSpan(1),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('status')
                                ->label('สถานะ')
                                ->default('Plan')
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('ยอดแผน/สอบเทียบ')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('plan_count')
                                ->label('Plan (จำนวนที่ต้องสอบ)')
                                ->numeric()
                                ->default(0)
                                ->live()
                                ->afterStateUpdated(fn ($state, $set, $get) => 
                                    $set('remain_count', max(0, (int)$state - (int)$get('cal_count')))
                                ),

                            TextInput::make('cal_count')
                                ->label('Cal (สอบเทียบแล้ว)')
                                ->numeric()
                                ->default(0)
                                ->live()
                                ->afterStateUpdated(fn ($state, $set, $get) => 
                                    $set('remain_count', max(0, (int)$get('plan_count') - (int)$state))
                                ),

                            TextInput::make('remain_count')
                                ->label('Remain (คงเหลือ)')
                                ->numeric()
                                ->default(0)
                                ->disabled()
                                ->dehydrated(),
                        ]),
                    ]),

                Section::make('Level (ผลการสอบเทียบ)')
                    ->schema([
                        Grid::make(3)->schema([
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
            ->columns([
                Tables\Columns\TextColumn::make('plan_month')
                    ->label('เดือน')
                    ->date('M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('toolType.name')
                    ->label('ประเภท')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('department')
                    ->label('แผนก')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Plan' => 'warning',
                        'Completed' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('plan_count')
                    ->label('Plan')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('cal_count')
                    ->label('Cal')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('cal_percent')
                    ->label('% Cal')
                    ->alignCenter()
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('level_a')
                    ->label('A')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('level_b')
                    ->label('B')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('level_c')
                    ->label('C')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('remain_count')
                    ->label('Remain')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->label('แผนก')
                    ->options(fn () => Department::pluck('name', 'name')->toArray()),

                Tables\Filters\SelectFilter::make('tool_type_id')
                    ->label('ประเภท')
                    ->relationship('toolType', 'name'),
            ])
            ->headerActions([
                // Sync Data Action
                Action::make('sync_data')
                    ->label('🔄 Sync Data')
                    ->color('info')
                    ->form([
                        DatePicker::make('month')
                            ->label('เดือน')
                            ->displayFormat('F Y')
                            ->native(false)
                            ->default(now()->startOfMonth())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        static::syncDataForMonth(Carbon::parse($data['month']));
                    })
                    ->requiresConfirmation()
                    ->modalHeading('ดึงข้อมูลอัตโนมัติ')
                    ->modalDescription('ระบบจะดึงยอด Plan/Cal จากข้อมูลจริง สำหรับเดือนที่เลือก'),

                // Export PDF Action
                Action::make('export_pdf')
                    ->label('📄 Export PDF')
                    ->color('success')
                    ->form([
                        DatePicker::make('start_date')
                            ->label('วันที่เริ่มต้น')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->required(),

                        DatePicker::make('end_date')
                            ->label('ถึงวันที่')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->required(),

                        Select::make('department')
                            ->label('แผนก')
                            ->options(fn () => array_merge(
                                ['all' => 'ทั้งหมด'],
                                Department::pluck('name', 'name')->toArray()
                            ))
                            ->default('all'),

                        Select::make('tool_type_id')
                            ->label('ประเภทเครื่องมือ')
                            ->options(fn () => array_merge(
                                ['all' => 'ทั้งหมด'],
                                ToolType::pluck('name', 'id')->toArray()
                            ))
                            ->default('all'),

                        Select::make('pdf_type')
                            ->label('รูปแบบ PDF')
                            ->options([
                                'monthly_report' => 'Monthly Report (ใบสรุปผล)',
                                'cal_plan' => 'Gauge/Inst Cal Plan (แผนรายละเอียด)',
                                'internal_plan' => 'Internal Calibration Plan (ใบให้หัวหน้าเซ็น)',
                            ])
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data) {
                        // TODO: Generate PDF
                        return redirect()->route('monthly-plan.pdf', $data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Sync data from real calibration data (OPTIMIZED)
     */
    public static function syncDataForMonth(Carbon $month): void
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        // 1. Get Plan counts (instruments due this month) - GROUP BY in one query
        $planCounts = Instrument::join('departments', 'instruments.department_id', '=', 'departments.id')
            ->whereBetween('next_cal_date', [$startOfMonth, $endOfMonth])
            ->groupBy('tool_type_id', 'departments.name')
            ->selectRaw('tool_type_id, departments.name as department, COUNT(*) as plan_count')
            ->get()
            ->keyBy(fn($item) => $item->tool_type_id . '_' . $item->department);

        // 2. Get Cal counts and levels - GROUP BY in one query
        $calCounts = CalibrationRecord::join('instruments', 'calibration_logs.instrument_id', '=', 'instruments.id')
            ->join('departments', 'instruments.department_id', '=', 'departments.id')
            ->whereBetween('calibration_logs.cal_date', [$startOfMonth, $endOfMonth])
            ->groupBy('instruments.tool_type_id', 'departments.name')
            ->selectRaw('
                instruments.tool_type_id,
                departments.name as department,
                COUNT(*) as cal_count,
                SUM(CASE WHEN calibration_logs.cal_level = \'A\' THEN 1 ELSE 0 END) as level_a,
                SUM(CASE WHEN calibration_logs.cal_level = \'B\' THEN 1 ELSE 0 END) as level_b,
                SUM(CASE WHEN calibration_logs.cal_level = \'C\' THEN 1 ELSE 0 END) as level_c
            ')
            ->get()
            ->keyBy(fn($item) => $item->tool_type_id . '_' . $item->department);

        // 3. Merge and insert/update
        $allKeys = $planCounts->keys()->merge($calCounts->keys())->unique();

        foreach ($allKeys as $key) {
            $planData = $planCounts->get($key);
            $calData = $calCounts->get($key);

            // Extract tool_type_id and department from key
            if ($planData) {
                $toolTypeId = $planData->tool_type_id;
                $department = $planData->department;
            } else {
                $toolTypeId = $calData->tool_type_id;
                $department = $calData->department;
            }

            $planCount = $planData?->plan_count ?? 0;
            $calCount = $calData?->cal_count ?? 0;
            $levelA = $calData?->level_a ?? 0;
            $levelB = $calData?->level_b ?? 0;
            $levelC = $calData?->level_c ?? 0;

            MonthlyPlan::updateOrCreate(
                [
                    'plan_month' => $startOfMonth->format('Y-m-d'),
                    'tool_type_id' => $toolTypeId,
                    'department' => $department,
                ],
                [
                    'status' => $calCount >= $planCount && $planCount > 0 ? 'Completed' : 'Plan',
                    'plan_count' => $planCount,
                    'cal_count' => $calCount,
                    'remain_count' => max(0, $planCount - $calCount),
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
