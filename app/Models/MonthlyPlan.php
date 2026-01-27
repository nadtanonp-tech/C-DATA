<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyPlan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'plan_month' => 'date',
        'plan_count' => 'integer',
        'cal_count' => 'integer',
        'remain_count' => 'integer',
        'level_a' => 'integer',
        'level_b' => 'integer',
        'level_c' => 'integer',
    ];

    public function toolType(): BelongsTo
    {
        return $this->belongsTo(ToolType::class);
    }

    /**
     * คำนวณ % Cal
     */
    public function getCalPercentAttribute(): float
    {
        if ($this->plan_count == 0) return 0;
        return round(($this->cal_count / $this->plan_count) * 100, 2);
    }

    /**
     * คำนวณ % Remain
     */
    public function getRemainPercentAttribute(): float
    {
        if ($this->plan_count == 0) return 0;
        return round(($this->remain_count / $this->plan_count) * 100, 2);
    }

    /**
     * คำนวณ % Level A
     */
    public function getLevelAPercentAttribute(): float
    {
        if ($this->cal_count == 0) return 0;
        return round(($this->level_a / $this->cal_count) * 100, 2);
    }

    /**
     * คำนวณ % Level B
     */
    public function getLevelBPercentAttribute(): float
    {
        if ($this->cal_count == 0) return 0;
        return round(($this->level_b / $this->cal_count) * 100, 2);
    }

    /**
     * คำนวณ % Level C
     */
    public function getLevelCPercentAttribute(): float
    {
        if ($this->cal_count == 0) return 0;
        return round(($this->level_c / $this->cal_count) * 100, 2);
    }
}
