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

class OverdueInstrumentsWidget extends BaseWidget

{

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.widget-spinner');
    }
    
    protected static ?string $heading = 'เครื่องมือที่เลยกำหนดสอบเทียบ';
    
    public function getColumnSpan(): int | string | array
    {
        return 'full';
    }
    
    protected static ?int $sort = 5;

    // 🚀 Polling - Auto-refresh every 10 seconds
    protected static ?string $pollingInterval = '10s';

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
     * ดึง record IDs ที่เลยกำหนดและยังไม่ได้สอบเทียบ
     * 🚀 ใช้ View แทน whereNotExists ที่ช้า
     */
    public function getOverdueRecordIds(): array
    {
        $today = Carbon::today();
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        $query = DB::table('latest_calibration_logs')
            ->where('latest_calibration_logs.next_cal_date', '<', $today);
        
        // กรองตามเดือน/ปี ของ next_cal_date (วันที่ครบกำหนด)
        if ($month === 0 && $year === 0) {
            // ทุกเดือน ทุกปี - ไม่ต้อง filter
        } elseif ($month === 0) {
            // ทุกเดือน ปีที่เลือก
            $query->whereRaw('EXTRACT(YEAR FROM latest_calibration_logs.next_cal_date) = ?', [$year]);
        } elseif ($year === 0) {
            // เดือนที่เลือก ทุกปี
            $query->whereRaw('EXTRACT(MONTH FROM latest_calibration_logs.next_cal_date) = ?', [$month]);
        } else {
            // เดือนและปีที่เลือก
            $query->whereRaw('EXTRACT(MONTH FROM latest_calibration_logs.next_cal_date) = ?', [$month])
                  ->whereRaw('EXTRACT(YEAR FROM latest_calibration_logs.next_cal_date) = ?', [$year]);
        }
        
        // 🔥 Filter: ไม่รวมเครื่องมือที่ ยกเลิก หรือ สูญหาย
        $query->join('instruments', 'latest_calibration_logs.instrument_id', '=', 'instruments.id')
              ->whereNotIn('instruments.status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']);
        
        return $query->pluck('latest_calibration_logs.id')->toArray();
    }

    public function table(Table $table): Table
    {
        // 🚀 ใช้ closure เพื่อให้ query รันเฉพาะตอนตารางแสดงจริงๆ
        $widget = $this;

        return $table
            ->heading(false)
            ->query(CalibrationRecord::query()->with('instrument'))
            ->modifyQueryUsing(function (Builder $query) use ($widget) {
                $overdueIds = $widget->getOverdueRecordIds();
                
                if (empty($overdueIds)) {
                    $overdueIds = [0];
                }
                
                $query->whereIn('id', $overdueIds)
                      ->orderBy('next_cal_date', 'asc');
                
                if ($widget->selectedLevel) {
                    $query->where('cal_level', $widget->selectedLevel);
                }
                
                // 🔥 กรองตาม cal_place
                if ($widget->selectedCalPlace) {
                    $query->where('cal_place', $widget->selectedCalPlace);
                }
                
                // 🔥 Filter: ไม่รวมเครื่องมือที่ ยกเลิก หรือ สูญหาย
                $query->whereHas('instrument', function ($q) {
                    $q->whereNotIn('status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']);
                });
                
                return $query;
            })
            ->deferLoading() // 🚀 ไม่ query จนกว่าตารางจะแสดง
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25,])
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
                    ->color('danger'),
                Tables\Columns\TextColumn::make('overdue_days')
                    ->label('เลยกำหนด (วัน)')
                    ->getStateUsing(fn ($record) => (int) Carbon::parse($record->next_cal_date)->diffInDays(now()))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('next_cal_date', $direction === 'asc' ? 'desc' : 'asc');
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 365 ? 'danger' : ($state > 90 ? 'warning' : 'gray')),
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
                Tables\Columns\TextColumn::make('cal_place')
                    ->label('สถานที่')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Internal' => 'info',
                        'External' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('overdue_year')
                    ->label('ปีที่เลยกำหนด')
                    ->options($this->getYearOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'])) {
                            $year = $data['value'];
                            $startOfYear = Carbon::createFromFormat('Y', $year)->startOfYear();
                            $endOfYear = Carbon::createFromFormat('Y', $year)->endOfYear();
                            
                            return $query->whereBetween('next_cal_date', [$startOfYear, $endOfYear]);
                        }
                        return $query;
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
            ->emptyStateHeading('ไม่มีเครื่องมือที่เลยกำหนด')
            ->emptyStateDescription('เครื่องมือทั้งหมดได้รับการสอบเทียบเรียบร้อยแล้ว')
            ->emptyStateIcon('heroicon-o-check-circle');
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
        $cacheKey = "overdue_count_{$month}_{$year}_{$level}_{$calPlace}";
        $count = Cache::remember($cacheKey, DASHBOARD_CACHE_TTL, function () {
            $overdueIds = $this->getOverdueRecordIds();
            $query = CalibrationRecord::whereIn('id', $overdueIds);
            if ($this->selectedLevel) {
                $query->where('cal_level', $this->selectedLevel);
            }
            if ($this->selectedCalPlace) {
                $query->where('cal_place', $this->selectedCalPlace);
            }
            
            // 🔥 Filter: ไม่รวมเครื่องมือที่ ยกเลิก หรือ สูญหาย
            $ignoredStatuses = ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost'];
            $query->whereHas('instrument', function ($q) use ($ignoredStatuses) {
                $q->whereNotIn('status', $ignoredStatuses);
            });
            
            return $query->count();
        });
        
        $levelText = $this->selectedLevel ? " - Level {$this->selectedLevel}" : '';
        
        // สร้างข้อความเดือน/ปี
        $monthText = $month === 0 ? '(ทุกเดือน)' : Carbon::createFromDate(2024, $month, 1)->locale('th')->translatedFormat('F');
        $yearText = $year === 0 ? '(ทุกปี)' : 'ค.ศ. ' . $year;
        
        return "เครื่องมือที่เลยกำหนดสอบเทียบ - {$monthText} {$yearText}{$levelText}";
    }

    /**
     * สร้าง options สำหรับ dropdown เลือกปี
     */
    private function getYearOptions(): array
    {
        $options = [];
        $now = Carbon::now();
        
        // 5 ปีก่อนหน้า
        for ($i = 5; $i >= 0; $i--) {
            $year = $now->copy()->subYears($i)->format('Y');
            $options[$year] = "ค.ศ. {$year}";
        }

        return $options;
    }
    public function getPollingInterval(): ?string
    {
        return static::$pollingInterval;
    }
}
