<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use HasFactory;

    /**
     * Mass-assignable fields. `likes_count` and `views_count` are deliberately
     * NOT here — they're engagement metrics and must only be mutated via the
     * dedicated controllers/jobs that actually count the events.
     */
    protected $fillable = [
        'user_id',
        'slug',
        'share_token',
        'title',
        'destination',
        'currency',
        'duration_days',
        'description',
        'cover_url',
        'budget_visibility',
        'trip_visibility',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'likes_count' => 'integer',
        'views_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('order');
    }

    public function galleryPhotos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class)->orderBy('order');
    }

    /**
     * Users who bookmarked this trip — pivot table `saves`.
     * Use `withCount('savedBy')` on queries to surface the `saved_by_count`
     * attribute without pulling the full user list.
     */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saves')->withTimestamps();
    }

    /**
     * Users who hit "like" on this trip — pivot table `likes`. The
     * denormalised counter lives in `trips.likes_count` and is kept in sync
     * by LikeController on every toggle.
     */
    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes')->withTimestamps();
    }

    public function budgetAccessRequests(): HasMany
    {
        return $this->hasMany(BudgetAccessRequest::class);
    }

    public function viewerHasApprovedBudgetAccess(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }
        return $this->budgetAccessRequests()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->exists();
    }
}
