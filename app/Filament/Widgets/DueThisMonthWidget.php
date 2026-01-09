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

    public ?string $selectedMonth = null;
    public ?string $selectedYear = null;
    public bool $viewByYear = false;

    public function mount(): void
    {
        $this->selectedMonth = Carbon::now()->format('Y-m');
        $this->selectedYear = Carbon::now()->format('Y');
    }

    #[On('month-changed')]
    public function updateMonth($month): void
    {
        $this->selectedMonth = $month;
        $this->viewByYear = false;
        $this->resetTable();
    }

    #[On('year-changed')]
    public function updateYear($year): void
    {
        $this->selectedYear = $year;
        $this->viewByYear = true;
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
        if ($this->viewByYear) {
            $selectedDate = Carbon::createFromFormat('Y', $this->selectedYear);
            $startDate = $selectedDate->copy()->startOfYear();
            $endDate = $selectedDate->copy()->endOfYear();
        } else {
            $selectedDate = $this->selectedMonth 
                ? Carbon::createFromFormat('Y-m', $this->selectedMonth) 
                : Carbon::now();
            $startDate = $selectedDate->copy()->startOfMonth();
            $endDate = $selectedDate->copy()->endOfMonth();
        }
        
        $dueIds = $this->getDueRecordIds($startDate, $endDate);
        
        if (empty($dueIds)) {
            $dueIds = [0];
        }

        return $table
            ->heading(false)
            ->query(
                CalibrationRecord::query()
                    ->with('instrument')
                    ->whereIn('id', $dueIds)
            )
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
        if ($this->viewByYear) {
            $selectedDate = Carbon::createFromFormat('Y', $this->selectedYear);
            $startDate = $selectedDate->copy()->startOfYear();
            $endDate = $selectedDate->copy()->endOfYear();
            $year = $this->selectedYear;
            $thaiYear = (int)$year + 543;
            $count = count($this->getDueRecordIds($startDate, $endDate));
            return "เครื่องมือครบกำหนดสอบเทียบ - พ.ศ. {$thaiYear} ({$count} รายการ)";
        }
        
        $selectedDate = $this->selectedMonth 
            ? Carbon::createFromFormat('Y-m', $this->selectedMonth) 
            : Carbon::now();
        
        $startDate = $selectedDate->copy()->startOfMonth();
        $endDate = $selectedDate->copy()->endOfMonth();
        $count = count($this->getDueRecordIds($startDate, $endDate));
        
        return 'เครื่องมือครบกำหนดสอบเทียบ - ' . $selectedDate->locale('th')->translatedFormat('F Y') . " ({$count} รายการ)";
    }
}
