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

class OverdueInstrumentsWidget extends BaseWidget
{
    protected static ?string $heading = 'เครื่องมือที่เลยกำหนดสอบเทียบ';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 4;

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
     * ดึง record IDs ที่เลยกำหนดและยังไม่ได้สอบเทียบ
     */
    private function getOverdueRecordIds(): array
    {
        $today = Carbon::today();
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        $query = DB::table('calibration_logs as cl')
            ->leftJoin('calibration_logs as newer', function ($join) {
                $join->on('newer.instrument_id', '=', 'cl.instrument_id')
                     ->whereColumn('newer.cal_date', '>', 'cl.cal_date');
            })
            ->whereNull('newer.id')
            ->where('cl.next_cal_date', '<', $today);
        
        // กรองตามเดือน/ปี ของ next_cal_date (วันที่ครบกำหนด)
        if ($month === 0 && $year === 0) {
            // ทุกเดือน ทุกปี - ไม่ต้อง filter
        } elseif ($month === 0) {
            // ทุกเดือน ปีที่เลือก
            $query->whereRaw('EXTRACT(YEAR FROM cl.next_cal_date) = ?', [$year]);
        } elseif ($year === 0) {
            // เดือนที่เลือก ทุกปี
            $query->whereRaw('EXTRACT(MONTH FROM cl.next_cal_date) = ?', [$month]);
        } else {
            // เดือนและปีที่เลือก
            $query->whereRaw('EXTRACT(MONTH FROM cl.next_cal_date) = ?', [$month])
                  ->whereRaw('EXTRACT(YEAR FROM cl.next_cal_date) = ?', [$year]);
        }
        
        return $query->pluck('cl.id')->toArray();
    }

    public function table(Table $table): Table
    {
        $overdueIds = $this->getOverdueRecordIds();
        
        if (empty($overdueIds)) {
            $overdueIds = [0];
        }

        $query = CalibrationRecord::query()
            ->with('instrument')
            ->whereIn('id', $overdueIds)
            ->orderBy('next_cal_date', 'asc');
        
        // Filter by Level if selected
        if ($this->selectedLevel) {
            $query->where('cal_level', $this->selectedLevel);
        }

        return $table
            ->heading(false)
            ->query($query)
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25,])
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
                    ->color('danger'),
                Tables\Columns\TextColumn::make('overdue_days')
                    ->label('เลยกำหนด (วัน)')
                    ->getStateUsing(fn ($record) => (int) Carbon::parse($record->next_cal_date)->diffInDays(now()))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('next_cal_date', $direction === 'asc' ? 'desc' : 'asc');
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 365 ? 'danger' : ($state > 90 ? 'warning' : 'gray')),
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
            ->filters([
                Tables\Filters\SelectFilter::make('overdue_year')
                    ->label('ปีที่เลยกำหนด')
                    ->options($this->getYearOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'])) {
                            $year = $data['value'];
                            $startOfYear = Carbon::createFromFormat('Y', $year)->startOfYear();
                            $endOfYear = Carbon::createFromFormat('Y', $year)->endOfYear();
                            
                            return $query->whereBetween('next_cal_date', [$startOfYear, $endOfYear]);
                        }
                        return $query;
                    }),
            ])
            ->defaultSort('next_cal_date', 'asc')
            ->emptyStateHeading('ไม่มีเครื่องมือที่เลยกำหนด')
            ->emptyStateDescription('เครื่องมือทั้งหมดได้รับการสอบเทียบเรียบร้อยแล้ว')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public function getTableHeading(): string
    {
        $month = $this->selectedMonth ?? (int) Carbon::now()->format('m');
        $year = $this->selectedYear ?? (int) Carbon::now()->format('Y');
        
        $overdueIds = $this->getOverdueRecordIds();
        
        $query = CalibrationRecord::whereIn('id', $overdueIds);
        if ($this->selectedLevel) {
            $query->where('cal_level', $this->selectedLevel);
        }
        $count = $query->count();
        
        $levelText = $this->selectedLevel ? " - Level {$this->selectedLevel}" : '';
        
        // สร้างข้อความเดือน/ปี
        $monthText = $month === 0 ? '(ทั้งหมด)' : Carbon::createFromDate(2024, $month, 1)->locale('th')->translatedFormat('F');
        $yearText = $year === 0 ? '(ทั้งหมด)' : 'พ.ศ. ' . ($year + 543);
        
        return "เครื่องมือที่เลยกำหนดสอบเทียบ - {$monthText} {$yearText}{$levelText} ({$count} รายการ)";
    }

    /**
     * สร้าง options สำหรับ dropdown เลือกปี
     */
    private function getYearOptions(): array
    {
        $options = [];
        $now = Carbon::now();
        
        // 5 ปีก่อนหน้า
        for ($i = 5; $i >= 0; $i--) {
            $year = $now->copy()->subYears($i)->format('Y');
            $thaiYear = (int)$year + 543;
            $options[$year] = "พ.ศ. {$thaiYear} ({$year})";
        }

        return $options;
    }
}
