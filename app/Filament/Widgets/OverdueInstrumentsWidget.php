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

class OverdueInstrumentsWidget extends BaseWidget
{
    protected static ?string $heading = 'เครื่องมือที่เลยกำหนดสอบเทียบ';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 4;

    // 🚀 Lazy loading - ทำให้ widget โหลดแบบ async ไม่บล็อก navigation
    protected static bool $isLazy = true;

    protected static string $view = 'filament.widgets.collapsible-table-widget';

    public ?int $selectedMonth = null;
    public ?int $selectedYear = null;
    public ?string $selectedLevel = null;

    public function mount(): void
    {
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedLevel = null;
    }

    #[On('filter-changed')]
    public function updateFilters($data): void
    {
        $this->selectedMonth = $data['month'] ?? $this->selectedMonth;
        $this->selectedYear = $data['year'] ?? $this->selectedYear;
        $this->selectedLevel = $data['level'] ?: null;
        $this->resetTable();
    }

    /**
     * ดึง record IDs ที่เลยกำหนดและยังไม่ได้สอบเทียบ
     */
    public function getOverdueRecordIds(): array
    {
        $today = Carbon::today();
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        $query = DB::table('calibration_logs as cl')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('calibration_logs as newer')
                    ->whereColumn('newer.instrument_id', 'cl.instrument_id')
                    ->whereColumn('newer.cal_date', '>', 'cl.cal_date');
            })
            ->where('cl.next_cal_date', '<', $today);
        
        // กรองตามเดือน/ปี ของ next_cal_date (วันที่ครบกำหนด)
        if ($month === 0 && $year === 0) {
            // ทุกเดือน ทุกปี - ไม่ต้อง filter
        } elseif ($month === 0) {
            // ทุกเดือน ปีที่เลือก
            $query->whereRaw('EXTRACT(YEAR FROM cl.next_cal_date) = ?', [$year]);
        } elseif ($year === 0) {
            // เดือนที่เลือก ทุกปี
            $query->whereRaw('EXTRACT(MONTH FROM cl.next_cal_date) = ?', [$month]);
        } else {
            // เดือนและปีที่เลือก
            $query->whereRaw('EXTRACT(MONTH FROM cl.next_cal_date) = ?', [$month])
                  ->whereRaw('EXTRACT(YEAR FROM cl.next_cal_date) = ?', [$year]);
        }
        
        return $query->pluck('cl.id')->toArray();
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
            ->defaultSort('next_cal_date', 'asc')
            ->emptyStateHeading('ไม่มีเครื่องมือที่เลยกำหนด')
            ->emptyStateDescription('เครื่องมือทั้งหมดได้รับการสอบเทียบเรียบร้อยแล้ว')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public function getTableHeading(): string
    {
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        $level = $this->selectedLevel ?? '';
        
        // 🚀 ใช้ cache เพื่อไม่ต้อง query นับจำนวนทุกครั้ง (cache 5 นาที)
        $cacheKey = "overdue_count_{$month}_{$year}_{$level}";
        $count = Cache::remember($cacheKey, 300, function () {
            $overdueIds = $this->getOverdueRecordIds();
            $query = CalibrationRecord::whereIn('id', $overdueIds);
            if ($this->selectedLevel) {
                $query->where('cal_level', $this->selectedLevel);
            }
            return $query->count();
        });
        
        $levelText = $this->selectedLevel ? " - Level {$this->selectedLevel}" : '';
        
        // สร้างข้อความเดือน/ปี
        $monthText = $month === 0 ? '(ทั้งหมด)' : Carbon::createFromDate(2024, $month, 1)->locale('th')->translatedFormat('F');
        $yearText = $year === 0 ? '(ทั้งหมด)' : 'พ.ศ. ' . ($year + 543);
        
        return "เครื่องมือที่เลยกำหนดสอบเทียบ - {$monthText} {$yearText}{$levelText} ({$count} รายการ)";
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
            $thaiYear = (int)$year + 543;
            $options[$year] = "พ.ศ. {$thaiYear} ({$year})";
        }

        return $options;
    }
}
