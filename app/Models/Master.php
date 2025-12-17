<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Master extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function toolTypes(): BelongsToMany
    {
        return $this->belongsToMany(ToolType::class, 'standard_usages')
                    ->withPivot('check_point')
                    ->withTimestamps();
    }

    public function latestCalibrationRecord()
    {
        // ใช้ cache เพื่อไม่ให้ query ซ้ำ
        if (!isset($this->latestCalCache)) {
            $instrument = Instrument::where('code_no', $this->master_code)->first();
            
            if (!$instrument) {
                $this->latestCalCache = null;
                return null;
            }
            
            $this->latestCalCache = CalibrationRecord::where('instrument_id', $instrument->id)
                        ->orderBy('cal_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
        }
        
        return $this->latestCalCache;
    }
    
    // Accessor สำหรับ cal_status
    public function getCalStatusAttribute()
    {
        $latestCal = $this->latestCalibrationRecord();
        
        if (!$latestCal || is_null($latestCal->result_status)) {
            return 'Unknown';
        }
        
        // ทำความสะอาดข้อมูล
        $status = trim(strtolower($latestCal->result_status));
        
        if ($status === 'pass') {
            return 'Pass';
        } elseif ($status === 'reject') {
            return 'Reject';
        }
        
        return 'Unknown';
    }
}