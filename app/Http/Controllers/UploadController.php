<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * POST /api/me/uploads/cover
     * Accepts an image file, stores it on the public disk, returns its URL.
     */
    public function cover(Request $request): JsonResponse
    {
        return $this->upload($request, 'uploads/covers', maxKb: 8192);
    }

    /**
     * POST /api/me/uploads/gallery — keep these light; lots of them per trip.
     */
    public function gallery(Request $request): JsonResponse
    {
        return $this->upload($request, 'uploads/gallery', maxKb: 2048);
    }

    /**
     * POST /api/me/uploads/avatar — user profile picture.
     */
    public function avatar(Request $request): JsonResponse
    {
        return $this->upload($request, 'uploads/avatars', maxKb: 4096);
    }

    /**
     * POST /api/me/uploads/user-cover — user profile banner.
     */
    public function userCover(Request $request): JsonResponse
    {
        return $this->upload($request, 'uploads/user-covers', maxKb: 8192);
    }

    private function upload(Request $request, string $folder, int $maxKb = 8192): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', "max:{$maxKb}"],
        ]);

        $path = $request->file('file')->store($folder, 'public');
        $url = rtrim(config('app.url'), '/') . '/storage/' . $path;

        return response()->json([
            'url' => $url,
            'path' => $path,
        ]);
    }
}
