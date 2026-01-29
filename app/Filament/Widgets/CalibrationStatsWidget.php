<?php

namespace App\Filament\Widgets;

use App\Models\CalibrationRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

// 🔧 Cache TTL constant - 30 minutes
if (!defined('DASHBOARD_CACHE_TTL')) define('DASHBOARD_CACHE_TTL', 1800);

class CalibrationStatsWidget extends BaseWidget
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.widget-spinner');
    }
    public function getColumnSpan(): int | string | array
    {
        return 'full';
    }

    protected static ?int $sort = 0;

    // 🚀 Polling - Auto-refresh every 10 seconds
    protected static ?string $pollingInterval = '10s';
    
    // 🚀 Lazy loading - ทำให้ widget โหลดแบบ async ไม่บล็อค navigation
    protected static bool $isLazy = true;

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
     * นับเครื่องมือที่ next_cal_date อยู่ในช่วงที่กำหนด
     * 🚀 ใช้ View แทน whereNotExists ที่ช้า
     */
    private function countDueRecords($startDate, $endDate): int
    {
        $query = DB::table('latest_calibration_logs')
            ->join('instruments', 'latest_calibration_logs.instrument_id', '=', 'instruments.id') // 🔥 Join instruments
            ->whereBetween('latest_calibration_logs.next_cal_date', [$startDate, $endDate])
            ->whereNotIn('instruments.status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']); // 🔥 Filter Status
        
        if ($this->selectedLevel) {
            $query->where('latest_calibration_logs.cal_level', $this->selectedLevel);
        }
        
        // 🔥 กรองตาม cal_place
        if ($this->selectedCalPlace) {
            $query->where('latest_calibration_logs.cal_place', $this->selectedCalPlace);
        }
        
        return $query->count();
    }

    /**
     * นับเครื่องมือที่เลยกำหนดตามช่วงที่เลือก
     * 🚀 ใช้ View แทน whereNotExists ที่ช้า
     */
    private function countOverdue(): int
    {
        $today = Carbon::today();
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        $query = DB::table('latest_calibration_logs')
            ->join('instruments', 'latest_calibration_logs.instrument_id', '=', 'instruments.id') // 🔥 Join instruments
            ->where('latest_calibration_logs.next_cal_date', '<', $today)
            ->whereNotIn('instruments.status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']); // 🔥 Filter Status
        
        // กรองตามเดือน/ปี
        if ($month === 0 && $year === 0) {
            // ทั้งหมด - ไม่ต้อง filter
        } elseif ($month === 0) {
            $query->whereRaw('EXTRACT(YEAR FROM latest_calibration_logs.next_cal_date) = ?', [$year]);
        } elseif ($year === 0) {
            $query->whereRaw('EXTRACT(MONTH FROM latest_calibration_logs.next_cal_date) = ?', [$month]);
        } else {
            $query->whereRaw('EXTRACT(MONTH FROM latest_calibration_logs.next_cal_date) = ?', [$month])
                  ->whereRaw('EXTRACT(YEAR FROM latest_calibration_logs.next_cal_date) = ?', [$year]);
        }
        
        if ($this->selectedLevel) {
            $query->where('latest_calibration_logs.cal_level', $this->selectedLevel);
        }
        
        // 🔥 กรองตาม cal_place
        if ($this->selectedCalPlace) {
            $query->where('latest_calibration_logs.cal_place', $this->selectedCalPlace);
        }
        
        return $query->count();
    }

    /**
     * นับเครื่องมือที่สอบเทียบแล้วในช่วงที่เลือก
     */
    private function countCalibrated($startDate, $endDate): int
    {
        $query = CalibrationRecord::whereBetween('cal_date', [$startDate, $endDate])
            ->whereHas('instrument', function ($q) { // 🔥 Filter Status
                $q->whereNotIn('status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']);
            });
        
        if ($this->selectedLevel) {
            $query->where('cal_level', $this->selectedLevel);
        }
        
        // 🔥 กรองตาม cal_place
        if ($this->selectedCalPlace) {
            $query->where('cal_place', $this->selectedCalPlace);
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
        $yearText = $year === 0 ? '' : 'ค.ศ. ' . $year;
        
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
        
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = (string)($this->selectedYear ?? (int) Carbon::now()->format('Y')); // Cast to string for cache key consistency
        $level = $this->selectedLevel ?? '';
        $calPlace = $this->selectedCalPlace ?? ''; // 🔥 เพิ่ม cal_place ใน key
        
        // 🚀 ใช้ cache เพื่อไม่ต้อง query นับจำนวนทุกครั้ง (cache 30 นาที)
        // Caching each stat individually to allow for more granular invalidation if needed
        $dueCacheKey = "due_stats_{$month}_{$year}_{$level}_{$calPlace}";
        $calibratedCacheKey = "calibrated_stats_{$month}_{$year}_{$level}_{$calPlace}";
        $overdueCacheKey = "overdue_stats_{$month}_{$year}_{$level}_{$calPlace}";

        $dueCount = Cache::remember($dueCacheKey, DASHBOARD_CACHE_TTL, function () use ($startDate, $endDate) {
            return $this->countDueRecords($startDate, $endDate);
        });

        $calibratedCount = Cache::remember($calibratedCacheKey, DASHBOARD_CACHE_TTL, function () use ($startDate, $endDate) {
            return $this->countCalibrated($startDate, $endDate);
        });

        $overdueCount = Cache::remember($overdueCacheKey, DASHBOARD_CACHE_TTL, function () {
            return $this->countOverdue();
        });

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

            $this->getCalibrationProgress(),
        ];
    }

    /**
     * 🚀 Get Calibration Progress Stat (Simple)
     */
    private function getCalibrationProgress(): Stat
    {
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');

        if ($month !== 0 && $year !== 0) {
            [$startDate, $endDate] = $this->getDateRange();
            
            // Re-using logic for consistency
            $planCount = DB::table('latest_calibration_logs')
                ->join('instruments', 'latest_calibration_logs.instrument_id', '=', 'instruments.id') // 🔥 Join
                ->whereBetween('latest_calibration_logs.next_cal_date', [$startDate, $endDate])
                ->whereNotIn('instruments.status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']); // 🔥 Filter Logic
                
            $calCountQuery = CalibrationRecord::whereBetween('cal_date', [$startDate, $endDate])
                ->whereHas('instrument', function ($q) { // 🔥 Filter Logic
                    $q->whereNotIn('status', ['ยกเลิก', 'สูญหาย', 'Inactive', 'Lost']);
                });

            if ($this->selectedLevel) {
                 $planCount->where('latest_calibration_logs.cal_level', $this->selectedLevel);
                 $calCountQuery->where('cal_level', $this->selectedLevel);
            }
            
            if ($this->selectedCalPlace) {
                $planCount->where('latest_calibration_logs.cal_place', $this->selectedCalPlace);
                $calCountQuery->where('cal_place', $this->selectedCalPlace);
            }
            
            $planCount = $planCount->count();
            $calCount = $calCountQuery->count();

        } else {
             [$startDate, $endDate] = $this->getDateRange();
             $planCount = $this->countDueRecords($startDate, $endDate); // Already filtered
             $calCount = $this->countCalibrated($startDate, $endDate); // Already filtered
        }

        // Avoid division by zero
        $percentage = $planCount > 0 ? round(($calCount / $planCount) * 100, 1) : 0;
        
        $color = 'primary';
        $icon = 'heroicon-m-clock';
        
        if ($percentage >= 100) {
            $color = 'success';
            $icon = 'heroicon-m-check-badge';
        } elseif ($percentage < 50) {
            $color = 'warning'; // Or danger
            $icon = 'heroicon-m-arrow-path';
        }

        return Stat::make('ความคืบหน้าแผน', $percentage . '%')
            ->description("สอบเทียบแล้ว {$calCount} / {$planCount} รายการ")
            ->descriptionIcon($icon)
            ->color($color);
    }
}
