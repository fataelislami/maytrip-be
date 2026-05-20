<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['trip_id', 'url', 'order'];

    protected $casts = ['order' => 'integer'];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
