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

    public function mount(): void
    {
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedLevel = 'all';
    }

    public function resetFilters(): void
    {
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedLevel = 'all';
        $this->dispatchFilters();
    }

    public function dispatchFilters(): void
    {
        $this->dispatch('filter-changed', [
            'month' => $this->selectedMonth,
            'year' => $this->selectedYear,
            'level' => $this->selectedLevel === 'all' ? null : $this->selectedLevel,
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
                $thaiYear = $year + 543;
                $label = $year === $currentYear 
                    ? "พ.ศ. {$thaiYear} (ปัจจุบัน)" 
                    : "พ.ศ. {$thaiYear}";
                $options[$year] = $label;
            }

            return $options;
        });
    }
}
