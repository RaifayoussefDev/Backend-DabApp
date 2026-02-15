<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use App\Models\GuideCategory;
use App\Models\GuideTag;
use App\Models\GuideSection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Guides",
 *     description="API Endpoints pour l'administration des guides"
 * )
 */
class GuideAdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/guides",
     *     summary="Liste tous les guides avec filtres avancés (Admin)",
     *     description="Récupère la liste de tous les guides avec possibilité de filtrer, trier et paginer. Si per_page est vide, retourne tous les résultats sans pagination.",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page pour la pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page. Laisser vide pour retourner TOUS les résultats sans pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrer par statut du guide",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "published", "archived"}, example="published")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filtrer par ID de catégorie",
     *         required=false,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="author_id",
     *         in="query",
     *         description="Filtrer par ID de l'auteur",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche dans le titre, l'excerpt et le contenu des guides",
     *         required=false,
     *         @OA\Schema(type="string", example="maintenance")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Champ de tri",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "updated_at", "title", "views_count", "likes_count", "comments_count"}, default="created_at", example="views_count")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Ordre de tri",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc", example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des guides récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Ultimate Guide to Motorcycle Maintenance"),
     *                     @OA\Property(property="title_ar", type="string", example="الدليل الشامل لصيانة الدراجات النارية"),
     *                     @OA\Property(property="slug", type="string", example="ultimate-guide-motorcycle-maintenance"),
     *                     @OA\Property(property="excerpt", type="string", example="Everything you need to know about keeping your bike in top condition."),
     *                     @OA\Property(property="excerpt_ar", type="string", example="كل ما تحتاج معرفته للحفاظ على دراجتك في أفضل حالة."),
     *                     @OA\Property(property="featured_image", type="string", example="https://example.com/images/guide-maintenance.jpg"),
     *                     @OA\Property(property="status", type="string", example="published"),
     *                     @OA\Property(property="views_count", type="integer", example=1234),
     *                     @OA\Property(property="likes_count", type="integer", example=45),
     *                     @OA\Property(property="comments_count", type="integer", example=12),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Maintenance"),
     *                         @OA\Property(property="slug", type="string", example="maintenance")
     *                     ),
     *                     @OA\Property(
     *                         property="author",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="name", type="string", example="Ahmed Hassan"),
     *                         @OA\Property(property="email", type="string", example="ahmed@example.com"),
     *                         @OA\Property(property="profile_picture", type="string", example="https://example.com/profiles/ahmed.jpg")
     *                     ),
     *                     @OA\Property(
     *                         property="tags",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="DIY"),
     *                             @OA\Property(property="slug", type="string", example="diy")
     *                         )
     *                     ),
     *                     @OA\Property(property="meta_title", type="string", example="Motorcycle Maintenance Guide - DabApp"),
     *                     @OA\Property(property="meta_description", type="string", example="Learn how to maintain your motorcycle like a pro."),
     *                     @OA\Property(property="meta_keywords", type="string", example="maintenance, motorcycle, tips, guide"),
     *                     @OA\Property(property="created_at", type="string", example="2025-01-15 10:30:00"),
     *                     @OA\Property(property="updated_at", type="string", example="2025-02-01 14:20:00"),
     *                     @OA\Property(property="published_at", type="string", example="2025-01-16 09:00:00")
     *                 )
     *             ),
     *             @OA\Property(property="current_page", type="integer", example=1, description="Current page number"),
     *             @OA\Property(property="last_page", type="integer", example=10, description="Last page number"),
     *             @OA\Property(property="per_page", type="integer", example=20, description="Items per page"),
     *             @OA\Property(property="total", type="integer", example=195, description="Total items count"),
     *             @OA\Property(property="from", type="integer", example=1, description="First item index"),
     *             @OA\Property(property="to", type="integer", example=20, description="Last item index")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Vérifier que l'utilisateur est admin (role_id = 1)
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $query = Guide::with(['author', 'category', 'tags'])
            ->withCount(['comments', 'likes']);

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('author_id')) {
            $query->where('author_id', $request->author_id);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('excerpt_ar', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('content_ar', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['likes_count', 'comments_count'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination ou tout
        $perPage = $request->get('per_page');

        if (empty($perPage)) {
            // Retourner tous les résultats
            $guides = $query->get()->map(function ($guide) {
                return $this->formatGuideForAdmin($guide);
            });

            return response()->json([
                'data' => $guides,
                'total' => $guides->count()
            ]);
        } else {
            // Pagination
            $guides = $query->paginate($perPage);

            return response()->json([
                'data' => $guides->getCollection()->map(function ($guide) {
                    return $this->formatGuideForAdmin($guide);
                }),
                'current_page' => $guides->currentPage(),
                'last_page' => $guides->lastPage(),
                'per_page' => $guides->perPage(),
                'total' => $guides->total(),
                'from' => $guides->firstItem(),
                'to' => $guides->lastItem()
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/guides/stats",
     *     summary="Statistiques globales des guides (Admin)",
     *     description="Retourne des statistiques complètes sur tous les guides : compteurs, top auteurs, catégories populaires, guides les plus consultés, etc.",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques récupérées avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_guides", type="integer", example=150, description="Nombre total de guides"),
     *             @OA\Property(property="published_guides", type="integer", example=120, description="Guides publiés"),
     *             @OA\Property(property="draft_guides", type="integer", example=25, description="Guides en brouillon"),
     *             @OA\Property(property="archived_guides", type="integer", example=5, description="Guides archivés"),
     *             @OA\Property(property="total_views", type="integer", example=45678, description="Total de vues"),
     *             @OA\Property(property="total_likes", type="integer", example=3456, description="Total de likes"),
     *             @OA\Property(property="total_comments", type="integer", example=890, description="Total de commentaires"),
     *             @OA\Property(property="total_categories", type="integer", example=12, description="Nombre de catégories"),
     *             @OA\Property(property="total_tags", type="integer", example=45, description="Nombre de tags"),
     *             @OA\Property(property="guides_this_month", type="integer", example=15, description="Guides créés ce mois"),
     *             @OA\Property(property="guides_today", type="integer", example=2, description="Guides créés aujourd'hui"),
     *             @OA\Property(property="avg_views_per_guide", type="number", format="float", example=304.52, description="Moyenne de vues par guide"),
     *             @OA\Property(
     *                 property="top_authors",
     *                 type="array",
     *                 description="Top 5 des auteurs les plus productifs",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="guides_count", type="integer", example=25)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="top_categories",
     *                 type="array",
     *                 description="Top 5 des catégories les plus utilisées",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Maintenance"),
     *                     @OA\Property(property="guides_count", type="integer", example=45)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="most_viewed_guides",
     *                 type="array",
     *                 description="Top 5 des guides les plus consultés",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="title", type="string", example="Guide complet de maintenance"),
     *                     @OA\Property(property="slug", type="string", example="guide-complet-maintenance"),
     *                     @OA\Property(property="views_count", type="integer", example=2345)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="most_liked_guides",
     *                 type="array",
     *                 description="Top 5 des guides les plus likés",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="title", type="string", example="Entretien moteur"),
     *                     @OA\Property(property="slug", type="string", example="entretien-moteur"),
     *                     @OA\Property(property="likes_count", type="integer", example=156)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="recent_guides",
     *                 type="array",
     *                 description="5 guides les plus récents",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=150),
     *                     @OA\Property(property="title", type="string", example="Nouveau guide"),
     *                     @OA\Property(property="slug", type="string", example="nouveau-guide"),
     *                     @OA\Property(property="status", type="string", example="draft"),
     *                     @OA\Property(property="author_name", type="string", example="Jane Smith"),
     *                     @OA\Property(property="created_at", type="string", example="2025-02-07 10:30:00")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="status_distribution",
     *                 type="object",
     *                 description="Distribution des guides par statut",
     *                 @OA\Property(property="draft", type="integer", example=25),
     *                 @OA\Property(property="published", type="integer", example=120),
     *                 @OA\Property(property="archived", type="integer", example=5)
     *             ),
     *             @OA\Property(
     *                 property="guides_by_month",
     *                 type="array",
     *                 description="Nombre de guides créés par mois (12 derniers mois)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="year", type="integer", example=2025),
     *                     @OA\Property(property="month", type="integer", example=2),
     *                     @OA\Property(property="count", type="integer", example=15)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function stats()
    {
        // Vérifier que l'utilisateur est admin
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $stats = [
            'total_guides' => Guide::count(),
            'published_guides' => Guide::where('status', 'published')->count(),
            'draft_guides' => Guide::where('status', 'draft')->count(),
            'archived_guides' => Guide::where('status', 'archived')->count(),

            'total_views' => Guide::sum('views_count'),
            'total_likes' => DB::table('guide_likes')->count(),
            'total_comments' => DB::table('guide_comments')->count(),

            'total_categories' => GuideCategory::count(),
            'total_tags' => GuideTag::count(),

            'guides_this_month' => Guide::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),

            'guides_today' => Guide::whereDate('created_at', today())->count(),

            'avg_views_per_guide' => round(Guide::avg('views_count'), 2),

            'top_authors' => User::select('users.id', DB::raw('CONCAT(users.first_name, " ", users.last_name) as name'))
                ->join('guides', 'users.id', '=', 'guides.author_id')
                ->groupBy('users.id', 'users.first_name', 'users.last_name')
                ->orderByRaw('COUNT(guides.id) DESC')
                ->limit(5)
                ->get()
                ->map(function ($author) {
                    $author->guides_count = Guide::where('author_id', $author->id)->count();
                    return $author;
                }),

            'top_categories' => GuideCategory::withCount('guides')
                ->orderBy('guides_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'guides_count' => $category->guides_count
                    ];
                }),

            'most_viewed_guides' => Guide::select('id', 'title', 'slug', 'views_count')
                ->orderBy('views_count', 'desc')
                ->limit(5)
                ->get(),

            'most_liked_guides' => Guide::withCount('likes')
                ->orderBy('likes_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($guide) {
                    return [
                        'id' => $guide->id,
                        'title' => $guide->title,
                        'slug' => $guide->slug,
                        'likes_count' => $guide->likes_count
                    ];
                }),

            'recent_guides' => Guide::with('author')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($guide) {
                    return [
                        'id' => $guide->id,
                        'title' => $guide->title,
                        'slug' => $guide->slug,
                        'status' => $guide->status,
                        'author_name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                        'created_at' => $guide->created_at->format('Y-m-d H:i:s')
                    ];
                }),

            'status_distribution' => [
                'draft' => Guide::where('status', 'draft')->count(),
                'published' => Guide::where('status', 'published')->count(),
                'archived' => Guide::where('status', 'archived')->count(),
            ],

            'guides_by_month' => DB::table('guides')
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get()
        ];

        return response()->json($stats);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/guides/{id}",
     *     summary="Détails complets d'un guide (Admin)",
     *     description="Récupère tous les détails d'un guide incluant le contenu complet, les sections, images et commentaires récents",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du guide récupérés avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Ultimate Guide to Motorcycle Maintenance"),
     *                 @OA\Property(property="title_ar", type="string", example="الدليل الشامل لصيانة الدراجات النارية"),
     *                 @OA\Property(property="slug", type="string", example="ultimate-guide-motorcycle-maintenance"),
     *                 @OA\Property(property="excerpt", type="string", example="Everything you need to know about keeping your bike in top condition."),
     *                 @OA\Property(property="excerpt_ar", type="string", example="كل ما تحتاج معرفته للحفاظ على دراجتك في أفضل حالة."),
     *                 @OA\Property(property="content", type="string", example="<p>Regular oil changes are crucial for engine longevity...</p>"),
     *                 @OA\Property(property="content_ar", type="string", example="<p>تغيير الزيت بانتظام أمر بالغ الأهمية لطول عمر المحرك...</p>"),
     *                 @OA\Property(property="featured_image", type="string", example="https://example.com/images/guide-maintenance.jpg"),
     *                 @OA\Property(property="status", type="string", example="published"),
     *                 @OA\Property(property="views_count", type="integer", example=1234),
     *                 @OA\Property(property="likes_count", type="integer", example=45),
     *                 @OA\Property(property="comments_count", type="integer", example=12),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Maintenance"),
     *                     @OA\Property(property="slug", type="string", example="maintenance")
     *                 ),
     *                 @OA\Property(
     *                     property="author",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Ahmed Hassan"),
     *                     @OA\Property(property="email", type="string", example="ahmed@example.com"),
     *                     @OA\Property(property="profile_picture", type="string", example="https://example.com/profiles/ahmed.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="tags",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="DIY"),
     *                         @OA\Property(property="slug", type="string", example="diy")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="sections",
     *                     type="array",
     *                     description="Guide sections",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Oil Change"),
     *                         @OA\Property(property="title_ar", type="string", example="تغيير الزيت"),
     *                         @OA\Property(property="description", type="string", example="<p>Step 1: Warm up the engine...</p>"),
     *                         @OA\Property(property="description_ar", type="string", example="<p>الخطوة 1: قم بتسخين المحرك...</p>"),
     *                         @OA\Property(property="order_position", type="integer", example=1)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     description="Guide images",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="image_url", type="string", example="https://example.com/images/step1.jpg"),
     *                         @OA\Property(property="caption", type="string", example="Drain bolt location"),
     *                         @OA\Property(property="order_position", type="integer", example=1)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="recent_comments",
     *                     type="array",
     *                     description="10 most recent comments",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="content", type="string", example="Great guide, thanks!"),
     *                         @OA\Property(property="user_name", type="string", example="Sarah Jones"),
     *                         @OA\Property(property="created_at", type="string", example="2025-02-05 11:30:00")
     *                     )
     *                 ),
     *                 @OA\Property(property="meta_title", type="string", example="Motorcycle Maintenance Guide - DabApp"),
     *                 @OA\Property(property="meta_description", type="string", example="Learn how to maintain your motorcycle like a pro."),
     *                 @OA\Property(property="meta_keywords", type="string", example="maintenance, motorcycle, tips, guide"),
     *                 @OA\Property(property="created_at", type="string", example="2025-01-15 10:30:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-02-01 14:20:00"),
     *                 @OA\Property(property="published_at", type="string", example="2025-01-16 09:00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $guide = Guide::with([
            'author',
            'category',
            'tags',
            'sections',
            'images' => function ($query) {
                $query->orderBy('order_position');
            },
            'comments' => function ($query) {
                $query->with('user')->latest();
            }
        ])
            ->withCount(['comments', 'likes'])
            ->find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        return response()->json([
            'data' => $this->formatGuideDetailsForAdmin($guide)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/guides",
     *     summary="Créer un nouveau guide (Admin)",
     *     description="Crée un nouveau guide avec toutes ses informations. Le slug est généré automatiquement depuis le titre.",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données du guide à créer",
     *         @OA\JsonContent(
     *             required={"title", "category_id", "author_id", "status"},
     *             @OA\Property(property="title", type="string", example="Ultimate Guide to Motorcycle Maintenance", description="Guide title (max 255 chars)"),
     *             @OA\Property(property="title_ar", type="string", example="الدليل الشامل لصيانة الدراجات النارية", description="Arabic title"),
     *             @OA\Property(property="excerpt", type="string", example="Everything you need to know about keeping your bike in top condition.", description="Short summary"),
     *             @OA\Property(property="excerpt_ar", type="string", example="كل ما تحتاج معرفته للحفاظ على دراجتك في أفضل حالة.", description="Arabic summary"),
     *             @OA\Property(property="content", type="string", example="<p>Regular oil changes are crucial...</p>", description="Full HTML content"),
     *             @OA\Property(property="content_ar", type="string", example="<p>تغيير الزيت بانتظام ضروري...</p>", description="Arabic HTML content"),
     *             @OA\Property(property="featured_image", type="string", example="https://example.com/images/maintenance.jpg", description="Main image URL"),
     *             @OA\Property(property="category_id", type="integer", example=1, description="Category ID"),
     *             @OA\Property(property="author_id", type="integer", example=5, description="Author ID"),
     *             @OA\Property(property="status", type="string", enum={"draft", "published", "archived"}, example="draft", description="Guide status"),
     *             @OA\Property(
     *                 property="sections",
     *                 type="array",
     *                 description="Content sections",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"type", "title"},
     *                     @OA\Property(property="type", type="string", enum={"text", "image", "text_image", "video"}, example="text"),
     *                     @OA\Property(property="title", type="string", example="Introduction"),
     *                     @OA\Property(property="title_ar", type="string", example="مقدمة"),
     *                     @OA\Property(property="description", type="string", example="<p>Welcome to the guide...</p>"),
     *                     @OA\Property(property="description_ar", type="string", example="<p>مرحبًا بك في الدليل...</p>"),
     *                     @OA\Property(property="order_position", type="integer", example=1)
     *                 ),
     *                 example={
     *                     {
     *                         "type": "text",
     *                         "title": "Introduction",
     *                         "title_ar": "مقدمة",
     *                         "description": "<p>Welcome to the guide...</p>",
     *                         "description_ar": "<p>مرحبًا بك في الدليل...</p>",
     *                         "order_position": 1
     *                     },
     *                     {
     *                         "type": "image",
     *                         "title": "Visual Aid",
     *                         "title_ar": "مساعدة بصرية",
     *                         "description": "<p>See this diagram.</p>",
     *                         "description_ar": "<p>انظر هذا الرسم البياني.</p>",
     *                         "image_url": "https://example.com/diagram.jpg",
     *                         "order_position": 2
     *                     }
     *                 }
     *             ),
     *             @OA\Property(property="meta_title", type="string", example="Motorcycle Maintenance Guide - DabApp", description="SEO Title"),
     *             @OA\Property(property="meta_description", type="string", example="Learn how to maintain your motorcycle like a pro.", description="SEO Description"),
     *             @OA\Property(property="meta_keywords", type="string", example="maintenance, motorcycle, tips, guide", description="SEO Keywords")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Guide créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Guide created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=150),
     *                 @OA\Property(property="title", type="string", example="Ultimate Guide to Motorcycle Maintenance"),
     *                 @OA\Property(property="title_ar", type="string", example="الدليل الشامل لصيانة الدراجات النارية"),
     *                 @OA\Property(property="slug", type="string", example="ultimate-guide-motorcycle-maintenance"),
     *                 @OA\Property(property="excerpt", type="string", example="Everything you need to know about keeping your bike in top condition."),
     *                 @OA\Property(property="excerpt_ar", type="string", example="كل ما تحتاج معرفته للحفاظ على دراجتك في أفضل حالة."),
     *                 @OA\Property(property="featured_image", type="string", example="https://example.com/images/maintenance.jpg"),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(property="views_count", type="integer", example=0),
     *                 @OA\Property(property="likes_count", type="integer", example=0),
     *                 @OA\Property(property="comments_count", type="integer", example=0),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Maintenance"),
     *                     @OA\Property(property="slug", type="string", example="maintenance")
     *                 ),
     *                 @OA\Property(
     *                     property="author",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 ),
     *                 @OA\Property(
     *                     property="tags",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Entretien")
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", example="2025-02-07 10:30:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-02-07 10:30:00"),
     *                 @OA\Property(property="published_at", type="string", example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="title",
     *                     type="array",
     *                     @OA\Items(type="string", example="The title field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected category id is invalid.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string',
            'excerpt_ar' => 'nullable|string',
            'content' => 'nullable|string',
            'content_ar' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'category_id' => 'required|exists:guide_categories,id',
            'author_id' => 'required|exists:users,id',
            'status' => 'required|in:draft,published,archived',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:guide_tags,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Générer le slug
        $slug = Str::slug($request->title);
        $originalSlug = $slug;
        $count = 1;

        while (Guide::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        $guide = Guide::create([
            'title' => $request->title,
            'title_ar' => $request->title_ar,
            'slug' => $slug,
            'excerpt' => $request->excerpt,
            'excerpt_ar' => $request->excerpt_ar,
            'content' => $request->content,
            'content_ar' => $request->content_ar,
            'featured_image' => $request->featured_image,
            'category_id' => $request->category_id,
            'author_id' => $request->author_id,
            'status' => $request->status,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords,
            'views_count' => 0,
            'published_at' => $request->status === 'published' ? now() : null,
        ]);

        // Attacher les tags
        if ($request->has('tags')) {
            $guide->tags()->sync($request->tags);
        }

        // Ajouter les sections
        if ($request->has('sections') && is_array($request->sections)) {
            foreach ($request->sections as $index => $sectionData) {
                GuideSection::create([
                    'guide_id' => $guide->id,
                    'type' => $sectionData['type'],
                    'title' => $sectionData['title'] ?? null,
                    'title_ar' => $sectionData['title_ar'] ?? null,
                    'description' => $sectionData['description'] ?? null,
                    'description_ar' => $sectionData['description_ar'] ?? null,
                    'image_url' => $sectionData['image_url'] ?? null,
                    'image_position' => $sectionData['image_position'] ?? 'top',
                    'media' => $sectionData['media'] ?? null,
                    'order_position' => $sectionData['order_position'] ?? $index,
                ]);
            }
        }

        return response()->json([
            'message' => 'Guide created successfully',
            'data' => $this->formatGuideForAdmin($guide->load(['author', 'category', 'tags']))
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/guides/{id}",
     *     summary="Mettre à jour un guide (Admin)",
     *     description="Met à jour un guide existant. Tous les champs sont optionnels. Si le titre change, le slug est régénéré automatiquement.",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide à mettre à jour",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données à mettre à jour (tous les champs sont optionnels)",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Ultimate Guide to Motorcycle Maintenance (Updated)", description="New title (max 255)"),
     *             @OA\Property(property="title_ar", type="string", example="الدليل الشامل لصيانة الدراجات النارية (محدث)", description="New Arabic title"),
     *             @OA\Property(property="excerpt", type="string", example="Updated summary of the guide."),
     *             @OA\Property(property="excerpt_ar", type="string", example="ملخص محدث للدليل."),
     *             @OA\Property(property="content", type="string", example="<p>Updated content...</p>"),
     *             @OA\Property(property="content_ar", type="string", example="<p>محتوى محدث...</p>"),
     *             @OA\Property(property="featured_image", type="string", example="https://example.com/new-image.jpg"),
     *             @OA\Property(property="category_id", type="integer", example=2),
     *             @OA\Property(property="author_id", type="integer", example=3),
     *             @OA\Property(property="status", type="string", enum={"draft", "published", "archived"}, example="published"),
     *             @OA\Property(
     *                 property="tags",
     *                 type="array",
     *                 description="New tags (replaces old ones)",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 5}
     *             ),
     *             @OA\Property(
     *                 property="sections",
     *                 type="array",
     *                 description="Updated sections",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Introduction (Updated)"),
     *                     @OA\Property(property="title_ar", type="string", example="مقدمة (محدثة)"),
     *                     @OA\Property(property="description", type="string", example="<p>Updated content...</p>"),
     *                     @OA\Property(property="description_ar", type="string", example="<p>محتوى محدث...</p>"),
     *                     @OA\Property(property="order_position", type="integer", example=1)
     *                 ),
     *                 example={
     *                     {
     *                         "id": 1,
     *                         "title": "Introduction (Updated)",
     *                         "title_ar": "مقدمة (محدثة)",
     *                         "description": "<p>Updated content...</p>",
     *                         "description_ar": "<p>محتوى محدث...</p>",
     *                         "order_position": 1
     *                     }
     *                 }
     *             ),
     *             @OA\Property(property="meta_title", type="string", example="Updated SEO Title"),
     *             @OA\Property(property="meta_description", type="string", example="Updated SEO Description"),
     *             @OA\Property(property="meta_keywords", type="string", example="updated, keywords")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guide mis à jour avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Guide updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Guide mis à jour avec toutes ses relations"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string',
            'excerpt_ar' => 'nullable|string',
            'content' => 'nullable|string',
            'content_ar' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'category_id' => 'sometimes|exists:guide_categories,id',
            'author_id' => 'sometimes|exists:users,id',
            'status' => 'sometimes|in:draft,published,archived',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:guide_tags,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Si le titre change, régénérer le slug
        if ($request->has('title') && $request->title !== $guide->title) {
            $slug = Str::slug($request->title);
            $originalSlug = $slug;
            $count = 1;

            while (Guide::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }

            $guide->slug = $slug;
        }

        // Mettre à jour published_at si le statut passe à published
        if ($request->has('status') && $request->status === 'published' && $guide->status !== 'published') {
            $guide->published_at = now();
        }

        $guide->update($request->except(['tags']));

        // Mettre à jour les tags
        if ($request->has('tags')) {
            $guide->tags()->sync($request->tags);
        }

        // Mettre à jour les sections
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
                            'title_ar' => $sectionData['title_ar'] ?? null,
                            'description' => $sectionData['description'] ?? null,
                            'description_ar' => $sectionData['description_ar'] ?? null,
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
                        'title_ar' => $sectionData['title_ar'] ?? null,
                        'description' => $sectionData['description'] ?? null,
                        'description_ar' => $sectionData['description_ar'] ?? null,
                        'image_url' => $sectionData['image_url'] ?? null,
                        'image_position' => $sectionData['image_position'] ?? 'top',
                        'media' => $sectionData['media'] ?? null,
                        'order_position' => $sectionData['order_position'] ?? $index,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Guide updated successfully',
            'data' => $this->formatGuideForAdmin($guide->load(['author', 'category', 'tags']))
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/guides/{id}",
     *     summary="Supprimer un guide (Admin)",
     *     description="Supprime définitivement un guide et toutes ses relations (tags, sections, images, commentaires, likes). Cette action est irréversible.",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide à supprimer",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guide supprimé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        // Supprimer les relations
        $guide->tags()->detach();
        $guide->sections()->delete();
        $guide->images()->delete();
        $guide->comments()->delete();
        DB::table('guide_likes')->where('guide_id', $id)->delete();

        $guide->delete();

        return response()->json([
            'message' => 'Guide deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/guides/{id}/change-status",
     *     summary="Changer le statut d'un guide (Admin)",
     *     description="Change le statut d'un guide. Si le statut passe à 'published' pour la première fois, la date de publication est définie automatiquement.",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nouveau statut du guide",
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"draft", "published", "archived"},
     *                 example="published",
     *                 description="Nouveau statut à appliquer"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut changé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Status changed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="old_status", type="string", example="draft"),
     *                 @OA\Property(property="new_status", type="string", example="published"),
     *                 @OA\Property(property="published_at", type="string", example="2025-02-07 10:30:00", description="Date de publication (null si non publié)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="status",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected status is invalid.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     )
     * )
     */
    public function changeStatus(Request $request, $id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,published,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $guide->status;
        $guide->status = $request->status;

        // Si on passe à published pour la première fois
        if ($request->status === 'published' && $oldStatus !== 'published') {
            $guide->published_at = now();
        }

        $guide->save();

        return response()->json([
            'message' => 'Status changed successfully',
            'data' => [
                'id' => $guide->id,
                'old_status' => $oldStatus,
                'new_status' => $guide->status,
                'published_at' => $guide->published_at?->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/guides/bulk-delete",
     *     summary="Suppression en masse de guides (Admin)",
     *     description="Supprime plusieurs guides en une seule opération. Toutes les relations (tags, sections, images, commentaires, likes) sont également supprimées. Cette action est irréversible.",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Liste des IDs des guides à supprimer",
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 description="Tableau des IDs de guides à supprimer (minimum 1, recommandé max 100)",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3, 4, 5}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guides supprimés avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Guides deleted successfully"),
     *             @OA\Property(property="deleted_count", type="integer", example=5, description="Nombre de guides supprimés")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="The ids field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="ids.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected ids.0 is invalid.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     )
     * )
     */
    public function bulkDelete(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:guides,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $ids = $request->ids;

        // Supprimer les relations
        DB::table('guide_tag')->whereIn('guide_id', $ids)->delete();
        DB::table('guide_sections')->whereIn('guide_id', $ids)->delete();
        DB::table('guide_images')->whereIn('guide_id', $ids)->delete();
        DB::table('guide_comments')->whereIn('guide_id', $ids)->delete();
        DB::table('guide_likes')->whereIn('guide_id', $ids)->delete();

        // Supprimer les guides
        Guide::whereIn('id', $ids)->delete();

        return response()->json([
            'message' => 'Guides deleted successfully',
            'deleted_count' => count($ids)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/guides/bulk-change-status",
     *     summary="Changement de statut en masse (Admin)",
     *     description="Change le statut de plusieurs guides en une seule opération. Si le statut devient 'published', la date de publication est définie pour tous les guides concernés.",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Liste des IDs et nouveau statut",
     *         @OA\JsonContent(
     *             required={"ids", "status"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 description="Tableau des IDs de guides à modifier",
     *                 @OA\Items(type="integer"),
     *                 example={10, 11, 12, 13}
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"draft", "published", "archived"},
     *                 example="published",
     *                 description="Nouveau statut à appliquer à tous les guides"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statuts changés avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Status changed successfully"),
     *             @OA\Property(property="updated_count", type="integer", example=4, description="Nombre de guides modifiés"),
     *             @OA\Property(property="new_status", type="string", example="published", description="Nouveau statut appliqué")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="The ids field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected status is invalid.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     )
     * )
     */
    public function bulkChangeStatus(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:guides,id',
            'status' => 'required|in:draft,published,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = ['status' => $request->status];

        // Si on passe à published, mettre à jour published_at
        if ($request->status === 'published') {
            $updateData['published_at'] = now();
        }

        Guide::whereIn('id', $request->ids)->update($updateData);

        return response()->json([
            'message' => 'Status changed successfully',
            'updated_count' => count($request->ids),
            'new_status' => $request->status
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/guides/{id}/comments",
     *     summary="Liste des commentaires d'un guide (Admin)",
     *     description="Récupère tous les commentaires d'un guide spécifique avec les informations des utilisateurs",
     *     tags={"Admin - Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des commentaires récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="guide_id", type="integer", example=1),
     *             @OA\Property(property="comments_count", type="integer", example=25, description="Nombre total de commentaires"),
     *             @OA\Property(
     *                 property="comments",
     *                 type="array",
     *                 description="Liste de tous les commentaires triés du plus récent au plus ancien",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="guide_id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=10),
     *                     @OA\Property(property="content", type="string", example="Excellent guide, très utile pour les débutants!"),
     *                     @OA\Property(property="user_name", type="string", example="Jane Smith"),
     *                     @OA\Property(property="profile_picture", type="string", example="https://example.com/profiles/jane.jpg"),
     *                     @OA\Property(property="created_at", type="string", example="2025-02-05 14:30:00"),
     *                     @OA\Property(property="updated_at", type="string", example="2025-02-05 14:30:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Accès admin requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Admin access required")
     *         )
     *     )
     * )
     */
    public function getComments($id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $comments = DB::table('guide_comments')
            ->join('users', 'guide_comments.user_id', '=', 'users.id')
            ->where('guide_comments.guide_id', $id)
            ->select(
                'guide_comments.*',
                DB::raw('CONCAT(users.first_name, " ", users.last_name) as user_name'),
                'users.profile_picture'
            )
            ->orderBy('guide_comments.created_at', 'desc')
            ->get();

        return response()->json([
            'guide_id' => (int) $id,
            'comments_count' => $comments->count(),
            'comments' => $comments
        ]);
    }

    /**
     * Formater un guide pour l'admin
     */
    private function formatGuideForAdmin($guide)
    {
        return [
            'id' => $guide->id,
            'title' => $guide->title,
            'title_ar' => $guide->title_ar,
            'slug' => $guide->slug,
            'excerpt' => $guide->excerpt,
            'excerpt_ar' => $guide->excerpt_ar,
            'featured_image' => $guide->featured_image,
            'status' => $guide->status,
            'views_count' => $guide->views_count,
            'likes_count' => $guide->likes_count ?? 0,
            'comments_count' => $guide->comments_count ?? 0,
            'category' => $guide->category ? [
                'id' => $guide->category->id,
                'name' => $guide->category->name,
                'slug' => $guide->category->slug,
            ] : null,
            'author' => [
                'id' => $guide->author->id,
                'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                'email' => $guide->author->email,
                'profile_picture' => $guide->author->profile_picture,
            ],
            'tags' => $guide->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            }),
            'meta_title' => $guide->meta_title,
            'meta_description' => $guide->meta_description,
            'meta_keywords' => $guide->meta_keywords,
            'created_at' => $guide->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $guide->updated_at->format('Y-m-d H:i:s'),
            'published_at' => $guide->published_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Formater les détails complets d'un guide pour l'admin
     */
    private function formatGuideDetailsForAdmin($guide)
    {
        $data = $this->formatGuideForAdmin($guide);

        $data['content'] = $guide->content;
        $data['content_ar'] = $guide->content_ar;
        $data['sections'] = $guide->sections->map(function ($section) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'title_ar' => $section->title_ar,
                'description' => $section->description,
                'description_ar' => $section->description_ar,
                'order_position' => $section->order_position,
            ];
        });
        $data['images'] = $guide->images->map(function ($image) {
            return [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'caption' => $image->caption,
                'order_position' => $image->order_position,
            ];
        });
        $data['recent_comments'] = $guide->comments->take(10)->map(function ($comment) {
            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'user_name' => $comment->user->first_name . ' ' . $comment->user->last_name,
                'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $data;
    }
}
