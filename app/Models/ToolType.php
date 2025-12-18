<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Master;

class ToolType extends Model
{
    use HasFactory;

    // อนุญาตให้แก้ไขข้อมูลได้ทุกฟิลด์
    protected $guarded = [];

    // 🟢 สำคัญ: บอก Laravel ว่าฟิลด์พวกนี้เป็น JSON นะ (เวลาดึงมาใช้จะเป็น Array ทันที)
    protected $casts = [
        'dimension_specs' => 'array',
        'ui_options'      => 'array',
        'criteria_unit'   => 'array',
    ];

    // ความสัมพันธ์: 1 Type มีเครื่องมือลูกได้หลายตัว
    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class);
    }
    
    // ความสัมพันธ์: 1 Type มี Range ได้หลายช่วง
    public function calibrationRanges(): HasMany
    {
        return $this->hasMany(CalibrationRange::class);
    }
    
    // ความสัมพันธ์: 1 Type มี Master ได้หลายตัว
    public function masters(): BelongsToMany
    {
        return $this->belongsToMany(Master::class, 'standard_usages')
                ->withPivot('check_point') // ดึงฟิลด์พิเศษมาด้วย
                ->withTimestamps();
    }
}