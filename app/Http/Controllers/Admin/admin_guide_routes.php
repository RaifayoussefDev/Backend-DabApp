<?php

// Routes Admin pour les Guides
// À ajouter dans routes/api.php

use App\Http\Controllers\Admin\GuideAdminController;
use App\Http\Controllers\Admin\GuideCategoryAdminController;
use App\Http\Controllers\Admin\GuideTagAdminController;
use App\Http\Controllers\Admin\GuideCommentAdminController;

// Groupe de routes admin avec middleware auth
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    
    // ============================================
    // GUIDES ADMIN ROUTES
    // ============================================
    
    // Statistiques globales
    Route::get('guides/stats', [GuideAdminController::class, 'stats']);
    
    // Actions en masse (bulk)
    Route::post('guides/bulk-delete', [GuideAdminController::class, 'bulkDelete']);
    Route::post('guides/bulk-change-status', [GuideAdminController::class, 'bulkChangeStatus']);
    
    // CRUD principal
    Route::get('guides', [GuideAdminController::class, 'index']); // Liste avec filtres
    Route::post('guides', [GuideAdminController::class, 'store']); // Créer
    Route::get('guides/{id}', [GuideAdminController::class, 'show']); // Détails
    Route::put('guides/{id}', [GuideAdminController::class, 'update']); // Mettre à jour
    Route::delete('guides/{id}', [GuideAdminController::class, 'destroy']); // Supprimer
    
    // Changement de statut
    Route::post('guides/{id}/change-status', [GuideAdminController::class, 'changeStatus']);
    
    // Commentaires d'un guide
    Route::get('guides/{id}/comments', [GuideAdminController::class, 'getComments']);
    
    
    // ============================================
    // CATEGORIES ADMIN ROUTES
    // ============================================
    
    // Statistiques
    Route::get('guide-categories/stats', [GuideCategoryAdminController::class, 'stats']);
    
    // Réorganiser
    Route::post('guide-categories/reorder', [GuideCategoryAdminController::class, 'reorder']);
    
    // CRUD
    Route::get('guide-categories', [GuideCategoryAdminController::class, 'index']);
    Route::post('guide-categories', [GuideCategoryAdminController::class, 'store']);
    Route::put('guide-categories/{id}', [GuideCategoryAdminController::class, 'update']);
    Route::delete('guide-categories/{id}', [GuideCategoryAdminController::class, 'destroy']);
    
    
    // ============================================
    // TAGS ADMIN ROUTES
    // ============================================
    
    // Statistiques
    Route::get('guide-tags/stats', [GuideTagAdminController::class, 'stats']);
    
    // Nettoyage
    Route::delete('guide-tags/cleanup-unused', [GuideTagAdminController::class, 'cleanupUnused']);
    
    // Actions en masse
    Route::post('guide-tags/bulk-delete', [GuideTagAdminController::class, 'bulkDelete']);
    
    // CRUD
    Route::get('guide-tags', [GuideTagAdminController::class, 'index']);
    Route::post('guide-tags', [GuideTagAdminController::class, 'store']);
    Route::put('guide-tags/{id}', [GuideTagAdminController::class, 'update']);
    Route::delete('guide-tags/{id}', [GuideTagAdminController::class, 'destroy']);
    
    
    // ============================================
    // COMMENTS ADMIN ROUTES
    // ============================================
    
    // Statistiques
    Route::get('guide-comments/stats', [GuideCommentAdminController::class, 'stats']);
    
    // Suppressions en masse
    Route::post('guide-comments/bulk-delete', [GuideCommentAdminController::class, 'bulkDelete']);
    Route::delete('guide-comments/by-user/{user_id}', [GuideCommentAdminController::class, 'deleteByUser']);
    Route::delete('guide-comments/by-guide/{guide_id}', [GuideCommentAdminController::class, 'deleteByGuide']);
    
    // CRUD
    Route::get('guide-comments', [GuideCommentAdminController::class, 'index']);
    Route::delete('guide-comments/{id}', [GuideCommentAdminController::class, 'destroy']);
});
