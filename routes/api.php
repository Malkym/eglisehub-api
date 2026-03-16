<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MinistereController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\EvenementController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MessageContactController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\NotificationController;

// ============================================================
// ROUTES PUBLIQUES
// ============================================================
Route::post('/login', [AuthController::class, 'login']);

// Ministère
Route::get('/public/ministere', [MinistereController::class, 'getBySubdomain']);

// Pages
Route::get('/public/pages',        [PageController::class, 'publicIndex']);
Route::get('/public/pages/{slug}', [PageController::class, 'publicShow']);

// Articles
Route::get('/public/articles',        [ArticleController::class, 'publicIndex']);
Route::get('/public/articles/{slug}', [ArticleController::class, 'publicShow']);

// Événements
Route::get('/public/events',     [EvenementController::class, 'publicIndex']);
Route::get('/public/events/{id}', [EvenementController::class, 'publicShow']);

// Médias
Route::get('/public/media', [MediaController::class, 'publicIndex']);

// Formulaire de contact
Route::post('/public/contact', [MessageContactController::class, 'publicStore']);

// 
Route::get('/public/faq',     [FaqController::class,    'publicIndex']);
Route::get('/public/sliders', [SliderController::class, 'publicIndex']);
Route::get('/public/tags',    [TagController::class,    'publicIndex']);

// ============================================================
// ROUTES PROTÉGÉES
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::get('/me',               [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/',                [NotificationController::class, 'index']);
        Route::get('unread-count',     [NotificationController::class, 'unreadCount']);
        Route::post('mark-all-read',   [NotificationController::class, 'markAllAsRead']);
        Route::delete('clear-read',    [NotificationController::class, 'clearRead']);
        Route::get('{id}',             [NotificationController::class, 'show']);
        Route::patch('{id}/read',      [NotificationController::class, 'markAsRead']);
        Route::delete('{id}',          [NotificationController::class, 'destroy']);
    });

    // === SUPER ADMIN ===
    Route::middleware('role:super_admin')->prefix('admin')->group(function () {
        Route::apiResource('ministeres', MinistereController::class);
        Route::patch('ministeres/{id}/toggle', [MinistereController::class, 'toggle']);
        Route::get('ministeres/{id}/stats',    [MinistereController::class, 'stats']);
        Route::get('dashboard', [DashboardController::class, 'global']);
    });

    // === SUPER ADMIN + ADMIN MINISTÈRE ===
    Route::middleware('role:super_admin,admin_ministere')->prefix('ministry')->group(function () {

        // Pages
        Route::post('pages/reorder',         [PageController::class, 'reorder']);
        Route::patch('pages/{id}/publish',   [PageController::class, 'publish']);
        Route::patch('pages/{id}/unpublish', [PageController::class, 'unpublish']);
        Route::apiResource('pages', PageController::class);

        // Articles
        Route::patch('articles/{id}/publish', [ArticleController::class, 'publish']);
        Route::patch('articles/{id}/feature', [ArticleController::class, 'feature']);
        Route::apiResource('articles', ArticleController::class);

        // Événements
        Route::patch('events/{id}/cancel', [EvenementController::class, 'cancel']);
        Route::apiResource('events', EvenementController::class);

        // Médias
        Route::post('media/upload',       [MediaController::class, 'upload']);
        Route::post('media/bulk-delete',  [MediaController::class, 'bulkDelete']);
        Route::apiResource('media', MediaController::class)->except(['store']);

        // Messages de contact
        Route::get('contact-messages',               [MessageContactController::class, 'index']);
        Route::get('contact-messages/{id}',          [MessageContactController::class, 'show']);
        Route::patch('contact-messages/{id}/read',   [MessageContactController::class, 'markRead']);
        Route::patch('contact-messages/{id}/unread', [MessageContactController::class, 'markUnread']);
        Route::post('contact-messages/{id}/reply',   [MessageContactController::class, 'reply']);
        Route::delete('contact-messages/{id}',       [MessageContactController::class, 'destroy']);
    });

    // ===== PROFIL (tous les utilisateurs connectés) =====
    Route::prefix('profile')->group(function () {
        Route::get('/',               [ProfileController::class, 'show']);
        Route::put('/',               [ProfileController::class, 'update']);
        Route::post('change-password', [ProfileController::class, 'changePassword']);
        Route::get('activity',        [ProfileController::class, 'activity']);
    });

    // ===== SUPER ADMIN =====
    Route::middleware('role:super_admin')->prefix('admin')->group(function () {
        // ... routes existantes ...

        // Utilisateurs (super admin)
        Route::get('users',                  [UserController::class, 'index']);
        Route::post('users',                 [UserController::class, 'store']);
        Route::get('users/{id}',             [UserController::class, 'show']);
        Route::put('users/{id}',             [UserController::class, 'update']);
        Route::delete('users/{id}',          [UserController::class, 'destroy']);
        Route::patch('users/{id}/toggle',    [UserController::class, 'toggle']);
        Route::post('users/{id}/impersonate', [UserController::class, 'impersonate']);

        // Logs (super admin)
        Route::get('logs',                    [LogController::class, 'index']);
        Route::get('logs/export',             [LogController::class, 'export']);
        Route::post('logs/clean',             [LogController::class, 'clean']);
        Route::get('logs/{id}',               [LogController::class, 'show']);
        Route::get('users/{id}/activity',     [LogController::class, 'userActivity']);
    });

    // ===== SUPER ADMIN + ADMIN MINISTÈRE =====
    Route::middleware('role:super_admin,admin_ministere')->prefix('ministry')->group(function () {
        // ... routes existantes ...

        // Utilisateurs (admin ministère)
        Route::get('users',         [UserController::class, 'ministryIndex']);
        Route::post('users',        [UserController::class, 'ministryStore']);
        Route::put('users/{id}',    [UserController::class, 'ministryUpdate']);
        Route::delete('users/{id}', [UserController::class, 'ministryDestroy']);

        // Logs ministère
        Route::get('logs', [LogController::class, 'ministryLogs']);

        // Paramètres
        Route::get('settings',          [SettingController::class, 'index']);
        Route::put('settings',          [SettingController::class, 'update']);
        Route::get('settings/theme',    [SettingController::class, 'getTheme']);
        Route::put('settings/theme',    [SettingController::class, 'updateTheme']);
        Route::get('settings/seo',      [SettingController::class, 'getSeo']);
        Route::put('settings/seo',      [SettingController::class, 'updateSeo']);
        Route::get('settings/social',   [SettingController::class, 'getSocial']);
        Route::put('settings/social',   [SettingController::class, 'updateSocial']);

        Route::get('dashboard',         [DashboardController::class, 'ministry']);
        Route::get('stats/content',     [DashboardController::class, 'statsContent']);
        Route::get('stats/engagement',  [DashboardController::class, 'statsEngagement']);

        // FAQ
        Route::post('faq/reorder',      [FaqController::class, 'reorder']);
        Route::patch('faq/{id}/toggle', [FaqController::class, 'toggle']);
        Route::apiResource('faq', FaqController::class);

        // Sliders
        Route::post('sliders/reorder',       [SliderController::class, 'reorder']);
        Route::patch('sliders/{id}/toggle',  [SliderController::class, 'toggle']);
        Route::apiResource('sliders', SliderController::class);

        // Tags
        Route::get('tags/popular',   [TagController::class, 'popular']);
        Route::post('tags/attach',   [TagController::class, 'attach']);
        Route::post('tags/detach',   [TagController::class, 'detach']);
        Route::apiResource('tags', TagController::class)->except(['show']);
    });
});
