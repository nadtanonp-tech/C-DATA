<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCalibrationTypeByCodeNo extends Command
{
    protected $signature = 'calibration:fix-type-by-code';
    protected $description = 'Fix calibration_type based on code_no patterns';

    public function handle()
    {
        $this->info('🔥 เริ่มการแก้ไข calibration_type ตาม code_no...');

        // 1. x-04-xxxx → ThreadPlugGauge
        $ids04 = DB::table('instruments')->where('code_no', 'LIKE', '%-04-%')->pluck('id')->toArray();
        $updated04 = DB::table('calibration_logs')->whereIn('instrument_id', $ids04)->update(['calibration_type' => 'ThreadPlugGauge']);
        $this->info("   ✅ x-04-xxxx → ThreadPlugGauge: {$updated04} records");

        // 2. x-05-xxxx → ThreadRingGauge
        $ids05 = DB::table('instruments')->where('code_no', 'LIKE', '%-05-%')->pluck('id')->toArray();
        $updated05 = DB::table('calibration_logs')->whereIn('instrument_id', $ids05)->update(['calibration_type' => 'ThreadRingGauge']);
        $this->info("   ✅ x-05-xxxx → ThreadRingGauge: {$updated05} records");

        // 3. x-06-xxxx → SerrationPlugGauge
        $ids06 = DB::table('instruments')->where('code_no', 'LIKE', '%-06-%')->pluck('id')->toArray();
        $updated06 = DB::table('calibration_logs')->whereIn('instrument_id', $ids06)->update(['calibration_type' => 'SerrationPlugGauge']);
        $this->info("   ✅ x-06-xxxx → SerrationPlugGauge: {$updated06} records");

        // 4. x-07-xxxx → SerrationRingGauge
        $ids07 = DB::table('instruments')->where('code_no', 'LIKE', '%-07-%')->pluck('id')->toArray();
        $updated07 = DB::table('calibration_logs')->whereIn('instrument_id', $ids07)->update(['calibration_type' => 'SerrationRingGauge']);
        $this->info("   ✅ x-07-xxxx → SerrationRingGauge: {$updated07} records");

        $total = $updated04 + $updated05 + $updated06 + $updated07;
        $this->info("🎉 เสร็จสิ้น! อัปเดตทั้งหมด {$total} records");

        return Command::SUCCESS;
    }
}
