<?php

namespace App\Filament\Widgets;

use App\Models\CalibrationRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Illuminate\Support\Str;

// 🔧 Cache TTL constant - 30 minutes
if (!defined('DASHBOARD_CACHE_TTL')) define('DASHBOARD_CACHE_TTL', 1800);

class DueThisMonthWidget extends BaseWidget
{
    protected static ?string $heading = 'เครื่องมือครบกำหนดสอบเทียบ';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2;

    // 🚀 Lazy loading - ทำให้ widget โหลดแบบ async ไม่บล็อก navigation
    protected static bool $isLazy = true;

    protected static string $view = 'filament.widgets.collapsible-table-widget';

    public ?int $selectedMonth = null;
    public ?int $selectedYear = null;
    public ?string $selectedLevel = null;
    public ?string $selectedCalPlace = null; // 🔥 filter สถานที่สอบเทียบ

    public function mount(): void
    {
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedLevel = null;
        $this->selectedCalPlace = null;
    }

    #[On('filter-changed')]
    public function updateFilters($data): void
    {
        $this->selectedMonth = $data['month'] ?? $this->selectedMonth;
        $this->selectedYear = $data['year'] ?? $this->selectedYear;
        $this->selectedLevel = $data['level'] ?: null;
        $this->selectedCalPlace = $data['cal_place'] ?? null; // 🔥 รับ cal_place
        $this->resetTable();
    }

    /**
     * ดึง record IDs ที่ครบกำหนดและยังไม่ได้สอบเทียบ
     * 🚀 ใช้ View แทน whereNotExists ที่ช้า
     */
    public function getDueRecordIds($startDate, $endDate): array
    {
        return DB::table('latest_calibration_logs')
            ->whereBetween('next_cal_date', [$startDate, $endDate])
            ->pluck('id')
            ->toArray();
    }

