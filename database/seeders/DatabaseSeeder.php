<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $aiko = User::firstOrCreate(
            ['email' => 'aiko@example.com'],
            [
                'name' => 'Aiko Tanaka',
                'username' => 'aiko',
                'avatar_url' => 'https://i.pravatar.cc/160?u=aiko',
                'bio' => 'Tokyo-based mom of two. I share every yen we spent on family trips across Asia.',
                'location' => 'Tokyo, Japan',
                'link' => 'aikotravels.com',
                'email_verified_at' => now(),
            ]
        );

        $rio = User::firstOrCreate(
            ['email' => 'rio@example.com'],
            [
                'name' => 'Rio Wijaya',
                'username' => 'rio',
                'avatar_url' => 'https://i.pravatar.cc/160?u=rio',
                'bio' => 'Long-haul backpacker. Budget over comfort, always.',
                'location' => 'Jakarta, Indonesia',
                'email_verified_at' => now(),
            ]
        );

        $luc = User::firstOrCreate(
            ['email' => 'luc@example.com'],
            [
                'name' => 'Luc Martin',
                'username' => 'luc',
                'avatar_url' => 'https://i.pravatar.cc/160?u=luc',
                'bio' => 'Slow travel. One city per week, minimum.',
                'location' => 'Paris, France',
                'email_verified_at' => now(),
            ]
        );

        $this->seedOsakaFamily($aiko);
        $this->seedHokkaidoUpcoming($aiko);
        $this->seedBaliBackpacking($rio);
        $this->seedParisHoneymoon($luc);
    }

    private function seedOsakaFamily(User $aiko): void
    {
        $trip = Trip::updateOrCreate(
            ['user_id' => $aiko->id, 'slug' => 'osaka-family'],
            [
                'title' => 'Osaka Family Trip',
                'destination' => 'Osaka, Japan',
                'currency' => 'JPY',
                'duration_days' => 5,
                'description' => 'A 3-generation family trip centered on Universal Studios Japan, with a quiet day in Kyoto in the middle. Everything booked 6 weeks ahead.',
                'cover_url' => 'https://picsum.photos/seed/osaka/1200/750',
                'budget_visibility' => 'public',
                'likes_count' => 342,
                'views_count' => 2421,
                'created_at' => Carbon::parse('2026-05-17T08:00:00Z'),
            ]
        );

        $trip->items()->delete();
        $trip->sections()->delete();
        $trip->galleryPhotos()->delete();

        $secs = [];
        foreach (
            [
                'Day 1 — Arrival',
                'Day 2 — USJ',
                'Day 3 — Osaka City',
                'Day 4 — Kyoto',
                'Day 5 — Departure',
            ] as $i => $title
        ) {
            $secs[$i] = $trip->sections()->create(['title' => $title, 'order' => $i]);
        }

        $items = [
            [0, 'Shinkansen tickets', 'round trip Tokyo ↔ Osaka', 'transport', 88000, '08:30', '11:00', 4, 'https://global.jr-central.co.jp/', 'spent', 'extension'],
            [0, 'Hotel Universal Port', '3 nights, family room', 'lodging', 96000, '15:00', '11:00', 1, 'https://www.hoteluniversalport.jp/', 'spent', 'extension'],
            [1, 'Universal Studios', '1-day pass × 4', 'activity', 32000, '09:00', '20:00', 4, 'https://www.usjticketing.com/', 'spent', 'story'],
            [1, 'Subway day passes', null, 'transport', 6000, null, null, 4, null, 'spent', null],
            [2, 'Osaka Castle entry', null, 'activity', 2400, '10:00', '12:30', 4, 'https://www.osakacastle.net/', 'spent', null],
            [2, 'Ichiran Ramen', 'Dotonbori location', 'food', 5200, '13:00', null, null, null, 'spent', 'story'],
            [2, 'Family dinner Kuromon', null, 'food', 22000, '18:00', null, null, null, 'spent', null],
            [2, 'Kani Doraku crab', null, 'food', 18400, '19:30', null, null, null, 'spent', 'story'],
            [2, 'Dotonbori street food crawl', null, 'food', 8000, '21:00', null, null, null, 'spent', 'story'],
            [3, 'Kyoto day trip transport', 'Hankyu railway', 'transport', 4400, '08:00', null, null, null, 'spent', null],
            [3, 'Fushimi Inari visit', null, 'activity', 0, '10:00', '12:00', null, null, 'spent', null],
            [4, 'Don Quijote shopping', null, 'other', 24000, '13:00', null, null, null, 'spent', null],
            [4, 'Airport limousine bus', 'Kansai airport ↔ hotel', 'transport', 15600, '16:00', null, null, 'https://www.kate.co.jp/', 'spent', 'extension'],
            [4, 'Souvenirs & misc', null, null, 18000, null, null, null, null, 'spent', null],
        ];

        foreach ($items as $order => $row) {
            [$secIdx, $title, $note, $cat, $price, $ts, $te, $qty, $url, $status, $source] = $row;
            $trip->items()->create([
                'section_id' => $secs[$secIdx]->id,
                'title' => $title,
                'note' => $note,
                'category' => $cat,
                'price' => $price,
                'time_start' => $ts,
                'time_end' => $te,
                'quantity' => $qty,
                'source_url' => $url,
                'status' => $status,
                'source' => $source,
                'order' => $order,
            ]);
        }

        foreach (['osaka-g1', 'osaka-g2', 'osaka-g3', 'osaka-g4', 'osaka-g5', 'osaka-g6'] as $i => $seed) {
            $trip->galleryPhotos()->create([
                'url' => "https://picsum.photos/seed/{$seed}/600/600",
                'order' => $i,
            ]);
        }
    }

    private function seedHokkaidoUpcoming(User $aiko): void
    {
        $trip = Trip::updateOrCreate(
            ['user_id' => $aiko->id, 'slug' => 'hokkaido-winter-2027'],
            [
                'title' => 'Hokkaido winter — planning',
                'destination' => 'Hokkaido, Japan',
                'currency' => 'JPY',
                'duration_days' => 6,
                'description' => 'Planning a winter trip with the kids. Niseko for skiing, then Sapporo for the snow festival.',
                'cover_url' => 'https://picsum.photos/seed/hokkaido/1200/750',
                'budget_visibility' => 'request',
                'likes_count' => 28,
                'views_count' => 142,
                'created_at' => Carbon::parse('2026-05-18T10:00:00Z'),
            ]
        );

        $trip->items()->delete();
        $trip->sections()->delete();
        $trip->galleryPhotos()->delete();

        $secs = [
            $trip->sections()->create(['title' => 'Day 1 — Tokyo → Sapporo', 'order' => 0]),
            $trip->sections()->create(['title' => 'Day 2-4 — Niseko skiing', 'order' => 1]),
            $trip->sections()->create(['title' => 'Day 5 — Snow Festival', 'order' => 2]),
            $trip->sections()->create(['title' => 'Day 6 — Return', 'order' => 3]),
        ];

        $items = [
            [0, 'Tokyo → Sapporo flight', 'JAL, family of 4', 'transport', 240000, '08:00', '09:50', 4, 'https://www.jal.co.jp/'],
            [1, 'Hilton Niseko Village', '3 nights, family suite', 'lodging', 180000, null, null, null, 'https://www.hilton.com/'],
            [1, 'Ski rental + lift pass', null, 'activity', 64000, '09:00', '16:00', 4, 'https://www.klook.com/'],
            [2, 'Sapporo Snow Festival', null, 'activity', 0, '10:00', '20:00', null, null],
            [2, 'Hotel Mystays Sapporo', null, 'lodging', 48000, null, null, null, 'https://www.mystays.com/'],
            [3, 'Sapporo → Tokyo flight', null, 'transport', 240000, '17:00', '18:50', 4, 'https://www.jal.co.jp/'],
        ];

        foreach ($items as $order => $row) {
            [$secIdx, $title, $note, $cat, $price, $ts, $te, $qty, $url] = $row;
            $trip->items()->create([
                'section_id' => $secs[$secIdx]->id,
                'title' => $title,
                'note' => $note,
                'category' => $cat,
                'price' => $price,
                'time_start' => $ts,
                'time_end' => $te,
                'quantity' => $qty,
                'source_url' => $url,
                'status' => 'planned',
                'source' => 'extension',
                'order' => $order,
            ]);
        }
    }

    private function seedBaliBackpacking(User $rio): void
    {
        $trip = Trip::updateOrCreate(
            ['user_id' => $rio->id, 'slug' => 'bali-backpacking'],
            [
                'title' => 'Backpacking Bali on a shoestring',
                'destination' => 'Bali, Indonesia',
                'currency' => 'IDR',
                'duration_days' => 10,
                'description' => 'Ten days, one backpack, mostly hostels and motorbikes.',
                'cover_url' => 'https://picsum.photos/seed/bali/1200/750',
                'budget_visibility' => 'public',
                'likes_count' => 218,
                'views_count' => 1180,
                'created_at' => Carbon::parse('2026-05-15T08:00:00Z'),
            ]
        );

        $trip->items()->delete();
        $trip->sections()->delete();
        $trip->galleryPhotos()->delete();

        $items = [
            ['Hostel Canggu (4 nights)', null, 'lodging', 800000],
            ['Hostel Ubud (3 nights)', null, 'lodging', 540000],
            ['Beach hut Uluwatu (3 nights)', null, 'lodging', 720000],
            ['Motorbike rental', '10 days', 'transport', 600000],
            ['Fuel', null, 'transport', 280000],
            ['Tegalalang rice terrace', null, 'activity', 50000],
            ['Mt Batur sunrise hike', null, 'activity', 450000],
            ['Warungs & cafes', '~daily', 'food', 760000],
        ];
        foreach ($items as $order => [$t, $n, $c, $p]) {
            $trip->items()->create([
                'title' => $t,
                'note' => $n,
                'category' => $c,
                'price' => $p,
                'status' => 'spent',
                'order' => $order,
            ]);
        }
    }

    private function seedParisHoneymoon(User $luc): void
    {
        $trip = Trip::updateOrCreate(
            ['user_id' => $luc->id, 'slug' => 'paris-honeymoon'],
            [
                'title' => 'Paris honeymoon',
                'destination' => 'Paris, France',
                'currency' => 'EUR',
                'duration_days' => 6,
                'description' => 'A week in Le Marais. Mostly walking, one fancy meal, one boat ride.',
                'cover_url' => 'https://picsum.photos/seed/paris/1200/750',
                'budget_visibility' => 'hidden',
                'likes_count' => 612,
                'views_count' => 4290,
                'created_at' => Carbon::parse('2026-05-05T08:00:00Z'),
            ]
        );

        $trip->items()->delete();
        $trip->sections()->delete();
        $trip->galleryPhotos()->delete();

        $items = [
            ['Boutique hotel Le Marais (6 nights)', null, 'lodging', 1620],
            ['Eurostar London ↔ Paris', null, 'transport', 320],
            ['Metro carnet × 2', null, 'transport', 32],
            ['Louvre tickets', null, 'activity', 44],
            ['Bistro dinners', null, 'food', 580],
            ['Le Jules Verne dinner', null, 'food', 460],
            ['Seine boat cruise', null, 'activity', 38],
            ['Wine & cheese tasting', null, 'food', 120],
            ['Boutique shopping', null, 'other', 240],
        ];
        foreach ($items as $order => [$t, $n, $c, $p]) {
            $trip->items()->create([
                'title' => $t,
                'note' => $n,
                'category' => $c,
                'price' => $p,
                'status' => 'spent',
                'order' => $order,
            ]);
        }
    }
}
