<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TripSectionController extends Controller
{
    /**
     * POST /api/me/trips/{slug}/sections — append a section.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $trip = $this->findOwnedTrip($request, $slug);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
        ]);

        $maxOrder = (int) $trip->sections()->max('order');

        $section = $trip->sections()->create([
            'title' => $data['title'],
            'order' => $maxOrder + 1,
        ]);

        return response()->json($this->serialize($section), 201);
    }

    /**
     * PATCH /api/me/trips/{slug}/sections/{id} — rename / reorder a section.
     */
    public function update(Request $request, string $slug, int $id): JsonResponse
    {
        $trip = $this->findOwnedTrip($request, $slug);
        $section = $trip->sections()->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:100'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (! empty($data)) {
            $section->update($data);
        }

        return response()->json($this->serialize($section->refresh()));
    }

    /**
     * DELETE /api/me/trips/{slug}/sections/{id}
     * Items inside the section become unscheduled (section_id = null).
     */
    public function destroy(Request $request, string $slug, int $id): Response
    {
        $trip = $this->findOwnedTrip($request, $slug);
        $section = $trip->sections()->where('id', $id)->firstOrFail();

        $trip->items()->where('section_id', $section->id)->update(['section_id' => null]);
        $section->delete();

        return response()->noContent();
    }

    private function findOwnedTrip(Request $request, string $slug): Trip
    {
        /** @var Trip */
        return $request->user()->trips()->where('slug', $slug)->firstOrFail();
    }

    private function serialize(Section $s): array
    {
        return [
            'id' => (string) $s->id,
            'title' => $s->title,
            'order' => (int) $s->order,
        ];
    }
}
