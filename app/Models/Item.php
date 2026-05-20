<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'section_id',
        'title',
        'note',
        'category',
        'price',
        'time_start',
        'time_end',
        'quantity',
        'source_url',
        'photo_url',
        'story_tagged_at',
        'status',
        'source',
        'order',
    ];

    protected $casts = [
        'price' => 'integer',
        'quantity' => 'integer',
        'order' => 'integer',
        'story_tagged_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
