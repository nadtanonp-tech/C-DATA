<?php

namespace App\Filament\Widgets;

use App\Models\CalibrationRecord;
use App\Models\Instrument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class CalibrationStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

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
    }

    /**
     * สร้างช่วงวันที่จาก month/year ที่เลือก
     */
    private function getDateRange(): array
    {
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
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
        
        return [$startDate, $endDate];
    }

    /**
     * นับเครื่องมือที่ next_cal_date อยู่ในช่วงที่กำหนด และยังไม่มีการสอบเทียบใหม่กว่า
     */
    private function countDueRecords($startDate, $endDate): int
    {
        $query = DB::table('calibration_logs as cl')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('calibration_logs as newer')
                    ->whereColumn('newer.instrument_id', 'cl.instrument_id')
                    ->whereColumn('newer.cal_date', '>', 'cl.cal_date');
            })
            ->whereBetween('cl.next_cal_date', [$startDate, $endDate]);
        
        if ($this->selectedLevel) {
            $query->where('cl.cal_level', $this->selectedLevel);
        }
        
        return $query->count();
    }

    /**
     * นับเครื่องมือที่เลยกำหนดตามช่วงที่เลือก
     */
    private function countOverdue(): int
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
        
        // กรองตามเดือน/ปี
        if ($month === 0 && $year === 0) {
            // ทั้งหมด - ไม่ต้อง filter
        } elseif ($month === 0) {
            $query->whereRaw('EXTRACT(YEAR FROM cl.next_cal_date) = ?', [$year]);
        } elseif ($year === 0) {
            $query->whereRaw('EXTRACT(MONTH FROM cl.next_cal_date) = ?', [$month]);
        } else {
            $query->whereRaw('EXTRACT(MONTH FROM cl.next_cal_date) = ?', [$month])
                  ->whereRaw('EXTRACT(YEAR FROM cl.next_cal_date) = ?', [$year]);
        }
        
        if ($this->selectedLevel) {
            $query->where('cl.cal_level', $this->selectedLevel);
        }
        
        return $query->count();
    }

    /**
     * นับเครื่องมือที่สอบเทียบแล้วในช่วงที่เลือก
     */
    private function countCalibrated($startDate, $endDate): int
    {
        $query = CalibrationRecord::whereBetween('cal_date', [$startDate, $endDate]);
        
        if ($this->selectedLevel) {
            $query->where('cal_level', $this->selectedLevel);
        }
        
        return $query->count();
    }

    /**
     * สร้างข้อความเดือน/ปี
     */
    private function getDateLabel(): string
    {
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        $monthText = $month === 0 ? '(ทั้งหมด)' : Carbon::createFromDate(2024, $month, 1)->locale('th')->translatedFormat('F');
        $yearText = $year === 0 ? '' : 'พ.ศ. ' . ($year + 543);
        
        if ($month === 0 && $year === 0) {
            return 'ทั้งหมด';
        } elseif ($month === 0) {
            return $yearText;
        } elseif ($year === 0) {
            return $monthText . ' (ทุกปี)';
        }
        
        return $monthText . ' ' . $yearText;
    }

    protected function getStats(): array
    {
        [$startDate, $endDate] = $this->getDateRange();
        $dateLabel = $this->getDateLabel();
        $levelLabel = $this->selectedLevel ? " Level {$this->selectedLevel}" : '';

        // นับจำนวนเครื่องมือที่ครบกำหนด
        $dueCount = $this->countDueRecords($startDate, $endDate);

        // นับจำนวนเครื่องมือที่เลยกำหนด
        $overdueCount = $this->countOverdue();

        // นับจำนวนเครื่องมือที่สอบเทียบแล้ว
        $calibratedCount = $this->countCalibrated($startDate, $endDate);

        // นับจำนวนเครื่องมือทั้งหมด
        $totalInstruments = Instrument::count();

        return [
            Stat::make('ครบกำหนด', $dueCount)
                ->description("เครื่องมือที่ต้องสอบเทียบ {$dateLabel}{$levelLabel}")
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),
            Stat::make('สอบเทียบแล้ว', $calibratedCount)
                ->description("เครื่องมือที่สอบเทียบแล้ว {$dateLabel}{$levelLabel}")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('เลยกำหนด', $overdueCount)
                ->description("เครื่องมือที่เลยกำหนดสอบเทียบ{$levelLabel}")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
            Stat::make('เครื่องมือทั้งหมด', $totalInstruments)
                ->description('จำนวนเครื่องมือในระบบ')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('gray'),
        ];
    }
}
