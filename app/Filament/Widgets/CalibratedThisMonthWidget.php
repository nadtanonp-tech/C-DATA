<?php

namespace App\Filament\Widgets;

use App\Models\CalibrationRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Livewire\Attributes\On;

class CalibratedThisMonthWidget extends BaseWidget
{
    protected static ?string $heading = 'เครื่องมือที่สอบเทียบแล้ว';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 3;

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

        return $table
            ->heading(false)
            ->query(
                CalibrationRecord::query()
                    ->with('instrument')
                    ->whereBetween('cal_date', [$startDate, $endDate])
                    ->orderBy('cal_date', 'desc')
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
                    ->label('วันที่สอบเทียบ')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_cal_date')
                    ->label('ครบกำหนดครั้งถัดไป')
                    ->date('d/m/Y')
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('cal_by')
                    ->label('ผู้สอบเทียบ'),
            ])
            ->defaultSort('cal_date', 'desc')
            ->emptyStateHeading('ไม่มีเครื่องมือที่สอบเทียบแล้วในช่วงที่เลือก')
            ->emptyStateDescription('ยังไม่มีการสอบเทียบเครื่องมือในช่วงเวลานี้')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public function getTableHeading(): string
    {
        if ($this->viewByYear) {
            $selectedDate = Carbon::createFromFormat('Y', $this->selectedYear);
            $startDate = $selectedDate->copy()->startOfYear();
            $endDate = $selectedDate->copy()->endOfYear();
            $year = $this->selectedYear;
            $thaiYear = (int)$year + 543;
            $count = \App\Models\CalibrationRecord::whereBetween('cal_date', [$startDate, $endDate])->count();
            return "เครื่องมือที่สอบเทียบแล้ว - พ.ศ. {$thaiYear} ({$count} รายการ)";
        }
        
        $selectedDate = $this->selectedMonth 
            ? Carbon::createFromFormat('Y-m', $this->selectedMonth) 
            : Carbon::now();
        
        $startDate = $selectedDate->copy()->startOfMonth();
        $endDate = $selectedDate->copy()->endOfMonth();
        $count = \App\Models\CalibrationRecord::whereBetween('cal_date', [$startDate, $endDate])->count();
        
        return 'เครื่องมือที่สอบเทียบแล้ว - ' . $selectedDate->locale('th')->translatedFormat('F Y') . " ({$count} รายการ)";
    }
}