    public function table(Table $table): Table
    {
        // 🚀 ใช้ closure เพื่อให้ query รันเฉพาะตอนตารางแสดงจริงๆ
        $widget = $this;
        
        return $table
            ->heading(false)
            ->query(CalibrationRecord::query()->with('instrument'))
            ->modifyQueryUsing(function (Builder $query) use ($widget) {
                // query นี้จะรันเมื่อ table render เท่านั้น (ไม่รันตอน page load)
                $month = $widget->selectedMonth ?? (int) Carbon::now()->format('m');
                $year = $widget->selectedYear ?? (int) Carbon::now()->format('Y');
                
                $currentYear = (int) Carbon::now()->format('Y');
                $minYear = $currentYear - 10;
                $maxYear = $currentYear + 5;
                
                if ($month === 0 && $year === 0) {
                    $startDate = Carbon::createFromDate($minYear, 1, 1)->startOfYear();
                    $endDate = Carbon::createFromDate($maxYear, 12, 31)->endOfYear();
                } elseif ($month === 0) {
                    $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                    $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
                } elseif ($year === 0) {
                    $startDate = Carbon::createFromDate($minYear, $month, 1)->startOfMonth();
                    $endDate = Carbon::createFromDate($maxYear, $month, 1)->endOfMonth();
                } else {
                    $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                    $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
                }
                
                $dueIds = $widget->getDueRecordIds($startDate, $endDate);
                
                if (empty($dueIds)) {
                    $dueIds = [0];
                }
                
                $query->whereIn('id', $dueIds);
                
                if ($widget->selectedLevel) {
                    $query->where('cal_level', $widget->selectedLevel);
                }
                
                // 🔥 กรองตาม cal_place
                if ($widget->selectedCalPlace) {
                    $query->where('cal_place', $widget->selectedCalPlace);
                }
                
                return $query;
            })
            ->deferLoading() // 🚀 ไม่ query จนกว่าตารางจะแสดง
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->columns([
                Tables\Columns\TextColumn::make('instrument.code_no')
                    ->label('ID Code Instrument')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('instrument.name')
                    ->label('ID Code Type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('instrument.toolType.name')
                    ->label('Type Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cal_date')
                    ->label('วันที่สอบเทียบล่าสุด')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_cal_date')
                    ->label('วันครบกำหนด')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => Carbon::parse($record->next_cal_date)->isPast() ? 'danger' : (Carbon::parse($record->next_cal_date)->diffInDays(now()) <= 7 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('result_status')
                    ->label('ผลการสอบเทียบ')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Pass' => 'success',
                        'Reject' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('cal_level')
                    ->label('Level')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'A' => 'success',
                        'B' => 'warning',
                        'C' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('calibrate')
                    ->label('ไปสอบเทียบ')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('success')
                    ->url(fn ($record) => $this->getCalibrationUrl($record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('next_cal_date', 'asc')
            ->emptyStateHeading('ไม่มีเครื่องมือครบกำหนดในช่วงที่เลือก')
            ->emptyStateDescription('เครื่องมือทั้งหมดยังไม่ถึงกำหนดสอบเทียบ หรือสอบเทียบเสร็จแล้ว')
            ->emptyStateIcon('heroicon-o-calendar');
    }

    /**
     * 🔗 Get the correct calibration URL based on calibration_type from last calibration record
     */
    private function getCalibrationUrl($record): string
    {
        $instrument = $record->instrument;
        $instrumentId = $record->instrument_id;
        $calibrationType = $record->calibration_type ?? 'KGauge';
        
        // 1. ถ้าเครื่องมือถูก set ว่าเป็น External -> ไป ExternalCalResultResource
        // หรือถ้า record ล่าสุดระบุว่าเป็น External (เผื่อเปลี่ยนไปมา)
        $calPlace = $instrument->cal_place ?? 'Internal';
        if ($calPlace === 'External') {
             return route('filament.admin.calibration-report.resources.external-cal-results.create', [
                'instrument_id' => $instrumentId
            ]);
        }

        $gaugeTypes = [
            'KGauge', 'SnapGauge', 'PlugGauge', 
            'ThreadPlugGauge', 'SerrationPlugGauge', 
            'ThreadRingGauge', 'SerrationRingGauge', 
            'ThreadPlugGaugeFitWear'
        ];

        // 2. ถ้าเป็น Gauge Type -> ไป GaugeCalibrationResource
        if (in_array($calibrationType, $gaugeTypes)) {
            return route('filament.admin.calibration-report.resources.gauge-calibration.create', [
                'type' => $calibrationType,
                'instrument_id' => $instrumentId
            ]);
        }
        
        // 3. ถ้าเป็น Instrument Type อื่นๆ -> ไป CalibrationRecordResource (Instrument Calibration)
        return route('filament.admin.calibration-report.resources.instrument-calibration.create', [
            'type' => Str::snake($calibrationType),
            'instrument_id' => $instrumentId
        ]);
    }

    public function getTableHeading(): string
    {
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        $level = $this->selectedLevel ?? '';
        $calPlace = $this->selectedCalPlace ?? ''; // 🔥 เพิ่ม cal_place
        
        // 🚀 ใช้ cache เพื่อไม่ต้อง query นับจำนวนทุกครั้ง (cache 30 นาที)
        $cacheKey = "due_count_{$month}_{$year}_{$level}_{$calPlace}";
        $count = Cache::remember($cacheKey, DASHBOARD_CACHE_TTL, function () use ($month, $year) {
            $currentYear = (int) Carbon::now()->format('Y');
            $minYear = $currentYear - 10;
            $maxYear = $currentYear + 5;
            
            if ($month === 0 && $year === 0) {
                $startDate = Carbon::createFromDate($minYear, 1, 1)->startOfYear();
                $endDate = Carbon::createFromDate($maxYear, 12, 31)->endOfYear();
            } elseif ($month === 0) {
                $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
            } elseif ($year === 0) {
                $startDate = Carbon::createFromDate($minYear, $month, 1)->startOfMonth();
                $endDate = Carbon::createFromDate($maxYear, $month, 1)->endOfMonth();
            } else {
                $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            }
            
            $dueIds = $this->getDueRecordIds($startDate, $endDate);
            $query = CalibrationRecord::whereIn('id', $dueIds);
            if ($this->selectedLevel) {
                $query->where('cal_level', $this->selectedLevel);
            }
            if ($this->selectedCalPlace) {
                $query->where('cal_place', $this->selectedCalPlace);
            }
            return $query->count();
        });
        
        $levelText = $this->selectedLevel ? " - Level {$this->selectedLevel}" : '';
        
        // สร้างข้อความเดือน/ปี
        $monthText = $month === 0 ? '(ทุกเดือน)' : Carbon::createFromDate(2024, $month, 1)->locale('th')->translatedFormat('F');
        $yearText = $year === 0 ? '(ทุกปี)' : 'ค.ศ. ' . $year;
        
        return "เครื่องมือครบกำหนดสอบเทียบ - {$monthText} {$yearText}{$levelText} ({$count} รายการ)";
    }
}
