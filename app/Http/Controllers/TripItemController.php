<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TripItemController extends Controller
{
    /**
     * POST /api/me/trips/{slug}/items — append a single item.
     * Used by the browser extension's right-click flow.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $trip = $this->findOwnedTrip($request, $slug);
        $data = $this->validateItem($request);

        $sectionId = $this->resolveSectionId($trip, $data['sectionId'] ?? null);

        $maxOrder = (int) $trip->items()->max('order');

        $item = $trip->items()->create([
            'section_id' => $sectionId,
            'title' => $data['title'],
            'note' => $data['note'] ?? null,
            'category' => $data['category'] ?? null,
            'price' => $data['price'] ?? 0,
            'time_start' => $data['timeStart'] ?? null,
            'time_end' => $data['timeEnd'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'source_url' => $data['sourceUrl'] ?? null,
            'status' => $data['status'] ?? null,
            'source' => $data['source'] ?? null,
            'order' => $maxOrder + 1,
        ]);

        return response()->json($this->serialize($item), 201);
    }

    /**
     * PATCH /api/me/trips/{slug}/items/{id} — partial update of one item.
     */
    public function update(Request $request, string $slug, int $id): JsonResponse
    {
        $trip = $this->findOwnedTrip($request, $slug);
        $item = $trip->items()->where('id', $id)->firstOrFail();
        $data = $this->validateItem($request, partial: true);

        $patch = [];
        foreach ([
            'title' => 'title',
            'note' => 'note',
            'category' => 'category',
            'price' => 'price',
            'timeStart' => 'time_start',
            'timeEnd' => 'time_end',
            'quantity' => 'quantity',
            'sourceUrl' => 'source_url',
            'status' => 'status',
            'source' => 'source',
        ] as $in => $col) {
            if (array_key_exists($in, $data)) {
                $patch[$col] = $data[$in];
            }
        }
        if (array_key_exists('sectionId', $data)) {
            $patch['section_id'] = $this->resolveSectionId($trip, $data['sectionId']);
        }

        if (! empty($patch)) {
            $item->update($patch);
        }

        return response()->json($this->serialize($item->refresh()));
    }

    /**
     * DELETE /api/me/trips/{slug}/items/{id}
     */
    public function destroy(Request $request, string $slug, int $id): Response
    {
        $trip = $this->findOwnedTrip($request, $slug);
        $trip->items()->where('id', $id)->firstOrFail()->delete();
        return response()->noContent();
    }

    /**
     * PATCH /api/me/trips/{slug}/items/reorder
     * Body: { order: [{ id: int, sectionId?: int|null }, ...] }
     * Rewrites `order` (and optionally section_id) for every item in one shot.
     */
    public function reorder(Request $request, string $slug): JsonResponse
    {
        $trip = $this->findOwnedTrip($request, $slug);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'integer'],
            'order.*.sectionId' => ['nullable', 'integer'],
        ]);

        $ownItemIds = $trip->items()->pluck('id')->all();
        $payloadIds = array_column($data['order'], 'id');
        $bad = array_diff($payloadIds, $ownItemIds);
        if (! empty($bad)) {
            abort(422, 'Reorder payload contains items that do not belong to this trip.');
        }

        $validSectionIds = $trip->sections()->pluck('id')->all();

        DB::transaction(function () use ($trip, $data, $validSectionIds) {
            foreach ($data['order'] as $position => $row) {
                $patch = ['order' => $position];
                if (array_key_exists('sectionId', $row)) {
                    $patch['section_id'] = $row['sectionId'] !== null
                        && in_array($row['sectionId'], $validSectionIds, true)
                        ? $row['sectionId']
                        : null;
                }
                $trip->items()->where('id', $row['id'])->update($patch);
            }
        });

        return response()->json(['ok' => true]);
    }

    /* ────────────── helpers ────────────── */

    private function findOwnedTrip(Request $request, string $slug): Trip
    {
        /** @var Trip */
        return $request->user()->trips()->where('slug', $slug)->firstOrFail();
    }

    private function resolveSectionId(Trip $trip, ?int $sectionId): ?int
    {
        if ($sectionId === null) return null;
        return $trip->sections()->where('id', $sectionId)->exists() ? $sectionId : null;
    }

    private function validateItem(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:200'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'nullable', Rule::in(['transport', 'lodging', 'food', 'activity', 'other'])],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sectionId' => ['sometimes', 'nullable', 'integer'],
            'timeStart' => ['sometimes', 'nullable', 'string', 'max:5'],
            'timeEnd' => ['sometimes', 'nullable', 'string', 'max:5'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            // Restrict scheme to http/https — Laravel's bare `url` rule allows
            // anything `parse_url` accepts, including `javascript:` which is
            // an XSS vector when this string is later rendered as an href.
            'sourceUrl' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'status' => ['sometimes', 'nullable', Rule::in(['planned', 'spent', 'cancelled'])],
            'source' => ['sometimes', 'nullable', Rule::in(['manual', 'story', 'extension'])],
        ];
        return $request->validate($rules);
    }

    private function serialize(Item $it): array
    {
        return [
            'id' => (string) $it->id,
            'title' => $it->title,
            'note' => $it->note,
            'category' => $it->category,
            'price' => (int) $it->price,
            'sectionId' => $it->section_id ? (string) $it->section_id : null,
            'timeStart' => $it->time_start,
            'timeEnd' => $it->time_end,
            'quantity' => $it->quantity,
            'sourceUrl' => $it->source_url,
            'status' => $it->status,
            'source' => $it->source,
            'order' => (int) $it->order,
        ];
    }
}
