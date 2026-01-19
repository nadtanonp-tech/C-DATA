<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncInstrumentStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'instruments:sync-status';

    /**
     * The console command description.
     */
    protected $description = 'Sync instruments.status with the latest record from instrument_status_histories';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 เริ่มการ Sync สถานะจาก instrument_status_histories...');

        // ดึง new_status ล่าสุดของแต่ละ instrument
        $latestStatuses = DB::table('instrument_status_histories as h')
            ->select('h.instrument_id', 'h.new_status')
            ->whereRaw('h.changed_at = (
                SELECT MAX(h2.changed_at)
                FROM instrument_status_histories h2
                WHERE h2.instrument_id = h.instrument_id
            )')
            ->get();

        $this->info("📊 พบ " . count($latestStatuses) . " instruments ที่ต้อง sync");

        $updated = 0;

        foreach ($latestStatuses as $record) {
            $affected = DB::table('instruments')
                ->where('id', $record->instrument_id)
                ->update(['status' => $record->new_status]);
            
            if ($affected > 0) {
                $updated++;
            }
        }

        $this->info("✅ Sync เสร็จสิ้น! อัปเดต {$updated} instruments");

        return Command::SUCCESS;
    }
}
