<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMonthlyPlans extends Command
{
    protected $signature = 'fix:monthly-plans';
    protected $description = 'Remove duplicate monthly plans to prepare for migration';

    public function handle()
    {
        $this->info('Checking for duplicates...');

        $duplicates = DB::table('monthly_plans')
            ->select('plan_month', 'department', 'calibration_type', DB::raw('COUNT(*) as count'))
            ->groupBy('plan_month', 'department', 'calibration_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicates found.');
            return;
        }

        $this->warn("Found {$duplicates->count()} duplicate groups.");

        foreach ($duplicates as $dup) {
            $this->line(" - {$dup->plan_month} | {$dup->department} | {$dup->calibration_type}: {$dup->count} records");
        }

        $this->info('Cleaning up duplicates (keeping the latest ID)...');

        // Logic to keep MAX ID
        // Note: We use a subquery approach compatible with Postgres/MySQL
        // Deleting where ID is NOT IN the list of MAX IDs
        
        $deleted = DB::delete('
            DELETE FROM monthly_plans 
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) as id
                    FROM monthly_plans 
                    GROUP BY plan_month, department, calibration_type
                ) as ids_to_keep
            )
        ');

        $this->info("Deleted {$deleted} records.");
    }
}
