<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InternalCalPlanResource\Pages;
use App\Models\InternalCalPlan;
use App\Models\Instrument;
use App\Models\CalibrationRecord;
use App\Models\Department;
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

class InternalCalPlanResource extends Resource
{
    protected static ?string $model = InternalCalPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Gauge & Instrument Cal Plan';
    protected static ?string $modelLabel = 'Gauge & Instrument Cal Plan';
    protected static ?string $pluralModelLabel = 'Gauge & Instrument Cal Plan';
    // 🟢 จัดให้อยู่ใน Cluster เดียวกับ MonthlyPlanResource
    protected static ?string $cluster = \App\Filament\Clusters\MonthlyReport::class;
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('ข้อมูลเครื่องมือ')
                    ->schema([
                        Grid::make(7)->schema([
                            DatePicker::make('plan_month')
                                ->label('เดือน')
                                ->columnSpan(2)
                                ->displayFormat('F Y')
                                ->required(),

                            Select::make('instrument_id')
                                ->label('Code No')
                                ->options(Instrument::query()->pluck('code_no', 'id'))
                                ->searchable()
                                ->preload()
                                ->columnSpan(2)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $instrument = Instrument::find($state);
                                        if ($instrument) {
                                            $set('code_no', $instrument->code_no);
                                            $set('tool_name', $instrument->toolType->name ?? null);
                                            $set('tool_size', $instrument->toolType->size ?? null);
                                            $set('serial_no', $instrument->serial_no);
                                            $set('department', $instrument->department->name ?? null);

                                            // Fetch latest calibration log
                                            $lastLog = CalibrationRecord::where('instrument_id', $instrument->id)
                                                ->orderBy('cal_date', 'desc')
                                                ->first();

                                            if ($lastLog) {
                                                $set('cal_date', $lastLog->cal_date);
                                                $set('result_status', $lastLog->result_status ?? 'Pass');
                                                $set('cal_level', $lastLog->cal_level ?? 'A');
                                                $set('next_cal_date', $lastLog->next_cal_date);
                                                $set('remark', $lastLog->remark);
                                            }
                                        }
                                    }
                                }),

                            // Hidden field to store cached code_no string
                            Forms\Components\Hidden::make('code_no')
                                ->dehydrated(),

                            TextInput::make('tool_name')
                                ->label('Name')
                                ->disabled()
                                ->columnSpan(3)
                                ->dehydrated(),
                        ]),

                        Grid::make(7)->schema([
                            TextInput::make('tool_size')
                                ->label('Size')
                                ->disabled()
                                ->columnSpan(3)
                                ->dehydrated(),

                            TextInput::make('serial_no')
                                ->label('Serial No')
                                ->disabled()
                                ->columnSpan(3)
                                ->dehydrated(),

                            TextInput::make('department')
                                ->label('แผนก')
                                ->disabled()
                                ->dehydrated(),
                        ]),
                    ]),

                Section::make('ผลการสอบเทียบ')
                    ->schema([
                        Grid::make(7)->schema([
                            DatePicker::make('cal_date')
                                ->label('Cal Date')
                                ->columnSpan(2)
                                ->displayFormat('d/m/Y'),

                            Select::make('result_status')
                                ->label('ผลการ CAL')
                                ->options([
                                    'Pass' => 'Pass',
                                    'Fail' => 'Fail',
                                ])
                                ->native(false)
                                ->default('Pass'),

                            Select::make('cal_level')
                                ->label('Level')
                                ->options([
                                    'A' => 'Level A',
                                    'B' => 'Level B',
                                    'C' => 'Level C',
                                ])
                                ->native(false)
                                ->columnSpan(2)
                                ->default('A'),

                            DatePicker::make('next_cal_date')
                                ->label('Next Cal')
                                ->columnSpan(2)
                                ->displayFormat('d/m/Y'),
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
                // ลำดับ (Auto-generated row number)
                Tables\Columns\TextColumn::make('id')
                    ->label('ลำดับ')
                    ->rowIndex()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('plan_month')
                    ->label('เดือน')
                    ->date('M Y')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(),

                Tables\Columns\TextColumn::make('code_no')
                    ->label('Code No')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('tool_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('tool_size')
                    ->label('Size')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('serial_no')
                    ->label('Serial No')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('cal_date')
                    ->label('Cal Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('next_cal_date')
                    ->label('Next Cal')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                
                Tables\Columns\TextColumn::make('result_status')
                    ->label('ผลการ CAL')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pass' => 'success',
                        'Fail' => 'danger',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('cal_level')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'A' => 'success',
                        'B' => 'warning',
                        'C' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('department')
                    ->label('แผนก')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('remark')
                    ->label('Remark')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter by Year
                Tables\Filters\SelectFilter::make('plan_year')
                    ->label('ปี (Year)')
                    ->searchable()
                    ->options(function () {
                        return InternalCalPlan::selectRaw('EXTRACT(YEAR FROM plan_month) as year')
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
                Tables\Filters\SelectFilter::make('plan_month_filter')
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
                Tables\Filters\SelectFilter::make('code_type')
                    ->label('ประเภท (Type)')
                    ->options(function () {
                        return \App\Models\ToolType::distinct()
                            ->whereNotNull('code_type')
                            ->pluck('name', 'code_type')
                            ->toArray();
                    })
                    ->searchable()
                    ->columnSpan(2)
                    ->native(false),

                Tables\Filters\SelectFilter::make('result_status')
                    ->label('ผลการ Cal')
                    ->options([
                        'Pass' => 'Pass',
                        'Reject' => 'Reject',
                    ])
                    ->native(false),

                // Filter by Status
                Tables\Filters\SelectFilter::make('cal_level')
                    ->label('Level')
                    ->options([
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('department')
                    ->label('แผนก')
                    ->options(fn () => Department::pluck('name', 'name')->toArray())
                    ->native(false)
                    ->searchable(),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->headerActions([
                // Export Action
                Action::make('export_cal_plan')
                    ->label('Export Gauge & Instrument Plan')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Small)
                    ->centerModal()
                    ->modalHeading('Export Calibration Plan')
                    ->modalDescription('เลือกเงื่อนไขสำหรับออกรายงานแผนสอบเทียบ (Instrument Cal Plan)')
                    ->modalSubmitActionLabel('Submit')
                    ->form([
                        DatePicker::make('month')
                            ->label('เดือน (Month)')
                            ->displayFormat('F Y')
                            ->default(now()->startOfMonth())
                            ->required(),
                        Select::make('department')
                            ->label('แผนก (Department)')
                            ->options(fn () => \App\Models\Department::pluck('name', 'name')->toArray())
                            ->placeholder('ทั้งหมด (All)')
                            ->searchable(),
                        Select::make('calibration_type')
                            ->label('ประเภท (Type)')
                            ->options(function () {
                                return \App\Models\ToolType::distinct()
                                    ->whereNotNull('code_type')
                                    ->pluck('name', 'code_type')
                                    ->toArray();
                            })
                            ->placeholder('ทั้งหมด (All)')
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        return redirect()->to(route('monthly-plan.pdf', [
                            'start_date' => \Carbon\Carbon::parse($data['month'])->startOfMonth()->format('Y-m-d'),
                            'end_date' => \Carbon\Carbon::parse($data['month'])->endOfMonth()->format('Y-m-d'),
                            'department' => $data['department'] ?? 'all',
                            'calibration_type' => $data['calibration_type'] ?? 'all',
                            'pdf_type' => 'cal_plan',
                        ]));
                    }),

                // Sync Data Action
                Action::make('sync_data')
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
                            ->placeholder('Select Month')
                            ->displayFormat('F Y')
                            ->default(now()->startOfMonth())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $month = Carbon::parse($data['month']);
                        InternalCalPlanResource::syncDataForMonth($month);

                        Notification::make()
                            ->title('Sync Data Completed')
                            ->body('ดึงข้อมูลสำเร็จสำหรับเดือน ' . $month->format('F Y'))
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
     * Sync data from calibration_logs for internal calibration plan
     * 🟢 ดึงข้อมูลเครื่องมือที่ต้องสอบเทียบในเดือนนั้น + ที่สอบเทียบแล้ว
     */
    public static function syncDataForMonth(Carbon $month): void
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        // 🟢 ดึงเครื่องมือที่มี next_cal_date ตกในเดือนนี้ (จาก latest_calibration_logs) 
        // หรือเครื่องมือที่สอบเทียบในเดือนนี้แล้ว
        $instruments = DB::table('latest_calibration_logs as lcl')
            ->join('instruments', 'lcl.instrument_id', '=', 'instruments.id')
            ->join('tool_types', 'instruments.tool_type_id', '=', 'tool_types.id')
            ->join('departments', 'instruments.department_id', '=', 'departments.id')
            // ->where('lcl.calibration_type', $calibrationType) // REMOVED filter by specific type to sync all
            ->where('lcl.cal_place', 'Internal')
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                // เครื่องมือที่ต้องสอบเดือนนี้ หรือ สอบแล้วเดือนนี้
                $query->whereBetween('lcl.next_cal_date', [$startOfMonth, $endOfMonth])
                      ->orWhereBetween('lcl.cal_date', [$startOfMonth, $endOfMonth]);
            })
            ->whereNotIn('instruments.status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost'])
            ->select([
                'lcl.instrument_id',
                'lcl.id as calibration_log_id',
                'instruments.code_no',
                'tool_types.name as tool_name',
                'tool_types.size as tool_size',
                'instruments.serial_no',
                'lcl.cal_date',
                'lcl.cal_level',
                'lcl.result_status',
                'lcl.remark',
                'lcl.next_cal_date',
                'lcl.calibration_type',
                'departments.name as department',
            ])
            ->get();

        $upsertData = [];
        $now = now();

        foreach ($instruments as $item) {
            // Use instrument_id as key to prevent duplicates (Cardinality Violation Fix)
            $upsertData[$item->instrument_id] = [
                'plan_month' => $startOfMonth->format('Y-m-d'),
                'instrument_id' => $item->instrument_id,
                'calibration_log_id' => $item->calibration_log_id,
                'code_no' => $item->code_no,
                'tool_name' => $item->tool_name,
                'tool_size' => $item->tool_size,
                'serial_no' => $item->serial_no,
                'cal_date' => $item->cal_date,
                'cal_level' => $item->cal_level,
                'result_status' => $item->result_status,
                'remark' => $item->remark,
                'next_cal_date' => $item->next_cal_date,
                'department' => $item->department,
                'calibration_type' => $item->calibration_type,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($upsertData)) {
            // Bulk Upsert (แบ่ง Chunk เพื่อความปลอดภัยกรณีข้อมูลเยอะมาก)
            foreach (array_chunk($upsertData, 1000) as $chunk) {
                InternalCalPlan::upsert(
                    $chunk,
                    ['plan_month', 'instrument_id'], // Unique Key ที่ใช้เช็คซ้ำ
                    [
                        'calibration_log_id',
                        'code_no',
                        'tool_name',
                        'tool_size',
                        'serial_no',
                        'cal_date',
                        'cal_level',
                        'result_status',
                        'remark',
                        'next_cal_date',
                        'department',
                        'calibration_type',
                        'updated_at',
                    ]
                );
            }
        }
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInternalCalPlans::route('/'),
            'create' => Pages\CreateInternalCalPlan::route('/create'),
            'edit' => Pages\EditInternalCalPlan::route('/{record}/edit'),
        ];
    }
}
