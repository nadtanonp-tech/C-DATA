<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Master extends Model
{
    use HasFactory;

    // อนุญาตให้แก้ไขข้อมูลได้ทุกฟิลด์
    protected $guarded = [];

    // (แถม) ความสัมพันธ์ย้อนกลับ: Master ตัวนี้ ถูกใช้กับ Type ไหนบ้าง?
    public function toolTypes(): BelongsToMany
    {
        return $this->belongsToMany(ToolType::class, 'standard_usages')
                    ->withPivot('check_point')
                    ->withTimestamps();
    }
}