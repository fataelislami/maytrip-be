<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripController extends Controller
{
    /**
     * GET /api/trips — discover feed (recent public trips).
     *
     * Query params:
     *   q?    string  search by title/destination/description
     *   limit int     max 100, default 20
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = $request->string('q')->toString();
        $limit = (int) $request->integer('limit', 20);
        $limit = max(1, min($limit, 100));

        $query = Trip::with(['user', 'sections', 'items', 'galleryPhotos'])
            ->withCount('savedBy')
            ->where('trip_visibility', 'public')
            ->latest();

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'like', $like)
                    ->orWhere('destination', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        return TripResource::collection($query->limit($limit)->get());
    }

    /**
     * GET /api/users/{username}/trips/{slug} — single public trip.
     * Also increments view count (best-effort, ignores own views later).
     */
    public function show(string $username, string $slug): TripResource
    {
        $trip = Trip::whereHas('user', fn ($q) => $q->where('username', $username))
            ->where('slug', $slug)
            ->with(['user', 'sections', 'items', 'galleryPhotos'])
            ->firstOrFail();

        // Identify viewer via Sanctum token (works on public routes too)
        $viewer = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();

        // Unlisted/private trips: only the owner can view via /{username}/{slug}.
        // Non-owners of unlisted trips must use /share/{token}.
        $vis = $trip->trip_visibility ?? 'public';
        $isOwner = $viewer && $viewer->id === $trip->user_id;
        if ($vis !== 'public' && ! $isOwner) {
            abort(404);
        }

        // Increment views (skip self-views)
        if (! $viewer || $viewer->id !== $trip->user_id) {
            $trip->increment('views_count');
        }

        return new TripResource($trip);
    }
}
