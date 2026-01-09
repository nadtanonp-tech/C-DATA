<?php

namespace App\Filament\Widgets;

use App\Models\CalibrationRecord;
use App\Models\Instrument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalibrationStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    /**
     * นับเครื่องมือที่ next_cal_date อยู่ในช่วงที่กำหนด และยังไม่มีการสอบเทียบใหม่กว่า (ใช้ SQL เดียว)
     */
    private function countDueRecords($startOfMonth, $endOfMonth): int
    {
        return DB::table('calibration_logs as cl')
            ->leftJoin('calibration_logs as newer', function ($join) {
                $join->on('newer.instrument_id', '=', 'cl.instrument_id')
                     ->whereColumn('newer.cal_date', '>', 'cl.cal_date');
            })
            ->whereNull('newer.id')
            ->whereBetween('cl.next_cal_date', [$startOfMonth, $endOfMonth])
            ->count();
    }

    /**
     * นับเครื่องมือที่เลยกำหนดทั้งหมด (next_cal_date < วันนี้ และยังไม่ได้สอบเทียบใหม่)
     */
    private function countAllOverdue(): int
    {
        $today = Carbon::today();
        
        return DB::table('calibration_logs as cl')
            ->leftJoin('calibration_logs as newer', function ($join) {
                $join->on('newer.instrument_id', '=', 'cl.instrument_id')
                     ->whereColumn('newer.cal_date', '>', 'cl.cal_date');
            })
            ->whereNull('newer.id')
            ->where('cl.next_cal_date', '<', $today)
            ->count();
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // นับจำนวนเครื่องมือที่ครบกำหนดเดือนนี้ (ที่ยังไม่ได้สอบเทียบ)
        $dueThisMonth = $this->countDueRecords($startOfMonth, $endOfMonth);

        // นับจำนวนเครื่องมือที่เลยกำหนดทั้งหมด (ที่ยังไม่ได้สอบเทียบ)
        $overdue = $this->countAllOverdue();

        // นับจำนวนเครื่องมือที่สอบเทียบแล้วในเดือนนี้
        $calibratedThisMonth = CalibrationRecord::whereBetween('cal_date', [$startOfMonth, $endOfMonth])->count();

        // นับจำนวนเครื่องมือทั้งหมด
        $totalInstruments = Instrument::count();

        return [
            Stat::make('ครบกำหนดเดือนนี้', $dueThisMonth)
                ->description('เครื่องมือที่ต้องสอบเทียบเดือน ' . $now->locale('th')->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),
            Stat::make('สอบเทียบแล้วเดือนนี้', $calibratedThisMonth)
                ->description('เครื่องมือที่สอบเทียบแล้วในเดือน ' . $now->locale('th')->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('เลยกำหนดทั้งหมด', $overdue)
                ->description('เครื่องมือที่เลยกำหนดสอบเทียบ')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'success'),
            Stat::make('เครื่องมือทั้งหมด', $totalInstruments)
                ->description('จำนวนเครื่องมือในระบบ')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('gray'),
        ];
    }
}
