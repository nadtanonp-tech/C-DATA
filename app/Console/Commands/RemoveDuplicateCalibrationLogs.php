<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateCalibrationLogs extends Command
{
    protected $signature = 'calibration:remove-duplicates';
    protected $description = 'Remove duplicate calibration_logs entries (keep only the first record for each instrument_id + cal_date combination)';

    public function handle()
    {
        $this->info('🔍 กำลังหา duplicate records...');

        // หา duplicates (instrument_id + cal_date ที่มีมากกว่า 1 record)
        $duplicates = DB::table('calibration_logs')
            ->select('instrument_id', 'cal_date', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('instrument_id', 'cal_date')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        $totalDuplicateGroups = $duplicates->count();
        $this->info("📊 พบ {$totalDuplicateGroups} กลุ่มที่มี duplicates");

        if ($totalDuplicateGroups === 0) {
            $this->info('✅ ไม่พบ duplicate records!');
            return Command::SUCCESS;
        }

        $totalDeleted = 0;

        foreach ($duplicates as $dup) {
            // ลบ records ที่ไม่ใช่ record แรก (id ที่ต่ำสุด)
            $deleted = DB::table('calibration_logs')
                ->where('instrument_id', $dup->instrument_id)
                ->where('cal_date', $dup->cal_date)
                ->where('id', '!=', $dup->keep_id)
                ->delete();

            $totalDeleted += $deleted;
        }

        $this->info('');
        $this->info("🗑️ ลบ duplicate records ทั้งหมด {$totalDeleted} รายการ");
        $this->info('✅ เสร็จสิ้น!');

        return Command::SUCCESS;
    }
}
