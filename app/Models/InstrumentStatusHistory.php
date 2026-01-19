<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'instrument_id',
        'old_status',
        'new_status',
        'reason',
        'changed_at',
        'changed_by',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * ความสัมพันธ์กับ Instrument
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    /**
     * ความสัมพันธ์กับ User (ผู้เปลี่ยนสถานะ)
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
