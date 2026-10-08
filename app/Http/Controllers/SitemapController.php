<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\Listing;
use App\Models\Trainer;
use App\Models\TrainerCourse;
use Illuminate\Support\Facades\Cache;

/**
 * Public URLs for the website's XML sitemaps (built by the Angular SSR server in
 * src/server.ts): published listings (with up to 5 photos each), approved trainers,
 * their published courses and published guides — each with its last-modified date.
 * Cached for an hour; the SSR server caches its XML too.
 */
class SitemapController extends Controller
{
    /** Google's limit is 50,000 URLs per sitemap file. */
    private const MAX_LISTINGS = 45000;

    /**
     * @OA\Get(
     *     path="/api/sitemap",
     *     summary="Public URLs (listings, trainers, courses, guides) for the XML sitemap",
     *     tags={"SEO"},
     *     @OA\Response(response=200, description="Sitemap data")
     * )
     */
    public function index()
    {
        $data = Cache::remember('sitemap.v1', 3600, function () {
            $listings = Listing::query()
                ->where('status', 'published')
                ->select('id', 'title', 'category_id', 'updated_at', 'created_at')
                ->with(['images' => fn ($q) => $q->select('listing_id', 'image_url')->orderBy('id')])
                ->orderByDesc('updated_at')
                ->limit(self::MAX_LISTINGS)
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'category_id' => $l->category_id,
                    'updated_at' => ($l->updated_at ?? $l->created_at)?->toAtomString(),
                    'images' => $l->images->pluck('image_url')->filter()->take(5)->values(),
                ]);

            $trainers = Trainer::query()
                ->approved()
                ->select('id', 'updated_at')
                ->get()
                ->map(fn ($t) => ['id' => $t->id, 'updated_at' => $t->updated_at?->toAtomString()]);

            $courses = TrainerCourse::query()
                ->published()
                ->whereHas('trainer', fn ($q) => $q->approved())
                ->select('id', 'trainer_id', 'updated_at')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'trainer_id' => $c->trainer_id,
                    'updated_at' => $c->updated_at?->toAtomString(),
                ]);

            $guides = Guide::query()
                ->where('status', 'published')
                ->select('id', 'updated_at')
                ->get()
                ->map(fn ($g) => ['id' => $g->id, 'updated_at' => $g->updated_at?->toAtomString()]);

            return compact('listings', 'trainers', 'courses', 'guides');
        });

        return response()->json($data);
    }
}
