<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\CalibrationRecord;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

class DueTypeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'สัดส่วนเครื่องมือตาม Type Name (Due This Month)';
    
    protected static ?int $sort = 3;
    
    protected static ?string $pollingInterval = '10s';

    
    // Filters
    public ?int $selectedMonth = null;
    public ?int $selectedYear = null;
    public ?string $selectedCalPlace = null;
    
    public ?string $selectedType = null;
    
    public function mount(): void
    {
        $this->selectedMonth = (int) Carbon::now()->format('m');
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->selectedCalPlace = null;
        $this->selectedType = null;
    }
    
    #[On('filter-changed')]
    public function updateFilters($data): void
    {
        $this->selectedMonth = $data['month'] ?? $this->selectedMonth;
        $this->selectedYear = $data['year'] ?? $this->selectedYear;
        $this->selectedCalPlace = $data['cal_place'] ?? null;
        $this->selectedType = $data['type_name'] ?? null;
    }

    /**
     * 🚀 Get Raw Data for both Chart and Custom Legend
     */
    public function getCachedChartData(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "due_type_chart_data_{$this->selectedMonth}_{$this->selectedYear}_{$this->selectedCalPlace}_{$this->selectedType}",
            300, 
            function () {
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
                
                $dueIds = DB::table('latest_calibration_logs')
                    ->whereBetween('next_cal_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->pluck('id')
                    ->toArray();
                    
                if (empty($dueIds)) {
                    return ['labels' => [], 'counts' => [], 'colors' => []];
                }
        
                $query = CalibrationRecord::whereIn('calibration_logs.id', $dueIds)
                    ->join('instruments', 'calibration_logs.instrument_id', '=', 'instruments.id')
                    ->join('tool_types', 'instruments.tool_type_id', '=', 'tool_types.id')
                    ->select('tool_types.name as type_name', DB::raw('count(*) as count'));
                    
                if ($this->selectedCalPlace) {
                     $query->where('calibration_logs.cal_place', $this->selectedCalPlace);
                }
        
                if ($this->selectedType) {
                     $query->where('tool_types.name', $this->selectedType);
                }
                
                $data = $query->groupBy('tool_types.name')
                    ->orderByDesc('count')
                    ->get();
                    
                $labels = $data->pluck('type_name')->toArray();
                $counts = $data->pluck('count')->toArray();
                
                $backgroundColors = array_map(function($index) {
                    $h = ($index * 137.508) % 360;
                    return "hsl({$h}, 70%, 60%)";
                }, array_keys($labels));

                return [
                    'labels' => $labels,
                    'counts' => $counts,
                    'colors' => $backgroundColors,
                ];
            }
        );
    }

    protected function getData(): array
    {
        $data = $this->getCachedChartData();

        return [
            'datasets' => [
                [
                    'label' => 'จำนวนเครื่องมือ',
                    'data' => $data['counts'],
                    'backgroundColor' => $data['colors'],
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
    
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
             'plugins' => [
                'legend' => [
                    'display' => false, // 🚀 Hide Native Legend
                ],
            ],
            'layout' => [
                'padding' => 20,
            ],
            'scales' => [
                'x' => [
                    'display' => false,
                ],
                'y' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
