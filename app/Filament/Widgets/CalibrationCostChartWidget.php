<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\CalibrationRecord;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

class CalibrationCostChartWidget extends ChartWidget
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.widget-spinner');
    }

    protected static ?string $heading = 'ค่าใช้จ่ายสอบเทียบ (Calibration Cost)';
    
    protected static ?int $sort = 1;
    
    // 🚀 Polling - Auto-refresh every 10 seconds
    protected static ?string $pollingInterval = '10s';

    // 🚀 Lazy loading
    protected static bool $isLazy = true;
    
    // Chart size
    protected static ?string $maxHeight = '300px';
    
    // 🔥 Filter properties - รับค่าจาก MonthSelectorWidget
    public ?int $selectedYear = null;
    
    // 🔥 Filter ใน Chart เอง
    public ?string $filter = 'comparison'; // default แสดงเปรียบเทียบ
    
    public function mount(): void
    {
        $this->selectedYear = (int) Carbon::now()->format('Y');
    }
    
    /**
     * 🔥 รับ filter-changed event จาก MonthSelectorWidget (เฉพาะ year)
     */
    #[On('filter-changed')]
    public function updateFilters($data): void
    {
        $this->selectedYear = $data['year'] ?? $this->selectedYear;
        // 🔥 Update filter from cal_place
        // If cal_place is null (all), use 'comparison'
        // If cal_place is set, use it (Internal/External)
        $this->filter = $data['cal_place'] ?? 'comparison';
    }

    protected function getData(): array
    {
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        $thaiMonths = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.',
            4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.',
            10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];
        
        $labels = array_values($thaiMonths);
        
        // 🔥 แสดงทั้ง Internal และ External เทียบกัน
        $internalCosts = $this->getMonthlyCosts($year, 'Internal');
        $externalCosts = $this->getMonthlyCosts($year, 'External');
        
        $internalData = [];
        $externalData = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $internalData[] = $internalCosts->get($i)?->total_cost ?? 0;
            $externalData[] = $externalCosts->get($i)?->total_cost ?? 0;
        }
        
        return [
            'datasets' => [
                [
                    'label' => "ภายใน (Internal) - ปี {$year}",
                    'data' => $internalData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',   // info (Blue)
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                    'hidden' => $this->filter === 'External', // 🔥 ซ่อนถ้าเลือก External
                ],
                [
                    'label' => "ภายนอก (External) - ปี {$year}",
                    'data' => $externalData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.8)',  // warning (Amber)
                    'borderColor' => 'rgba(245, 158, 11, 1)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                    'hidden' => $this->filter === 'Internal', // 🔥 ซ่อนถ้าเลือก Internal
                ],
            ],
            'labels' => $labels,
        ];
    }
    
    /**
     * 🔥 Helper: Query ข้อมูลราคาสอบเทียบรายเดือน
     */
    private function getMonthlyCosts(int $year, ?string $calPlace)
    {
        $query = CalibrationRecord::select(
                DB::raw('EXTRACT(MONTH FROM cal_date) as month'),
                DB::raw('SUM(price) as total_cost'),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->whereRaw('EXTRACT(YEAR FROM cal_date) = ?', [$year]);
        
        if (!empty($calPlace)) {
            $query->where('cal_place', $calPlace);
        }
        
        return $query
            ->groupBy(DB::raw('EXTRACT(MONTH FROM cal_date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');
    }

    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'responsive' => true,
            'aspectRatio' => 1,
        ];
    }
    
    /**
     * คำนวณยอดรวมตาม filter
     */
    public function getFilteredTotal(?string $calPlace = null): string
    {
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        $query = CalibrationRecord::whereNotNull('price')
            ->where('price', '>', 0)
            ->whereRaw('EXTRACT(YEAR FROM cal_date) = ?', [$year]);
            
        if (!empty($calPlace)) {
            $query->where('cal_place', $calPlace);
        }
        
        $total = $query->sum('price');
            
        return number_format($total, 2);
    }
    
    protected function getFooter(): ?string
    {
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        $displayMode = $this->filter ?? 'comparison';
        
        // 🔥 โหมดเปรียบเทียบ - แสดงยอดรวมทั้งสอง
        if ($displayMode === 'comparison') {
            $internalTotal = $this->getFilteredTotal('Internal');
            $externalTotal = $this->getFilteredTotal('External');
            $grandTotal = $this->getFilteredTotal(null);
            
            return "💰 ปี {$year} | 🟢ภายใน: ฿{$internalTotal} | 🔵ภายนอก: ฿{$externalTotal} | รวม: ฿{$grandTotal}";
        }
        
        // 🔥 โหมดแท่งเดียว
        $calPlace = ($displayMode === 'total') ? null : $displayMode;
        $total = $this->getFilteredTotal($calPlace);
        
        $calPlaceLabel = match($displayMode) {
            'Internal' => 'ภายใน',
            'External' => 'ภายนอก',
            default => 'รวมทั้งหมด'
        };
        
        return "💰 ยอดรวมปี {$year} ({$calPlaceLabel}): ฿{$total} บาท";
    }
}

