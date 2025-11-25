<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\GuideSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Guides",
 *     description="API Endpoints for guide management"
 * )
 */
class GuideController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/guides",
     *     summary="List all guides with filters (excludes starter guides)",
     *     tags={"Guides"},
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer"), description="Filter by category ID"),
     *     @OA\Parameter(name="tag", in="query", @OA\Schema(type="string"), description="Filter by tag slug"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Search in title and excerpt"),
     *     @OA\Parameter(name="is_featured", in="query", @OA\Schema(type="string"), description="Filter featured guides (1 or 0)"),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"latest", "popular", "views"}), description="Sort order"),
     *     @OA\Response(response=200, description="List of guides")
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Guide::with(['author', 'category', 'tags', 'sections'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            });

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_featured') && $request->is_featured == '1') {
            $query->where('is_featured', true);
        }

        switch ($request->get('sort', 'latest')) {
            case 'popular':
            case 'views':
                $query->orderBy('views_count', 'desc');
                break;
            default:
                $query->orderBy('published_at', 'desc');
        }

        $guides = $query->get()->map(function ($guide) use ($user) {
            return $this->formatGuideList($guide, $user);
        });

        return response()->json($guides);
    }

    /**
     * @OA\Post(
     *     path="/api/guides",
     *     summary="Create a new guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title", type="string", example="Complete Motorcycle Maintenance Guide"),
     *             @OA\Property(property="excerpt", type="string", example="Learn how to maintain your motorcycle properly"),
     *             @OA\Property(property="featured_image", type="string", example="https://example.com/maintenance-cover.jpg"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
     *             @OA\Property(property="is_featured", type="boolean", example=false),
     *             @OA\Property(
     *                 property="sections",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="type", type="string", enum={"text", "image", "text_image", "gallery", "video"}),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="image_url", type="string"),
     *                     @OA\Property(property="image_position", type="string", enum={"top", "right", "left", "bottom"}),
     *                     @OA\Property(property="media", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="order_position", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Guide created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:guide_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:guide_tags,id',
            'is_featured' => 'nullable|boolean',
            'sections' => 'nullable|array',
            'sections.*.type' => 'required|in:text,image,text_image,gallery,video',
            'sections.*.title' => 'nullable|string|max:255',
            'sections.*.description' => 'nullable|string',
            'sections.*.image_url' => 'nullable|string|max:255',
            'sections.*.image_position' => 'nullable|in:top,right,left,bottom',
            'sections.*.media' => 'nullable|array',
            'sections.*.order_position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $guide = Guide::create([
                'title' => $request->title,
                'content' => '',
                'excerpt' => $request->excerpt,
                'featured_image' => $request->featured_image,
                'category_id' => $request->category_id,
                'author_id' => Auth::id(),
                'status' => 'draft',
                'is_featured' => $request->is_featured ?? false,
            ]);

            if ($request->has('tags')) {
                $guide->tags()->sync($request->tags);
            }

            if ($request->has('sections') && is_array($request->sections)) {
                foreach ($request->sections as $index => $sectionData) {
                    GuideSection::create([
                        'guide_id' => $guide->id,
                        'type' => $sectionData['type'],
                        'title' => $sectionData['title'] ?? null,
                        'description' => $sectionData['description'] ?? null,
                        'image_url' => $sectionData['image_url'] ?? null,
                        'image_position' => $sectionData['image_position'] ?? 'top',
                        'media' => $sectionData['media'] ?? null,
                        'order_position' => $sectionData['order_position'] ?? $index,
                    ]);
                }
            }

            DB::commit();
            $guide->load(['author', 'category', 'tags', 'sections']);

            return response()->json([
                'message' => 'Guide created successfully',
                'data' => $this->formatGuideResponse($guide)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error creating guide', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/guides/{slug}",
     *     summary="Show guide details by slug",
     *     tags={"Guides"},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Guide details"),
     *     @OA\Response(response=404, description="Guide not found")
     * )
     */
    public function show($slug)
    {
        $user = Auth::user();
        $guide = Guide::with(['author', 'category', 'tags', 'sections'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            })
            ->first();

        if (!$guide) {
            return response()->json(['message' => 'Guide not found'], 404);
        }

        $guide->increment('views_count');
        return response()->json($this->formatGuideDetail($guide, $user));
    }

    /**
     * @OA\Put(
     *     path="/api/guides/{id}",
     *     summary="Update a guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="excerpt", type="string"),
     *             @OA\Property(property="featured_image", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="is_featured", type="boolean"),
     *             @OA\Property(
     *                 property="sections",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", description="Section ID for update, omit for new section"),
     *                     @OA\Property(property="type", type="string", enum={"text", "image", "text_image", "gallery", "video"}),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="image_url", type="string"),
     *                     @OA\Property(property="image_position", type="string", enum={"top", "right", "left", "bottom"}),
     *                     @OA\Property(property="media", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="order_position", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Guide updated successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Guide not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $guide = Guide::find($id);
        if (!$guide) {
            return response()->json(['message' => 'Guide not found'], 404);
        }

        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:guide_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:guide_tags,id',
            'is_featured' => 'nullable|boolean',
            'sections' => 'nullable|array',
            'sections.*.id' => 'nullable|exists:guide_sections,id',
            'sections.*.type' => 'required|in:text,image,text_image,gallery,video',
            'sections.*.title' => 'nullable|string|max:255',
            'sections.*.description' => 'nullable|string',
            'sections.*.image_url' => 'nullable|string|max:255',
            'sections.*.image_position' => 'nullable|in:top,right,left,bottom',
            'sections.*.media' => 'nullable|array',
            'sections.*.order_position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $guide->update($request->only(['title', 'excerpt', 'featured_image', 'category_id', 'is_featured']));

            if ($request->has('tags')) {
                $guide->tags()->sync($request->tags);
            }

            if ($request->has('sections')) {
                $sectionIdsToKeep = collect($request->sections)->pluck('id')->filter()->toArray();
                $guide->sections()->whereNotIn('id', $sectionIdsToKeep)->delete();

                foreach ($request->sections as $index => $sectionData) {
                    if (isset($sectionData['id'])) {
                        GuideSection::where('id', $sectionData['id'])
                            ->where('guide_id', $guide->id)
                            ->update([
                                'type' => $sectionData['type'],
                                'title' => $sectionData['title'] ?? null,
                                'description' => $sectionData['description'] ?? null,
                                'image_url' => $sectionData['image_url'] ?? null,
                                'image_position' => $sectionData['image_position'] ?? 'top',
                                'media' => $sectionData['media'] ?? null,
                                'order_position' => $sectionData['order_position'] ?? $index,
                            ]);
                    } else {
                        GuideSection::create([
                            'guide_id' => $guide->id,
                            'type' => $sectionData['type'],
                            'title' => $sectionData['title'] ?? null,
                            'description' => $sectionData['description'] ?? null,
                            'image_url' => $sectionData['image_url'] ?? null,
                            'image_position' => $sectionData['image_position'] ?? 'top',
                            'media' => $sectionData['media'] ?? null,
                            'order_position' => $sectionData['order_position'] ?? $index,
                        ]);
                    }
                }
            }

            DB::commit();
            $guide->load(['sections', 'tags', 'category']);

            return response()->json([
                'message' => 'Guide updated successfully',
                'data' => $this->formatGuideResponse($guide)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating guide', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/{id}",
     *     summary="Delete a guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guide deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Guide not found")
     * )
     */
    public function destroy($id)
    {
        $guide = Guide::find($id);
        if (!$guide) {
            return response()->json(['message' => 'Guide not found'], 404);
        }

        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $guide->delete();
        return response()->json(['message' => 'Guide deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/featured",
     *     summary="Get featured guides (excludes starter guides)",
     *     tags={"Guides"},
     *     @OA\Response(response=200, description="Featured guides")
     * )
     */
    public function featured()
    {
        $guides = Guide::with(['author', 'category', 'tags'])
            ->where('status', 'published')
            ->where('is_featured', true)
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            })
            ->orderBy('published_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'excerpt' => $guide->excerpt,
                    'featured_image' => $guide->featured_image,
                    'views_count' => $guide->views_count,
                    'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                    'author' => ['name' => $guide->author->first_name . ' ' . $guide->author->last_name],
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/popular",
     *     summary="Get popular guides (excludes starter guides)",
     *     tags={"Guides"},
     *     @OA\Response(response=200, description="Popular guides")
     * )
     */
    public function popular()
    {
        $guides = Guide::with(['author', 'category'])
            ->where('status', 'published')
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            })
            ->orderBy('views_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'excerpt' => $guide->excerpt,
                    'featured_image' => $guide->featured_image,
                    'views_count' => $guide->views_count,
                    'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/latest",
     *     summary="Get latest guides (excludes starter guides)",
     *     tags={"Guides"},
     *     @OA\Response(response=200, description="Latest guides")
     * )
     */
    public function latest()
    {
        $guides = Guide::with(['author', 'category'])
            ->where('status', 'published')
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            })
            ->orderBy('published_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'excerpt' => $guide->excerpt,
                    'featured_image' => $guide->featured_image,
                    'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{id}/publish",
     *     summary="Publish a guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guide published successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Guide not found")
     * )
     */
    public function publish($id)
    {
        $guide = Guide::find($id);
        if (!$guide) {
            return response()->json(['message' => 'Guide not found'], 404);
        }

        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $guide->update(['status' => 'published', 'published_at' => now()]);

        return response()->json([
            'message' => 'Guide published successfully',
            'data' => [
                'id' => $guide->id,
                'status' => $guide->status,
                'published_at' => $guide->published_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{id}/archive",
     *     summary="Archive a guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guide archived successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Guide not found")
     * )
     */
    public function archive($id)
    {
        $guide = Guide::find($id);
        if (!$guide) {
            return response()->json(['message' => 'Guide not found'], 404);
        }

        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $guide->update(['status' => 'archived']);
        return response()->json(['message' => 'Guide archived successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/my/guides",
     *     summary="Get my guides",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="My guides")
     * )
     */
    public function myGuides()
    {
        $guides = Guide::with(['category', 'tags'])
            ->where('author_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'status' => $guide->status,
                    'views_count' => $guide->views_count,
                    'likes_count' => $guide->likes()->count(),
                    'comments_count' => $guide->allComments()->count(),
                    'created_at' => $guide->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/id/{id}",
     *     summary="Show guide details by ID",
     *     tags={"Guides"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guide details"),
     *     @OA\Response(response=404, description="Guide not found")
     * )
     */
    public function showById($id)
    {
        $user = Auth::user();
        $guide = Guide::with(['author', 'category', 'tags', 'sections'])
            ->where('id', $id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            })
            ->first();

        if (!$guide) {
            return response()->json(['message' => 'Guide not found'], 404);
        }

        $guide->increment('views_count');
        return response()->json($this->formatGuideDetail($guide, $user));
    }

    /**
     * @OA\Get(
     *     path="/api/guides/category/{category_id}",
     *     summary="Get guides by category (excludes starter guides)",
     *     tags={"Guides"},
     *     @OA\Parameter(name="category_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"latest", "popular"}), description="Sort order"),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10), description="Number of results"),
     *     @OA\Response(response=200, description="Guides by category")
     * )
     */
    public function getByCategory($category_id, Request $request)
    {
        $user = Auth::user();
        $query = Guide::with(['author', 'category', 'tags', 'sections'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('category_id', $category_id)
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            });

        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        $limit = $request->get('limit', 10);
        $query->limit($limit);

        $guides = $query->get()->map(function ($guide) use ($user) {
            return $this->formatGuideList($guide, $user);
        });

        return response()->json([
            'category_id' => (int) $category_id,
            'total' => $guides->count(),
            'guides' => $guides
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tag/{tag_slug}",
     *     summary="Get guides by tag slug (excludes starter guides)",
     *     tags={"Guides"},
     *     @OA\Parameter(name="tag_slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"latest", "popular"})),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(response=200, description="Guides by tag")
     * )
     */
    public function getByTag($tag_slug, Request $request)
    {
        $user = Auth::user();
        $query = Guide::with(['author', 'category', 'tags', 'sections'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereHas('tags', function ($q) use ($tag_slug) {
                $q->where('slug', $tag_slug);
            })
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            });

        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        $limit = $request->get('limit', 10);
        $query->limit($limit);

        $guides = $query->get()->map(function ($guide) use ($user) {
            return $this->formatGuideList($guide, $user);
        });

        return response()->json(['tag_slug' => $tag_slug, 'total' => $guides->count(), 'guides' => $guides]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tag/id/{tag_id}",
     *     summary="Get guides by tag ID (excludes starter guides)",
     *     tags={"Guides"},
     *     @OA\Parameter(name="tag_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"latest", "popular"})),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(response=200, description="Guides by tag ID")
     * )
     */
    public function getByTagId($tag_id, Request $request)
    {
        $user = Auth::user();
        $query = Guide::with(['author', 'category', 'tags', 'sections'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereHas('tags', function ($q) use ($tag_id) {
                $q->where('guide_tags.id', $tag_id);
            })
            ->whereDoesntHave('category', function ($q) {
                $q->where('slug', 'guide-starter');
            });

        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        $limit = $request->get('limit', 10);
        $query->limit($limit);

        $guides = $query->get()->map(function ($guide) use ($user) {
            return $this->formatGuideList($guide, $user);
        });

        return response()->json(['tag_id' => (int) $tag_id, 'total' => $guides->count(), 'guides' => $guides]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/starter",
     *     summary="Get starter guides only",
     *     description="Retrieve all guides from the 'Guide starter' category",
     *     tags={"Guides"},
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"latest", "popular"}), description="Sort order"),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20), description="Number of results"),
     *     @OA\Response(
     *         response=200,
     *         description="Starter guides",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer", example=8),
     *             @OA\Property(property="guides", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function starter(Request $request)
    {
        $user = Auth::user();
        $query = Guide::with(['author', 'category', 'tags', 'sections'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereHas('category', function ($q) {
                $q->where('slug', 'guide-starter');
            });

        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        $limit = $request->get('limit', 20);
        $query->limit($limit);

        $guides = $query->get()->map(function ($guide) use ($user) {
            return $this->formatGuideList($guide, $user);
        });

        return response()->json([
            'total' => $guides->count(),
            'guides' => $guides
        ]);
    }

    // Helper methods
    private function formatGuideList($guide, $user)
    {
        $isLiked = false;
        $isBookmarked = false;

        if ($user) {
            $isLiked = DB::table('guide_likes')->where('user_id', $user->id)->where('guide_id', $guide->id)->exists();
            $isBookmarked = DB::table('guide_bookmarks')->where('user_id', $user->id)->where('guide_id', $guide->id)->exists();
        }

        return [
            'id' => $guide->id,
            'title' => $guide->title,
            'slug' => $guide->slug,
            'excerpt' => $guide->excerpt,
            'featured_image' => $guide->featured_image,
            'views_count' => $guide->views_count,
            'likes_count' => $guide->likes()->count(),
            'comments_count' => $guide->allComments()->count(),
            'is_featured' => $guide->is_featured,
            'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
            'author' => [
                'id' => $guide->author->id,
                'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                'profile_picture' => $guide->author->profile_picture,
            ],
            'category' => $guide->category ? [
                'id' => $guide->category->id,
                'name' => $guide->category->name,
                'slug' => $guide->category->slug,
            ] : null,
            'tags' => $guide->tags->map(function ($tag) {
                return ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug];
            }),
            'liked' => $isLiked,
            'bookmarked' => $isBookmarked,
        ];
    }

    private function formatGuideDetail($guide, $user)
    {
        $isLiked = false;
        $isBookmarked = false;

        if ($user) {
            $isLiked = DB::table('guide_likes')->where('user_id', $user->id)->where('guide_id', $guide->id)->exists();
            $isBookmarked = DB::table('guide_bookmarks')->where('user_id', $user->id)->where('guide_id', $guide->id)->exists();
        }

        return [
            'id' => $guide->id,
            'title' => $guide->title,
            'slug' => $guide->slug,
            'excerpt' => $guide->excerpt,
            'featured_image' => $guide->featured_image,
            'views_count' => $guide->views_count,
            'likes_count' => $guide->likes()->count(),
            'comments_count' => $guide->allComments()->count(),
            'is_featured' => $guide->is_featured,
            'published_at' => $guide->published_at->format('Y-m-d H:i:s'),
            'author' => [
                'id' => $guide->author->id,
                'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                'email' => $guide->author->email,
                'profile_picture' => $guide->author->profile_picture,
            ],
            'category' => $guide->category ? [
                'id' => $guide->category->id,
                'name' => $guide->category->name,
                'slug' => $guide->category->slug,
            ] : null,
            'tags' => $guide->tags->map(function ($tag) {
                return ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug];
            }),
            'sections' => $guide->sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'type' => $section->type,
                    'title' => $section->title,
                    'description' => $section->description,
                    'image_url' => $section->image_url,
                    'image_position' => $section->image_position,
                    'media' => $section->media,
                    'order_position' => $section->order_position,
                ];
            }),
            'liked' => $isLiked,
            'bookmarked' => $isBookmarked,
        ];
    }

    private function formatGuideResponse($guide)
    {
        return [
            'id' => $guide->id,
            'title' => $guide->title,
            'slug' => $guide->slug,
            'excerpt' => $guide->excerpt,
            'featured_image' => $guide->featured_image,
            'status' => $guide->status,
            'is_featured' => $guide->is_featured,
            'category' => $guide->category ? [
                'id' => $guide->category->id,
                'name' => $guide->category->name,
                'slug' => $guide->category->slug,
            ] : null,
            'tags' => $guide->tags->map(function ($tag) {
                return ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug];
            }),
            'sections' => $guide->sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'type' => $section->type,
                    'title' => $section->title,
                    'description' => $section->description,
                    'image_url' => $section->image_url,
                    'image_position' => $section->image_position,
                    'media' => $section->media,
                    'order_position' => $section->order_position,
                ];
            }),
            'created_at' => $guide->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $guide->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
