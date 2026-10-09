<?php

namespace Tests\Feature\Listings;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/listings/{id}/similar — ranking per category.
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class SimilarListingsTest extends TestCase
{
    use DatabaseTransactions;

    private int $seller;
    private int $country;
    private int $city;
    private int $otherCity;
    private int $minutes = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['email' => Str::uuid() . '@similar-test.local'])->id;
        $this->city = (int) DB::table('cities')->value('id');
        $this->country = (int) DB::table('cities')->where('id', $this->city)->value('country_id');
        $this->otherCity = (int) DB::table('cities')->where('id', '!=', $this->city)->value('id');
    }

    /** Newer listings are created later, so "newest first" can't explain the order. */
    private function listing(int $category, string $title, array $extra = []): int
    {
        return DB::table('listings')->insertGetId(array_merge([
            'title' => $title,
            'description' => 'similar listings test',
            'price' => 1000,
            'seller_id' => $this->seller,
            'category_id' => $category,
            'country_id' => $this->country,
            'city_id' => $this->city,
            'status' => 'published',
            'created_at' => now()->addMinutes(++$this->minutes),
            'updated_at' => now(),
        ], $extra));
    }

    private function part(string $title, int $partCategory, int $partBrand, array $fits = []): int
    {
        $listing = $this->listing(2, $title);
        $part = DB::table('spare_parts')->insertGetId([
            'listing_id' => $listing,
            'condition' => 'used',
            'bike_part_brand_id' => $partBrand,
            'bike_part_category_id' => $partCategory,
        ]);
        foreach ($fits as [$brand, $model, $year]) {
            DB::table('spare_part_motorcycles')->insert([
                'spare_part_id' => $part, 'brand_id' => $brand, 'model_id' => $model, 'year_id' => $year,
            ]);
        }
        return $listing;
    }

    private function plate(string $title, int $city, ?int $format): int
    {
        $listing = $this->listing(3, $title, ['city_id' => $city]);
        DB::table('license_plates')->insert([
            'listing_id' => $listing, 'country_id' => $this->country, 'city_id' => $city, 'plate_format_id' => $format,
        ]);
        return $listing;
    }

    private function similarTitles(int $id, int $limit = 12): array
    {
        $res = $this->getJson("/api/listings/{$id}/similar?limit={$limit}")->assertOk();
        // Only this test's listings (the dev database may hold others).
        return collect($res->json('listings'))->pluck('title')->filter(fn ($t) => str_starts_with($t, 'T-'))->values()->all();
    }

    public function test_spare_parts_rank_by_part_category_then_fitting_model_then_brand(): void
    {
        [$cat, $otherCat] = DB::table('bike_part_categories')->limit(2)->pluck('id')->all();
        [$brand, $otherBrand] = DB::table('bike_part_brands')->limit(2)->pluck('id')->all();
        $model = DB::table('motorcycle_models')->first(['id', 'brand_id']);
        $otherModel = DB::table('motorcycle_models')->where('id', '!=', $model->id)->first(['id', 'brand_id']);
        $year = (int) DB::table('motorcycle_years')->value('id');

        $source = $this->part('T-source', $cat, $brand, [[$model->brand_id, $model->id, $year]]);
        // Created oldest → newest: the expected order is the reverse of "newest first".
        $this->part('T-same-cat-same-model', $cat, $otherBrand, [[$model->brand_id, $model->id, $year]]);
        $this->part('T-same-cat-other-model', $cat, $brand, [[$otherModel->brand_id, $otherModel->id, $year]]);
        $this->part('T-other-cat', $otherCat, $brand, [[$model->brand_id, $model->id, $year]]);

        $titles = $this->similarTitles($source);

        $this->assertSame('T-same-cat-same-model', $titles[0]);
        $this->assertSame('T-same-cat-other-model', $titles[1]);
        $this->assertSame('T-other-cat', $titles[2]);
        $this->assertNotContains('T-source', $titles);
    }

    public function test_plates_rank_by_city_then_format(): void
    {
        [$format, $otherFormat] = DB::table('plate_formats')->limit(2)->pluck('id')->all();

        $source = $this->plate('T-source', $this->city, $format);
        $this->plate('T-same-city-same-format', $this->city, $format);
        $this->plate('T-same-city-other-format', $this->city, $otherFormat);
        $this->plate('T-other-city-same-format', $this->otherCity, $format);

        $titles = $this->similarTitles($source);

        $this->assertSame(['T-same-city-same-format', 'T-same-city-other-format', 'T-other-city-same-format'], array_slice($titles, 0, 3));
    }

    public function test_unpublished_listings_are_left_out(): void
    {
        [$format] = DB::table('plate_formats')->limit(1)->pluck('id')->all();
        $source = $this->plate('T-source', $this->city, $format);
        $draft = $this->plate('T-draft', $this->city, $format);
        DB::table('listings')->where('id', $draft)->update(['status' => 'draft']);

        $this->assertNotContains('T-draft', $this->similarTitles($source));
    }

    public function test_unknown_listing_is_404(): void
    {
        $this->getJson('/api/listings/999999999/similar')->assertNotFound();
    }
}
