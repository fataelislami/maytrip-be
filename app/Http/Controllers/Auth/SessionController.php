<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SessionController extends Controller
{
    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'cover_url' => $user->cover_url,
            'bio' => $user->bio,
            'location' => $user->location,
            'link' => $user->link,
            'savedTripIds' => $user->savedTrips()->pluck('trips.id')
                ->map(fn ($id) => (string) $id)
                ->toArray(),
        ]);
    }

    /**
     * PATCH /api/me — update profile fields.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            // Profile photo + cover are full URLs (we host them on our public
            // storage disk, but a client could PATCH with any URL string).
            // Restrict the scheme so we never render a javascript:/data: URL.
            'avatar_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'cover_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:280'],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            // `link` is bare-domain user input (e.g. "example.com"). We prefix
            // it with https:// on render, but enforce a max length and strip
            // any whitespace/control chars to keep the prefixed URL well-formed.
            'link' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:/^[a-zA-Z0-9._~:\/?#\[\]@!$&\'()*+,;=%-]*$/'],
        ], [
            'username.unique' => 'That username is already taken.',
            'username.regex' => 'Username may only contain lowercase letters, numbers, and underscores.',
        ]);

        $user->fill($data)->save();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'cover_url' => $user->cover_url,
            'bio' => $user->bio,
            'location' => $user->location,
            'link' => $user->link,
        ]);
    }

    /**
     * GET /api/username-available?u=foo
     * Checks if a username is available for the current viewer.
     */
    public function usernameAvailable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'u' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/'],
        ]);
        $viewer = $request->user();
        $taken = User::where('username', $data['u'])
            ->when($viewer, fn ($q) => $q->where('id', '!=', $viewer->id))
            ->exists();
        return response()->json(['available' => ! $taken]);
    }

    /**
     * Revoke the current personal access token.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    /**
     * POST /api/me/tokens — mint a new Sanctum token for an external surface
     * (e.g. the browser extension). Lets callers revoke that token without
     * affecting the user's web session.
     */
    public function issueToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $token = $user->createToken($data['name']);

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $data['name'],
            'createdAt' => now()->toIso8601String(),
        ]);
    }
}
