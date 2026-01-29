<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentOwnershipHistory extends Model
{
    use HasFactory;

    // ไม่มี updated_at เพราะเก็บแค่ history
    protected $guarded = []; // Allow everything for now, or specify fillable

    
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
