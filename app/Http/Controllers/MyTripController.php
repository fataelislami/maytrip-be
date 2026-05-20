<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as Status;

class MyTripController extends Controller
{
    /**
     * GET /api/me/trips — list current user's trips.
     */
    public function index(Request $request)
    {
        $trips = $request->user()
            ->trips()
            ->with(['user', 'sections', 'items', 'galleryPhotos'])
            ->latest()
            ->get();

        return TripResource::collection($trips);
    }

    /**
     * POST /api/me/trips — create a new trip (optionally forked from another).
     *
     * Body shape mirrors the frontend Trip type. Photos/likes/views are
     * never accepted from the client; only the user's own data structures.
     */
    public function store(Request $request): TripResource
    {
        $data = $this->validateTrip($request);
        $data = $this->normalizeVisibility($data);
        $user = $request->user();

        return DB::transaction(function () use ($user, $data) {
            $slug = $this->generateSlug($user->id, $data['title']);

            /** @var Trip $trip */
            $trip = $user->trips()->create([
                'slug' => $slug,
                'share_token' => Str::lower(Str::random(12)),
                'title' => $data['title'],
                'destination' => $data['destination'],
                'currency' => $data['currency'],
                'duration_days' => $data['durationDays'] ?? null,
                'description' => $data['description'] ?? null,
                'cover_url' => $data['coverUrl'] ?? null,
                'budget_visibility' => $data['budgetVisibility'] ?? 'public',
                'trip_visibility' => $data['tripVisibility'] ?? 'public',
            ]);

            $sectionMap = []; // clientId => sectionModelId
            if (! empty($data['sections'])) {
                foreach ($data['sections'] as $order => $sec) {
                    $created = $trip->sections()->create([
                        'title' => $sec['title'],
                        'order' => $order,
                    ]);
                    if (isset($sec['id'])) {
                        $sectionMap[$sec['id']] = $created->id;
                    }
                }
            }

            if (! empty($data['items'])) {
                foreach ($data['items'] as $order => $it) {
                    $trip->items()->create([
                        'section_id' => isset($it['sectionId']) && isset($sectionMap[$it['sectionId']])
                            ? $sectionMap[$it['sectionId']]
                            : null,
                        'title' => $it['title'],
                        'note' => $it['note'] ?? null,
                        'category' => $it['category'] ?? null,
                        'price' => $it['price'] ?? 0,
                        'time_start' => $it['timeStart'] ?? null,
                        'time_end' => $it['timeEnd'] ?? null,
                        'quantity' => $it['quantity'] ?? null,
                        'source_url' => $it['sourceUrl'] ?? null,
                        'status' => $it['status'] ?? null,
                        'source' => $it['source'] ?? null,
                        'order' => $order,
                    ]);
                }
            }

            if (! empty($data['galleryUrls'])) {
                foreach ($data['galleryUrls'] as $order => $url) {
                    $trip->galleryPhotos()->create([
                        'url' => $url,
                        'order' => $order,
                    ]);
                }
            }

            $trip->load(['user', 'sections', 'items', 'galleryPhotos']);
            return new TripResource($trip);
        });
    }

    /**
     * PUT /api/me/trips/{slug} — replace a trip's content with the given draft.
     * Simple destroy-and-recreate approach for now.
     */
    public function update(Request $request, string $slug): TripResource
    {
        $data = $this->validateTrip($request);
        $data = $this->normalizeVisibility($data);
        $user = $request->user();

        /** @var Trip $trip */
        $trip = $user->trips()->where('slug', $slug)->firstOrFail();

        return DB::transaction(function () use ($trip, $data) {
            $trip->update([
                'title' => $data['title'],
                'destination' => $data['destination'],
                'currency' => $data['currency'],
                'duration_days' => $data['durationDays'] ?? null,
                'description' => $data['description'] ?? null,
                'cover_url' => $data['coverUrl'] ?? null,
                'budget_visibility' => $data['budgetVisibility'] ?? 'public',
                'trip_visibility' => $data['tripVisibility'] ?? 'public',
            ]);

            // Wipe and recreate sections + items + gallery (simplest correct path)
            $trip->items()->delete();
            $trip->sections()->delete();
            $trip->galleryPhotos()->delete();

            $sectionMap = [];
            foreach (($data['sections'] ?? []) as $order => $sec) {
                $created = $trip->sections()->create([
                    'title' => $sec['title'],
                    'order' => $order,
                ]);
                if (isset($sec['id'])) {
                    $sectionMap[$sec['id']] = $created->id;
                }
            }

            foreach (($data['items'] ?? []) as $order => $it) {
                $trip->items()->create([
                    'section_id' => isset($it['sectionId']) && isset($sectionMap[$it['sectionId']])
                        ? $sectionMap[$it['sectionId']]
                        : null,
                    'title' => $it['title'],
                    'note' => $it['note'] ?? null,
                    'category' => $it['category'] ?? null,
                    'price' => $it['price'] ?? 0,
                    'time_start' => $it['timeStart'] ?? null,
                    'time_end' => $it['timeEnd'] ?? null,
                    'quantity' => $it['quantity'] ?? null,
                    'source_url' => $it['sourceUrl'] ?? null,
                    'status' => $it['status'] ?? null,
                    'source' => $it['source'] ?? null,
                    'order' => $order,
                ]);
            }

            foreach (($data['galleryUrls'] ?? []) as $order => $url) {
                $trip->galleryPhotos()->create(['url' => $url, 'order' => $order]);
            }

            $trip->load(['user', 'sections', 'items', 'galleryPhotos']);
            return new TripResource($trip);
        });
    }

