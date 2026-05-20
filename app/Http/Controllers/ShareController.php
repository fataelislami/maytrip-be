<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Models\Trip;

class ShareController extends Controller
{
    /**
     * GET /api/share/{token}
     * Resolve an unlisted (or public) trip by its random share_token.
     * Private trips are never resolvable via share token — owner uses their own URL.
     */
    public function show(string $token): TripResource
    {
        $trip = Trip::where('share_token', $token)
            ->with(['user', 'sections', 'items', 'galleryPhotos'])
            ->withCount('savedBy')
            ->firstOrFail();

        if ($trip->trip_visibility === 'private') {
            abort(404);
        }

        $viewer = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
        if (! $viewer || $viewer->id !== $trip->user_id) {
            $trip->increment('views_count');
        }

        return new TripResource($trip);
    }
}
