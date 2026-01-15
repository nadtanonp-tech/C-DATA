<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCalibrationType extends Command
{
    protected $signature = 'calibration:backfill-type';
    protected $description = 'Backfill calibration_type column based on instrument code_no pattern';

    public function handle()
    {
        $this->info('🔥 เริ่ม Backfill calibration_type...');

        // Mapping: รหัสกลาง (ตัวเลขหลังขีดแรก) => calibration_type
        $typeMapping = [
            '10' => 'VernierSpecial',
            '11' => 'Micrometer',
            '12' => 'DialCaliper',
            '13' => 'DialIndicator',
            '14' => 'DialTestIndicator',
            '15' => 'ThicknessGauge',
            '16' => 'ThicknessCaliper',
            '18' => 'PressureGauge',
            '19' => 'ChamferGauge',
        ];

        $totalUpdated = 0;

        foreach ($typeMapping as $code => $calibrationType) {
            // อัปเดต records ที่มี code_no ตรงกับ pattern (ทั้ง NULL และ VernierOther)
            $updated = DB::table('calibration_logs')
                ->join('instruments', 'calibration_logs.instrument_id', '=', 'instruments.id')
                ->where('instruments.code_no', 'LIKE', "%-{$code}-%")
                ->where(function ($query) {
                    $query->whereNull('calibration_logs.calibration_type')
                          ->orWhere('calibration_logs.calibration_type', 'VernierOther');
                })
                ->update(['calibration_logs.calibration_type' => $calibrationType]);

            if ($updated > 0) {
                $this->info("   ✅ อัปเดต {$updated} รายการ สำหรับ {$calibrationType} (code: {$code})");
                $totalUpdated += $updated;
            }
        }

        // อัปเดต records ที่ยังเป็น NULL โดยดูจาก calibration_data JSON
        $nullRecords = DB::table('calibration_logs')
            ->whereNull('calibration_type')
            ->get();

        $jsonUpdated = 0;
        foreach ($nullRecords as $record) {
            $calData = json_decode($record->calibration_data, true);
            if (isset($calData['calibration_type']) && !empty($calData['calibration_type'])) {
                DB::table('calibration_logs')
                    ->where('id', $record->id)
                    ->update(['calibration_type' => $calData['calibration_type']]);
                $jsonUpdated++;
            }
        }

        if ($jsonUpdated > 0) {
            $this->info("   ✅ อัปเดตจาก JSON: {$jsonUpdated} รายการ");
            $totalUpdated += $jsonUpdated;
        }

        $this->info('');
        $this->info("🎉 Backfill เสร็จสิ้น! อัปเดตทั้งหมด {$totalUpdated} รายการ");

        return Command::SUCCESS;
    }
}
