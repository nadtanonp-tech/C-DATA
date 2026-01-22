<?php

namespace App\Filament\Widgets;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Widgets\Widget;
use Carbon\Carbon;

class MonthSelectorWidget extends Widget implements HasForms
{
    use InteractsWithForms;
    
    protected static string $view = 'filament.widgets.month-selector-widget';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1;
    
    // 🚀 Lazy loading - ทำให้ widget โหลดแบบ async ไม่บล็อก navigation
    protected static bool $isLazy = true;

    public ?int $selectedMonth = null;
    public ?int $selectedYear = null;
    public ?string $selectedLevel = null;
    public ?string $selectedCalPlace = null; // 🔥 เพิ่ม filter สถานที่สอบเทียบ

    public function mount(): void
    {
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedLevel = 'all';
        $this->selectedCalPlace = 'all'; // 🔥 Default = ทั้งหมด
    }

    public function resetFilters(): void
    {
        // Reset filters to default values
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedLevel = 'all';
        $this->selectedCalPlace = 'all'; // 🔥 Reset cal_place
        
        // 🔄 Clear all dashboard-related cache
        $this->clearDashboardCache();
        
        // Dispatch filter-changed event to refresh all widgets
        $this->dispatchFilters();
        
        // Show success notification
        \Filament\Notifications\Notification::make()
            ->title('รีเซ็ตสำเร็จ')
            ->body('รีเซ็ตตัวกรองและรีเฟรชข้อมูลใหม่เรียบร้อยแล้ว')
            ->success()
            ->duration(3000)
            ->send();
    }

    /**
     * 🔄 Clear all dashboard cache
     */
    private function clearDashboardCache(): void
    {
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        $level = $this->selectedLevel === 'all' ? '' : ($this->selectedLevel ?? '');
        
        // Clear specific cache keys
        $cacheKeys = [
            "stats_counts_{$month}_{$year}_{$level}",
            "due_count_{$month}_{$year}_{$level}",
            "calibrated_count_{$month}_{$year}_{$level}",
            "overdue_count_{$month}_{$year}_{$level}",
            "year_options",
            // Also clear for empty level
            "stats_counts_{$month}_{$year}_",
            "due_count_{$month}_{$year}_",
            "calibrated_count_{$month}_{$year}_",
            "overdue_count_{$month}_{$year}_",
        ];
        
        foreach ($cacheKeys as $key) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }
    }

    public function dispatchFilters(): void
    {
        $this->dispatch('filter-changed', [
            'month' => $this->selectedMonth,
            'year' => $this->selectedYear,
            'level' => $this->selectedLevel === 'all' ? null : $this->selectedLevel,
            'cal_place' => $this->selectedCalPlace === 'all' ? null : $this->selectedCalPlace, // 🔥 เพิ่ม cal_place
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedMonth')
                    ->label('เลือกเดือน')
                    ->native(false)
                    ->options($this->getMonthOptions())
                    ->default((int) Carbon::now()->format('m')),
                Select::make('selectedYear')
                    ->label('เลือกปี')
                    ->native(false)
                    ->options($this->getYearOptions())
                    ->default((int) Carbon::now()->format('Y')),
                Select::make('selectedLevel')
                    ->label('เลือก Level')
                    ->native(false)
                    ->options([
                        'all' => 'ทั้งหมด',
                        'A' => 'Level A',
                        'B' => 'Level B',
                        'C' => 'Level C',
                    ])
                    ->default('all'),
                Select::make('selectedCalPlace')
                    ->label('สถานที่สอบเทียบ')
                    ->native(false)
                    ->options([
                        'all' => 'ทั้งหมด',
                        'Internal' => 'ภายใน (Internal)',
                        'External' => 'ภายนอก (External)',
                    ])
                    ->default('all'),
            ])
            ->columns(5);
    }

    /**
     * สร้าง options สำหรับ dropdown เลือกเดือน (1-12)
     */
    public function getMonthOptions(): array
    {
        $currentMonth = (int) Carbon::now()->format('m');
        
        $months = [
            0 => 'ทั้งหมด',
            1 => 'มกราคม',
            2 => 'กุมภาพันธ์',
            3 => 'มีนาคม',
            4 => 'เมษายน',
            5 => 'พฤษภาคม',
            6 => 'มิถุนายน',
            7 => 'กรกฎาคม',
            8 => 'สิงหาคม',
            9 => 'กันยายน',
            10 => 'ตุลาคม',
            11 => 'พฤศจิกายน',
            12 => 'ธันวาคม',
        ];
        
        // เพิ่ม (ปัจจุบัน) ให้เดือนปัจจุบัน
        $months[$currentMonth] .= ' (ปัจจุบัน)';
        
        return $months;
    }

    /**
     * สร้าง options สำหรับ dropdown เลือกปี
     * 🚀 ใช้ cache เพื่อไม่ต้อง query ทุกครั้ง
     */
    public function getYearOptions(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('year_options', 3600, function () {
            $options = [];
            $currentYear = (int) Carbon::now()->format('Y');
            
            // ตัวเลือก "ทั้งหมด"
            $options[0] = 'ทั้งหมด';
            
            // ดึงปีจากฐานข้อมูล (cal_date และ next_cal_date)
            $yearsFromCalDate = \Illuminate\Support\Facades\DB::table('calibration_logs')
                ->selectRaw('DISTINCT EXTRACT(YEAR FROM cal_date) as year')
                ->whereNotNull('cal_date')
                ->pluck('year')
                ->toArray();
                
            $yearsFromNextCalDate = \Illuminate\Support\Facades\DB::table('calibration_logs')
                ->selectRaw('DISTINCT EXTRACT(YEAR FROM next_cal_date) as year')
                ->whereNotNull('next_cal_date')
                ->pluck('year')
                ->toArray();
            
            // รวมปีทั้งหมดและเรียงลำดับ
            $allYears = array_unique(array_merge($yearsFromCalDate, $yearsFromNextCalDate));
            sort($allYears);
            
            foreach ($allYears as $year) {
                $year = (int) $year;
                $label = $year === $currentYear 
                    ? "ค.ศ. {$year} (ปัจจุบัน)" 
                    : "ค.ศ. {$year}";
                $options[$year] = $label;
            }

            return $options;
        });
    }
}
