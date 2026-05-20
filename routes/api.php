<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\BudgetRequestController;
use App\Http\Controllers\MyTripController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SaveController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripItemController;
use App\Http\Controllers\TripSectionController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
 * ────────────── Public read endpoints ──────────────
 */
Route::get('/trips', [TripController::class, 'index']);
Route::get('/users/{username}', [UserController::class, 'show'])
    ->whereAlphaNumeric('username');
Route::get('/users/{username}/trips/{slug}', [TripController::class, 'show'])
    ->whereAlphaNumeric('username');

// Public access for unlisted trips via random share token (no username/slug)
Route::get('/share/{token}', [ShareController::class, 'show']);

/*
 * ────────────── Google OAuth ──────────────
 * Browser-redirect endpoints (not consumed via fetch).
 */
Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/callback', [GoogleAuthController::class, 'callback']);
});

/*
 * ────────────── Authenticated (Sanctum Bearer) ──────────────
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SessionController::class, 'me']);
    Route::patch('/me', [SessionController::class, 'update'])->middleware('throttle:30,1');
    // The availability checker can be abused as a username enumerator and
    // token mint endpoint is a credential-creation surface — both rate-limited.
    Route::get('/me/username-available', [SessionController::class, 'usernameAvailable'])
        ->middleware('throttle:60,1');
    Route::post('/me/tokens', [SessionController::class, 'issueToken'])
        ->middleware('throttle:10,1');
    Route::post('/auth/logout', [SessionController::class, 'logout']);

    Route::get('/me/trips', [MyTripController::class, 'index']);
    Route::post('/me/trips', [MyTripController::class, 'store']);
    Route::put('/me/trips/{slug}', [MyTripController::class, 'update']);
    Route::delete('/me/trips/{slug}', [MyTripController::class, 'destroy']);

    // Granular item / section CRUD — used by the browser extension and any
    // future in-place edits from the web app.
    Route::post('/me/trips/{slug}/items', [TripItemController::class, 'store']);
    Route::patch('/me/trips/{slug}/items/reorder', [TripItemController::class, 'reorder']);
    Route::patch('/me/trips/{slug}/items/{id}', [TripItemController::class, 'update'])->whereNumber('id');
    Route::delete('/me/trips/{slug}/items/{id}', [TripItemController::class, 'destroy'])->whereNumber('id');

    Route::post('/me/trips/{slug}/sections', [TripSectionController::class, 'store']);
    Route::patch('/me/trips/{slug}/sections/{id}', [TripSectionController::class, 'update'])->whereNumber('id');
    Route::delete('/me/trips/{slug}/sections/{id}', [TripSectionController::class, 'destroy'])->whereNumber('id');

    Route::post('/me/trips/fork/{username}/{slug}', [MyTripController::class, 'fork'])
        ->whereAlphaNumeric('username');

    Route::post('/me/uploads/cover', [UploadController::class, 'cover']);
    Route::post('/me/uploads/gallery', [UploadController::class, 'gallery']);
    Route::post('/me/uploads/avatar', [UploadController::class, 'avatar']);
    Route::post('/me/uploads/user-cover', [UploadController::class, 'userCover']);

    Route::get('/me/notifications', [NotificationController::class, 'index']);
    Route::patch('/me/notifications/{id}/read', [NotificationController::class, 'read'])
        ->whereNumber('id');
    Route::post('/me/notifications/read-all', [NotificationController::class, 'readAll']);

    Route::get('/me/saves', [SaveController::class, 'index']);
    Route::post('/me/saves', [SaveController::class, 'store']);
    Route::delete('/me/saves/{trip}', [SaveController::class, 'destroy']);

    // Budget-access requests. Creation is throttled so a bored attacker can't
    // flood owners with notifications by hammering different trips.
    Route::post('/users/{username}/trips/{slug}/budget-requests', [BudgetRequestController::class, 'store'])
        ->whereAlphaNumeric('username')
        ->middleware('throttle:20,1');
    Route::get('/me/budget-requests', [BudgetRequestController::class, 'index']);
    Route::patch('/me/budget-requests/{id}', [BudgetRequestController::class, 'update'])
        ->whereNumber('id');
});