    /**
     * DELETE /api/me/trips/{slug}
     */
    public function destroy(Request $request, string $slug): Response
    {
        $request->user()->trips()->where('slug', $slug)->firstOrFail()->delete();
        return response()->noContent();
    }

    /**
     * POST /api/me/trips/fork/{username}/{slug}
     * Copies someone else's trip into the current user's account.
     * Cover + gallery are NOT copied. Items keep title/category/price/time/qty/sourceUrl.
     */
    public function fork(Request $request, string $username, string $slug): TripResource
    {
        $source = Trip::whereHas('user', fn ($q) => $q->where('username', $username))
            ->where('slug', $slug)
            ->with(['sections', 'items'])
            ->firstOrFail();

        $user = $request->user();

        return DB::transaction(function () use ($source, $user) {
            $newSlug = $this->generateSlug($user->id, $source->title);

            /** @var Trip $fork */
            $fork = $user->trips()->create([
                'slug' => $newSlug,
                'share_token' => Str::lower(Str::random(12)),
                'title' => $source->title,
                'destination' => $source->destination,
                'currency' => $source->currency,
                'duration_days' => $source->duration_days,
                'description' => $source->description,
                'cover_url' => null,       // photos stripped
                'budget_visibility' => $source->budget_visibility,
                'trip_visibility' => 'private', // forked trips start private; owner picks later
            ]);

            $sectionMap = [];
            foreach ($source->sections as $sec) {
                $newSec = $fork->sections()->create([
                    'title' => $sec->title,
                    'order' => $sec->order,
                ]);
                $sectionMap[$sec->id] = $newSec->id;
            }

            foreach ($source->items as $it) {
                $fork->items()->create([
                    'section_id' => $it->section_id ? ($sectionMap[$it->section_id] ?? null) : null,
                    'title' => $it->title,
                    'note' => $it->note,
                    'category' => $it->category,
                    'price' => $it->price,
                    'time_start' => $it->time_start,
                    'time_end' => $it->time_end,
                    'quantity' => $it->quantity,
                    'source_url' => $it->source_url,
                    'order' => $it->order,
                    // Strip recap-side: photo_url, story_tagged_at, source, status
                ]);
            }

            $fork->load(['user', 'sections', 'items', 'galleryPhotos']);
            return new TripResource($fork);
        });
    }

    private function validateTrip(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'destination' => ['required', 'string', 'max:200'],
            'currency' => ['required', 'string', 'size:3'],
            'durationDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Restrict scheme to http/https everywhere we'll later render this
            // value as href/src to block javascript: / data: XSS vectors.
            'coverUrl' => ['nullable', 'url:http,https', 'max:500'],
            'galleryUrls' => ['nullable', 'array', 'max:10'],
            'galleryUrls.*' => ['url:http,https', 'max:500'],
            'budgetVisibility' => ['nullable', Rule::in(['public', 'hidden', 'request'])],
            'tripVisibility' => ['nullable', Rule::in(['public', 'unlisted', 'private'])],
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'string'],
            'sections.*.title' => ['required', 'string', 'max:100'],
            'items' => ['nullable', 'array'],
            'items.*.title' => ['required', 'string', 'max:200'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'items.*.category' => ['nullable', Rule::in(['transport', 'lodging', 'food', 'activity', 'other'])],
            'items.*.price' => ['nullable', 'integer', 'min:0'],
            'items.*.sectionId' => ['nullable', 'string'],
            'items.*.timeStart' => ['nullable', 'string', 'max:5'],
            'items.*.timeEnd' => ['nullable', 'string', 'max:5'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.sourceUrl' => ['nullable', 'url:http,https', 'max:500'],
            'items.*.status' => ['nullable', Rule::in(['planned', 'spent', 'cancelled'])],
            'items.*.source' => ['nullable', Rule::in(['manual', 'story', 'extension'])],
        ]);
    }

    /**
     * Enforce business rules:
     * - "Anyone with the link" (unlisted) → budget always public for link-viewers.
     * - "Only me" (private) → budget visibility is moot; normalize to public.
     */
    private function normalizeVisibility(array $data): array
    {
        $tv = $data['tripVisibility'] ?? 'public';
        if ($tv === 'unlisted' || $tv === 'private') {
            $data['budgetVisibility'] = 'public';
        }
        return $data;
    }

    private function generateSlug(int $userId, string $title): string
    {
        $base = Str::slug($title) ?: 'trip';
        $slug = $base;
        $n = 2;
        while (Trip::where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n;
            $n++;
        }
        return $slug;
    }
}
