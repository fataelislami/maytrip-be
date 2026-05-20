<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    /**
     * Handle Google's OAuth callback. Find/create the user, issue a Sanctum
     * token, then redirect back to the frontend with the token in the URL.
     */
    public function callback(Request $request): RedirectResponse
    {
        $frontend = rtrim(config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:3000'), '/');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $e) {
            return redirect("{$frontend}/auth/callback?error=" . urlencode('google_oauth_failed'));
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            $user = User::create([
                'google_id' => $googleUser->getId(),
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Traveler',
                'email' => $googleUser->getEmail(),
                'avatar_url' => $googleUser->getAvatar(),
                'username' => $this->generateUniqueUsername($googleUser),
                'email_verified_at' => now(),
            ]);
        } else {
            // Keep Google id + avatar fresh
            $patch = [];
            if (! $user->google_id) {
                $patch['google_id'] = $googleUser->getId();
            }
            if ($googleUser->getAvatar() && ! $user->avatar_url) {
                $patch['avatar_url'] = $googleUser->getAvatar();
            }
            if (! $user->username) {
                $patch['username'] = $this->generateUniqueUsername($googleUser);
            }
            if ($patch) {
                $user->update($patch);
            }
        }

        $token = $user->createToken('web', ['*'])->plainTextToken;

        return redirect("{$frontend}/auth/callback?token=" . urlencode($token));
    }

    private function generateUniqueUsername($googleUser): string
    {
        $base = Str::lower(Str::slug(
            $googleUser->getNickname()
                ?: Str::before($googleUser->getEmail(), '@')
                ?: 'user',
            ''
        ));
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?: 'user';

        $candidate = $base;
        $n = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base . $n;
            $n++;
        }
        return $candidate;
    }
}
