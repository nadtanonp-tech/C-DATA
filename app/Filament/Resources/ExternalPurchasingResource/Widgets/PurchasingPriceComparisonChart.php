<?php

namespace App\Filament\Resources\ExternalPurchasingResource\Widgets;

use App\Models\PurchasingRecord;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchasingPriceComparisonChart extends ChartWidget
{
    use \Filament\Widgets\Concerns\InteractsWithPageTable;

    protected static ?string $heading = 'Price Comparison (Estimated vs Actual)';
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $pollingInterval = null;
    protected $listeners = ['updateChartData' => '$refresh'];

    protected function getTablePage(): string
    {
        return \App\Filament\Resources\ExternalPurchasingResource\Pages\ListExternalPurchasings::class;
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.widget-spinner');
    }

    protected function getFilters(): ?array
    {
        return [
            'instrument' => 'By Instrument',
            'month'      => 'By Month',
            'vendor'     => 'By Vendor',
        ];
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter ?? 'instrument';

        // ดึง query ที่มี filter แล้วจากตาราง
        $baseQuery = $this->getPageTableQuery();
        
        // ดึง IDs ของ records ที่ผ่าน filter
        $filteredIds = $baseQuery->pluck('id')->toArray();
        
        // ถ้าไม่มีข้อมูลหลัง filter
        if (empty($filteredIds)) {
            return [
                'datasets' => [
                    [
                        'label' => 'Estimated Price (ราคาเสนอ)',
                        'data' => [],
                        'backgroundColor' => '#f59e0b',
                        'borderColor' => '#d97706',
                    ],
                    [
                        'label' => 'Actual Price (ราคาจริง)',
                        'data' => [],
                        'backgroundColor' => '#3b82f6',
                        'borderColor' => '#2563eb',
                    ],
                ],
                'labels' => [],
            ];
        }
        
        // สร้าง query ใหม่จาก filtered IDs
        $query = PurchasingRecord::whereIn('id', $filteredIds);
        
        // Filter data based on selection
        if ($activeFilter === 'month') {
            $data = $query
                ->select(
                    DB::raw("TO_CHAR(pr_date, 'YYYY-MM') as period"),
                    DB::raw("SUM(estimated_price) as estimated"),
                    DB::raw("SUM(net_price) as actual")
                )
                ->groupBy(DB::raw("TO_CHAR(pr_date, 'YYYY-MM')"))
                ->orderBy(DB::raw("TO_CHAR(pr_date, 'YYYY-MM')"), 'asc')
                ->get();
            
            $labels = $data->map(function($item) {
                try {
                    return Carbon::createFromFormat('Y-m', $item->period)->format('M Y');
                } catch (\Exception $e) {
                    return $item->period;
                }
            })->toArray();

        } elseif ($activeFilter === 'vendor') {
            $data = $query
                ->select(
                    'vendor_name',
                    DB::raw("SUM(estimated_price) as estimated"),
                    DB::raw("SUM(net_price) as actual")
                )
                ->whereNotNull('vendor_name')
                ->groupBy('vendor_name')
                ->orderByRaw("SUM(net_price) DESC")
                ->limit(15)
                ->get();
            
            $labels = $data->pluck('vendor_name')->toArray();

        } else { // instrument (default)
            $data = $query
                ->select(
                    'instrument_id',
                    DB::raw("SUM(estimated_price) as estimated"),
                    DB::raw("SUM(net_price) as actual")
                )
                ->groupBy('instrument_id')
                ->orderByRaw("SUM(net_price) DESC")
                ->limit(15)
                ->get();
            
            $data->load('instrument');
                
            $labels = $data->map(fn($item) => $item->instrument?->code_no ?? 'Unknown')->toArray();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Estimated Price (ราคาเสนอ)',
                    'data' => $data->pluck('estimated')->map(fn($val) => (float)($val ?? 0))->toArray(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                ],
                [
                    'label' => 'Actual Price (ราคาจริง)',
                    'data' => $data->pluck('actual')->map(fn($val) => (float)($val ?? 0))->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#2563eb',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'aspectRatio' => 1, // ปรับ aspect ratio
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
    
    // เพิ่ม method นี้เพื่อกำหนดความสูงด้วย inline style
    public function getContentHeight(): ?int
    {
        return 500; // ความสูงเป็น pixels
    }
}