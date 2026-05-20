<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * GET /api/users/{username} — public profile + their trips.
     */
    public function show(string $username): JsonResponse
    {
        $user = User::where('username', $username)->firstOrFail();

        // Owner sees their private trips too; others see only public
        $viewer = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
        $isOwner = $viewer && $viewer->id === $user->id;

        $tripsQuery = $user->trips()
            ->with(['user', 'sections', 'items', 'galleryPhotos'])
            ->latest();

        if (! $isOwner) {
            $tripsQuery->where('trip_visibility', 'public');
        }

        $trips = $tripsQuery->get();

        // Public stats only count public trips (no leaking private metrics)
        $publicTrips = $user->trips()->where('trip_visibility', 'public');

        return response()->json([
            'user' => [
                'username' => $user->username,
                'name' => $user->name,
                'avatarUrl' => $user->avatar_url,
                'coverUrl' => $user->cover_url,
                'bio' => $user->bio,
                'location' => $user->location,
                'link' => $user->link,
                'tripsCount' => $isOwner ? $user->trips()->count() : $publicTrips->count(),
                'totalLikes' => (int) $publicTrips->sum('likes_count'),
                'totalViews' => (int) $publicTrips->sum('views_count'),
            ],
            'trips' => TripResource::collection($trips),
        ]);
    }
}
