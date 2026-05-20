<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\BudgetAccessRequest;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetRequestController extends Controller
{
    /**
     * Viewer creates a budget-access request for a given trip.
     * POST /api/users/{username}/trips/{slug}/budget-requests
     */
    public function store(string $username, string $slug): JsonResponse
    {
        $viewer = Auth::user();
        abort_unless($viewer, 401);

        $owner = User::where('username', $username)->firstOrFail();
        $trip = Trip::where('user_id', $owner->id)
            ->where('slug', $slug)
            ->firstOrFail();

        // Owners don't request from themselves
        if ($trip->user_id === $viewer->id) {
            return response()->json([
                'status' => 'approved',
            ]);
        }

        // Only meaningful when the trip is actually budget=request
        if ($trip->budget_visibility !== 'request') {
            abort(409, 'This trip does not require a budget access request.');
        }

        $existing = BudgetAccessRequest::where('trip_id', $trip->id)
            ->where('user_id', $viewer->id)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => $existing->status,
                'createdAt' => $existing->created_at?->toIso8601String(),
            ]);
        }

        $req = BudgetAccessRequest::create([
            'trip_id' => $trip->id,
            'user_id' => $viewer->id,
            'status' => 'pending',
        ]);

        AppNotification::create([
            'user_id' => $trip->user_id,
            'type' => 'budget_request',
            'data' => [
                'requestId' => (string) $req->id,
                'tripId' => (string) $trip->id,
                'tripSlug' => $trip->slug,
                'tripTitle' => $trip->title,
                'requesterId' => (string) $viewer->id,
                'requesterName' => $viewer->name,
                'requesterUsername' => $viewer->username,
                'requesterAvatarUrl' => $viewer->avatar_url,
            ],
        ]);

        return response()->json([
            'status' => $req->status,
            'createdAt' => $req->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Owner lists budget-access requests across their trips.
     * GET /api/me/budget-requests
     */
    public function index(): JsonResponse
    {
        $owner = Auth::user();
        abort_unless($owner, 401);

        $tripIds = Trip::where('user_id', $owner->id)->pluck('id');

        $requests = BudgetAccessRequest::with(['user', 'trip'])
            ->whereIn('trip_id', $tripIds)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(
            $requests->map(fn ($r) => [
                'id' => (string) $r->id,
                'status' => $r->status,
                'createdAt' => $r->created_at?->toIso8601String(),
                'decidedAt' => $r->decided_at?->toIso8601String(),
                'trip' => [
                    'id' => (string) $r->trip->id,
                    'slug' => $r->trip->slug,
                    'title' => $r->trip->title,
                ],
                'requester' => [
                    'id' => (string) $r->user->id,
                    'name' => $r->user->name,
                    'username' => $r->user->username,
                    'avatarUrl' => $r->user->avatar_url,
                ],
            ])
        );
    }

    /**
     * Owner approves or rejects a request.
     * PATCH /api/me/budget-requests/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $owner = Auth::user();
        abort_unless($owner, 401);

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $req = BudgetAccessRequest::with('trip')->findOrFail($id);
        abort_unless($req->trip->user_id === $owner->id, 403);

        DB::transaction(function () use ($req, $data) {
            $req->status = $data['status'];
            $req->decided_at = now();
            $req->save();
        });

        AppNotification::create([
            'user_id' => $req->user_id,
            'type' => $req->status === 'approved' ? 'budget_approved' : 'budget_rejected',
            'data' => [
                'requestId' => (string) $req->id,
                'tripId' => (string) $req->trip->id,
                'tripSlug' => $req->trip->slug,
                'tripTitle' => $req->trip->title,
                'ownerUsername' => $owner->username,
                'ownerName' => $owner->name,
            ],
        ]);

        return response()->json([
            'id' => (string) $req->id,
            'status' => $req->status,
            'decidedAt' => $req->decided_at?->toIso8601String(),
        ]);
    }
}
