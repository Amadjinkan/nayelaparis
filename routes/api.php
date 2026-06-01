<?php

use App\Http\Controllers\AdminCommandeController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RetourController;
use App\Http\Controllers\SiteContentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API NayeLa Paris
|--------------------------------------------------------------------------
*/

// ============== PUBLIC (sans authentification) ==============

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('password/forgot', [AuthController::class, 'requestPasswordReset']);
});

// Catalogue produits — accessible à tous
Route::get('produits', [ProduitController::class, 'index']);
Route::get('produits/{id}', [ProduitController::class, 'show']);

// Configuration publique (clé Stripe publique, etc.)
Route::get('config', [ConfigController::class, 'index']);

// Menu public administrable
Route::get('menu', [MenuItemController::class, 'index']);

// Contact public
Route::post('contact', [ContactController::class, 'store']);

// Marketing public
Route::get('marketing/recommandes', [MarketingController::class, 'recommended']);
Route::get('marketing/recemment-vus', [MarketingController::class, 'recent']);
Route::post('marketing/coupons/verifier', [MarketingController::class, 'validateCoupon']);
Route::post('produits/{id}/vue', [MarketingController::class, 'recordView']);
Route::get('produits/{id}/similaires', [MarketingController::class, 'similar']);
Route::get('produits/{id}/avis', [MarketingController::class, 'reviews']);

// Contenu public administrable
Route::get('site/content', [SiteContentController::class, 'publicContent']);
Route::get('site/categories', [SiteContentController::class, 'publicCategories']);
Route::get('site/settings', [SiteContentController::class, 'publicSettings']);
Route::get('site/banners', [SiteContentController::class, 'publicBanners']);
Route::get('site/pages/{slug}', [SiteContentController::class, 'publicPage']);

// Webhook Stripe — pas d'auth (Stripe vérifie via signature)
Route::post('stripe/webhook', [PaiementController::class, 'webhook']);

// ============== AUTHENTIFIÉ (token Sanctum requis) ==============

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Profil & adresses
    Route::get('profil', [ProfilController::class, 'show']);
    Route::put('profil', [ProfilController::class, 'update']);
    Route::post('profil/adresses', [ProfilController::class, 'ajouterAdresse']);
    Route::delete('profil/adresses/{id}', [ProfilController::class, 'supprimerAdresse']);

    // Commandes
    Route::get('commandes', [CommandeController::class, 'mesCommandes']);
    Route::get('commandes/{id}/facture', [CommandeController::class, 'facture']);
    Route::get('commandes/{id}', [CommandeController::class, 'show']);
    Route::post('commandes', [CommandeController::class, 'store']);

    // Marketing client
    Route::get('marketing/favoris', [MarketingController::class, 'favorites']);
    Route::post('marketing/favoris/{id}', [MarketingController::class, 'addFavorite']);
    Route::delete('marketing/favoris/{id}', [MarketingController::class, 'removeFavorite']);
    Route::post('produits/{id}/avis', [MarketingController::class, 'addReview']);
    Route::get('marketing/fidelite', [MarketingController::class, 'loyalty']);

    // Paiement
    Route::post('paiements/intent', [PaiementController::class, 'creerIntent']);
    Route::post('paiements/confirmer', [PaiementController::class, 'confirmer']);

    // Retours (RMA)
    Route::get('retours', [RetourController::class, 'mesRetours']);
    Route::get('retours/{id}', [RetourController::class, 'show']);
    Route::post('retours', [RetourController::class, 'store']);

    // ============== ADMIN UNIQUEMENT ==============

    Route::middleware('admin')->prefix('admin')->group(function () {
        // Stats
        Route::get('statistiques', [AdminCommandeController::class, 'stats']);
        Route::get('dashboard', [AdminDashboardController::class, 'index']);
        Route::get('dashboard/export', [AdminDashboardController::class, 'export']);

        // Produits
        Route::get('produits', [ProduitController::class, 'adminIndex']);
        Route::post('produits', [ProduitController::class, 'store']);
        Route::put('produits/{id}', [ProduitController::class, 'update']);
        Route::delete('produits/{id}', [ProduitController::class, 'destroy']);

        // Menu du site
        Route::get('menu', [MenuItemController::class, 'adminIndex']);
        Route::post('menu', [MenuItemController::class, 'store']);
        Route::post('menu/reorder', [MenuItemController::class, 'reorder']);
        Route::put('menu/{id}', [MenuItemController::class, 'update']);
        Route::delete('menu/{id}', [MenuItemController::class, 'destroy']);

        // Contenu du site
        Route::get('site/content', [SiteContentController::class, 'adminContent']);
        Route::put('site/settings', [SiteContentController::class, 'updateSettings']);
        Route::post('site/categories', [SiteContentController::class, 'storeCategory']);
        Route::put('site/categories/{id}', [SiteContentController::class, 'updateCategory']);
        Route::delete('site/categories/{id}', [SiteContentController::class, 'destroyCategory']);
        Route::post('site/banners', [SiteContentController::class, 'storeBanner']);
        Route::put('site/banners/{id}', [SiteContentController::class, 'updateBanner']);
        Route::delete('site/banners/{id}', [SiteContentController::class, 'destroyBanner']);
        Route::post('site/pages', [SiteContentController::class, 'storePage']);
        Route::put('site/pages/{id}', [SiteContentController::class, 'updatePage']);
        Route::delete('site/pages/{id}', [SiteContentController::class, 'destroyPage']);

        // Commandes
        Route::get('commandes', [AdminCommandeController::class, 'index']);
        Route::put('commandes/{id}', [AdminCommandeController::class, 'update']);

        // Marketing
        Route::get('coupons', [MarketingController::class, 'adminCoupons']);
        Route::post('coupons', [MarketingController::class, 'storeCoupon']);
        Route::put('coupons/{id}', [MarketingController::class, 'updateCoupon']);
        Route::delete('coupons/{id}', [MarketingController::class, 'destroyCoupon']);
        Route::get('avis', [MarketingController::class, 'adminReviews']);
        Route::post('avis/{id}/approuver', [MarketingController::class, 'approveReview']);
        Route::post('avis/{id}/refuser', [MarketingController::class, 'rejectReview']);

        // Retours
        Route::get('retours', [RetourController::class, 'adminIndex']);
        Route::post('retours/{id}/approuver', [RetourController::class, 'approuver']);
        Route::post('retours/{id}/refuser', [RetourController::class, 'refuser']);
        Route::post('retours/{id}/rembourser', [RetourController::class, 'rembourser']);
    });
});
