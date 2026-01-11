<?php

namespace App\Filament\Widgets;

use App\Models\CalibrationRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class DueThisMonthWidget extends BaseWidget
{
    protected static ?string $heading = 'เครื่องมือครบกำหนดสอบเทียบ';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2;

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
     * ดึง record IDs ที่ครบกำหนดและยังไม่ได้สอบเทียบ (ใช้ SQL เดียว)
     */
    private function getDueRecordIds($startDate, $endDate): array
    {
        return DB::table('calibration_logs as cl')
            ->leftJoin('calibration_logs as newer', function ($join) {
                $join->on('newer.instrument_id', '=', 'cl.instrument_id')
                     ->whereColumn('newer.cal_date', '>', 'cl.cal_date');
            })
            ->whereNull('newer.id')
            ->whereBetween('cl.next_cal_date', [$startDate, $endDate])
            ->pluck('cl.id')
            ->toArray();
    }

    public function table(Table $table): Table
    {
        // สร้างวันที่จากเดือนและปีที่เลือก
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        // กรณีต่างๆ ของ month/year
        $currentYear = (int) Carbon::now()->format('Y');
        $minYear = $currentYear - 10;
        $maxYear = $currentYear + 5;
        
        if ($month === 0 && $year === 0) {
            // ทุกเดือน ทุกปี
            $startDate = Carbon::createFromDate($minYear, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($maxYear, 12, 31)->endOfYear();
        } elseif ($month === 0) {
            // ทุกเดือน ปีที่เลือก
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        } elseif ($year === 0) {
            // เดือนที่เลือก ทุกปี
            $startDate = Carbon::createFromDate($minYear, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($maxYear, $month, 1)->endOfMonth();
        } else {
            // เดือนและปีที่เลือก
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        }
        
        $dueIds = $this->getDueRecordIds($startDate, $endDate);
        
        if (empty($dueIds)) {
            $dueIds = [0];
        }

        $query = CalibrationRecord::query()
            ->with('instrument')
            ->whereIn('id', $dueIds);
        
        // Filter by Level if selected
        if ($this->selectedLevel) {
            $query->where('cal_level', $this->selectedLevel);
        }

        return $table
            ->heading(false)
            ->query($query)
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->columns([
                Tables\Columns\TextColumn::make('instrument.code_no')
                    ->label('รหัสเครื่องมือ')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('instrument.name')
                    ->label('ชื่อเครื่องมือ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('instrument.serial_no')
                    ->label('Serial No.')
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
                    ->color(fn ($record) => Carbon::parse($record->next_cal_date)->isPast() ? 'danger' : (Carbon::parse($record->next_cal_date)->diffInDays(now()) <= 7 ? 'warning' : 'success')),
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
            ->defaultSort('next_cal_date', 'asc')
            ->emptyStateHeading('ไม่มีเครื่องมือครบกำหนดในช่วงที่เลือก')
            ->emptyStateDescription('เครื่องมือทั้งหมดยังไม่ถึงกำหนดสอบเทียบ หรือสอบเทียบเสร็จแล้ว')
            ->emptyStateIcon('heroicon-o-calendar');
    }

    public function getTableHeading(): string
    {
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        // กรณีต่างๆ ของ month/year
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
        
        $dueIds = $this->getDueRecordIds($startDate, $endDate);
        $query = CalibrationRecord::whereIn('id', $dueIds);
        if ($this->selectedLevel) {
            $query->where('cal_level', $this->selectedLevel);
        }
        $count = $query->count();
        
        $levelText = $this->selectedLevel ? " - Level {$this->selectedLevel}" : '';
        
        // สร้างข้อความเดือน/ปี
        $monthText = $month === 0 ? '(ทั้งหมด)' : Carbon::createFromDate(2024, $month, 1)->locale('th')->translatedFormat('F');
        $yearText = $year === 0 ? '(ทั้งหมด)' : 'พ.ศ. ' . ($year + 543);
        
        return "เครื่องมือครบกำหนดสอบเทียบ - {$monthText} {$yearText}{$levelText} ({$count} รายการ)";
    }
}
