<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
        $isOwner = $viewer && $viewer->id === $this->user_id;

        // Effective budget access for this viewer.
        $rawBudgetVisibility = $this->budget_visibility ?? 'public';
        $hasApprovedRequest = $rawBudgetVisibility === 'request'
            && $viewer
            && $this->viewerHasApprovedBudgetAccess($viewer->id);

        $canSeeBudget = $isOwner
            || $rawBudgetVisibility === 'public'
            || $hasApprovedRequest;

        // For request-mode trips, surface to the viewer whether THEY were already
        // approved so frontend can show "public" UX without leaking to others.
        $effectiveBudgetVisibility = $canSeeBudget ? 'public' : $rawBudgetVisibility;

        $items = $this->items->map(fn ($it) => [
            'id' => (string) $it->id,
            'title' => $it->title,
            'note' => $it->note,
            'category' => $it->category,
            'price' => $canSeeBudget ? (int) $it->price : 0,
            'sectionId' => $it->section_id ? (string) $it->section_id : null,
            'timeStart' => $it->time_start,
            'timeEnd' => $it->time_end,
            'quantity' => $it->quantity,
            'sourceUrl' => $it->source_url,
            'photoUrl' => $it->photo_url,
            'storyTaggedAt' => optional($it->story_tagged_at)?->toIso8601String(),
            'status' => $it->status,
            'source' => $it->source,
        ]);

        $sections = $this->sections->map(fn ($s) => [
            'id' => (string) $s->id,
            'title' => $s->title,
        ]);

        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            // Share token only exposed to the trip owner
            'shareToken' => $isOwner ? $this->share_token : null,
            'title' => $this->title,
            'destination' => $this->destination,
            'currency' => $this->currency,
            'durationDays' => $this->duration_days,
            'description' => $this->description,
            'coverUrl' => $this->cover_url,
            'galleryUrls' => $this->galleryPhotos->pluck('url'),
            'sections' => $sections,
            'tripVisibility' => $this->trip_visibility ?? 'public',
            'budgetVisibility' => $effectiveBudgetVisibility,
            'items' => $items,
            'creator' => [
                'username' => $this->user->username,
                'name' => $this->user->name,
                'avatarUrl' => $this->user->avatar_url,
                'bio' => $this->user->bio,
                'location' => $this->user->location,
                'link' => $this->user->link,
            ],
            'likes' => (int) $this->likes_count,
            'views' => (int) $this->views_count,
            // `saved_by_count` is populated when callers do `withCount('savedBy')`.
            // Falls back to 0 when not pre-loaded so single-trip endpoints
            // don't have to opt in.
            'saves' => (int) ($this->saved_by_count ?? 0),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
