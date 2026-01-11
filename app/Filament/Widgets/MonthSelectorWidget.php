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

    public ?int $selectedMonth = null;
    public ?int $selectedYear = null;
    public ?string $selectedLevel = null;

    public function mount(): void
    {
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedLevel = null;
    }

    public function resetFilters(): void
    {
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedLevel = null;
        $this->dispatchFilters();
    }

    public function dispatchFilters(): void
    {
        $this->dispatch('filter-changed', [
            'month' => $this->selectedMonth,
            'year' => $this->selectedYear,
            'level' => $this->selectedLevel,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedMonth')
                    ->label('เลือกเดือน')
                    ->options($this->getMonthOptions())
                    ->default((int) Carbon::now()->format('m'))
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatchFilters()),
                Select::make('selectedYear')
                    ->label('เลือกปี')
                    ->options($this->getYearOptions())
                    ->default((int) Carbon::now()->format('Y'))
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatchFilters()),
                Select::make('selectedLevel')
                    ->label('เลือก Level')
                    ->placeholder('ทั้งหมด')
                    ->options([
                        'A' => 'Level A',
                        'B' => 'Level B',
                        'C' => 'Level C',
                    ])
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatchFilters()),
            ])
            ->columns(3);
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
        
        // เพิ่ม (เดือนนี้) ให้เดือนปัจจุบัน
        $months[$currentMonth] .= ' (เดือนนี้)';
        
        return $months;
    }

    /**
     * สร้าง options สำหรับ dropdown เลือกปี
     */
    public function getYearOptions(): array
    {
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
                ? "พ.ศ. {$thaiYear} (ปีนี้)" 
                : "พ.ศ. {$thaiYear}";
            $options[$year] = $label;
        }

        return $options;
    }
}
