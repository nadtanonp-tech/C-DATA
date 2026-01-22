<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasingStatusHistory extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function purchasingRecord(): BelongsTo
    {
        return $this->belongsTo(PurchasingRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
