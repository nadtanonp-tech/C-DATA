<?php

namespace App\Filament\Widgets;

use App\Models\CalibrationRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OverdueInstrumentsWidget extends BaseWidget
{
    protected static ?string $heading = 'เครื่องมือที่เลยกำหนดสอบเทียบ';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 4;

    protected static string $view = 'filament.widgets.collapsible-table-widget';

    /**
     * ดึง record IDs ที่เลยกำหนดและยังไม่ได้สอบเทียบ
     */
    private function getOverdueRecordIds(): array
    {
        $today = Carbon::today();
        
        return DB::table('calibration_logs as cl')
            ->leftJoin('calibration_logs as newer', function ($join) {
                $join->on('newer.instrument_id', '=', 'cl.instrument_id')
                     ->whereColumn('newer.cal_date', '>', 'cl.cal_date');
            })
            ->whereNull('newer.id')
            ->where('cl.next_cal_date', '<', $today)
            ->pluck('cl.id')
            ->toArray();
    }

    public function table(Table $table): Table
    {
        $overdueIds = $this->getOverdueRecordIds();
        
        if (empty($overdueIds)) {
            $overdueIds = [0];
        }

        return $table
            ->heading(false)
            ->query(
                CalibrationRecord::query()
                    ->with('instrument')
                    ->whereIn('id', $overdueIds)
                    ->orderBy('next_cal_date', 'asc')
            )
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25, 50])
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
        $count = count($this->getOverdueRecordIds());
        return "เครื่องมือที่เลยกำหนดสอบเทียบ ({$count} รายการ)";
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
