<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardCacheHelper
{
    /**
     * Clear all dashboard related stats cache.
     * Since we use file cache which doesn't support tagging efficiently,
     * we will attempt to clear the most common keys and mostly rely on
     * the "dashboard_last_updated" timestamp strategy if we were to implement it fully.
     * 
     * However, to be safe and simple given the unknown key variations:
     * We will use a "version" key strategy or just accept that we can't easily clear everything
     * without a pattern matcher. 
     * 
     * BETTER STRATEGY: 
     * We will implement a "versioning" system for the dashboard cache.
     * All widgets will append a version number to their cache keys.
     * When we want to clear cache, we just increment the version number in cache.
     * 
     * NOTE: This requires updating the Widgets to use this version key.
     * FOR NOW: We will try to clear the specific keys for the current and adjacent months/years.
     */
    public static function clearDashboardCache(): void
    {
        // 1. Clear Stats for Current Month/Year (Most common view)
        $now = Carbon::now();
        $months = [$now->month, $now->copy()->subMonth()->month, $now->copy()->addMonth()->month, 0]; // 0 = All
        $years = [$now->year, $now->year - 1, $now->year + 1, 0]; // 0 = All
        $levels = [null, 'A', 'B', 'C'];
        $calPlaces = [null, 'Internal', 'External'];

        // Iterate through likely combinations to clear them
        // This is brute-force but works for the default view
        foreach ($months as $m) {
            foreach ($years as $y) {
                foreach ($calPlaces as $cp) {
                   // Level is usually null in default view, but we check common ones
                   $cpKey = $cp ?? '';
                   
                   // Stats Widget Keys from CalibrationStatsWidget
                   // keys: due_stats_{m}_{y}_{l}_{cp}, calibrated_stats_..., overdue_stats_...
                   foreach ($levels as $l) {
                       $lKey = $l ?? '';
                       
                       $suffix = "{$m}_{$y}_{$lKey}_{$cpKey}";
                       
                       Cache::forget("due_stats_{$suffix}");
                       Cache::forget("calibrated_stats_{$suffix}");
                       Cache::forget("overdue_stats_{$suffix}");
                       
                       // Overdue Widget Keys
                       Cache::forget("overdue_count_{$suffix}");
                   }
                }
            }
        }
        
        // Also clear a global timestamp key if we decide to use it later
        Cache::put('dashboard_last_modified', now()->timestamp, 86400);
    }
}
