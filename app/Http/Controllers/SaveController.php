<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SaveController extends Controller
{
    /**
     * GET /api/me/saves — trips the current user has bookmarked.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $trips = $request->user()->savedTrips()
            ->with(['user', 'sections', 'items', 'galleryPhotos'])
            ->orderByDesc('saves.created_at')
            ->get();

        return TripResource::collection($trips);
    }

    /**
     * POST /api/me/saves — bookmark a trip.
     * Body: { "trip_id": 123 }
     */
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
        ]);

        $request->user()->savedTrips()->syncWithoutDetaching([$data['trip_id']]);

        return response()->noContent();
    }

    /**
     * DELETE /api/me/saves/{trip} — remove a bookmark.
     */
    public function destroy(Request $request, Trip $trip): Response
    {
        $request->user()->savedTrips()->detach($trip->id);
        return response()->noContent();
    }
}
