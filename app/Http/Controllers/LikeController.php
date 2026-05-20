<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    /**
     * POST /api/trips/{trip}/like — toggle the current user's like on a trip.
     *
     * Idempotent: re-POSTing the same trip flips the state. Returns the
     * fresh state so the client can reconcile its optimistic UI:
     *   { liked: bool, likes: int }
     */
    public function toggle(Request $request, Trip $trip): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $trip) {
            $existing = $trip->likedBy()->where('user_id', $user->id)->exists();

            if ($existing) {
                $trip->likedBy()->detach($user->id);
                $trip->decrement('likes_count');
                $liked = false;
            } else {
                $trip->likedBy()->attach($user->id);
                $trip->increment('likes_count');
                $liked = true;
            }

            return response()->json([
                'liked' => $liked,
                'likes' => (int) $trip->fresh()->likes_count,
            ]);
        });
    }
}
