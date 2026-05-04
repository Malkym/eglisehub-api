<?php

use App\Http\Controllers\Api\ArticleCommentaireController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DonController;
use App\Http\Controllers\Api\EvenementController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MessageContactController;
use App\Http\Controllers\Api\MinistereController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorshipScheduleController;
use Illuminate\Support\Facades\Route;

Route::options('/{any}', function () {
    return response()->json([], 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Subdomain, Accept, Locale')
        ->header('Access-Control-Max-Age', '86400');
})->where('any', '.*');

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/public/contact', [MessageContactController::class, 'publicStore']);
    Route::post('/public/dons', [DonController::class, 'store']);
    Route::post('/public/articles/{slug}/comments', [ArticleCommentaireController::class, 'publicStore']);
    Route::post('/public/articles/{slug}/rate', [ArticleController::class, 'rate']);
});

Route::get('/public/ministere', [MinistereController::class, 'getBySubdomain']);

Route::get('/public/pages', [PageController::class, 'publicIndex']);
Route::get('/public/pages/{slug}', [PageController::class, 'publicShow']);

Route::get('/public/articles', [ArticleController::class, 'publicIndex']);
Route::get('/public/articles/{slug}', [ArticleController::class, 'publicShow']);

Route::get('/public/events', [EvenementController::class, 'publicIndex']);
Route::get('/public/events/{id}', [EvenementController::class, 'publicShow']);

Route::get('/public/media', [MediaController::class, 'publicIndex']);
Route::get('/public/gallery', [MediaController::class, 'publicGallery']);

Route::get('/public/faq', [FaqController::class, 'publicIndex']);
Route::get('/public/sliders', [SliderController::class, 'publicIndex']);
Route::get('/public/tags', [TagController::class, 'publicIndex']);

Route::get('/public/worship-schedules', [WorshipScheduleController::class, 'publicIndex']);

Route::get('/public/settings', [SettingController::class, 'publicSettings']);

Route::prefix('public/articles')->group(function () {
    Route::get('{slug}/comments', [ArticleCommentaireController::class, 'publicIndex']);
    Route::get('{slug}/rating', [ArticleController::class, 'getRating']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('clear-read', [NotificationController::class, 'clearRead']);
        Route::get('{id}', [NotificationController::class, 'show']);
        Route::patch('{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('{id}', [NotificationController::class, 'destroy']);
    });

    Route::middleware('role:super_admin')->prefix('admin')->group(function () {
        Route::apiResource('ministeres', MinistereController::class);
        Route::patch('ministeres/{id}/toggle', [MinistereController::class, 'toggle']);
        Route::get('ministeres/{id}/stats', [MinistereController::class, 'stats']);
        Route::get('dashboard', [DashboardController::class, 'global']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);
        Route::patch('users/{id}/toggle', [UserController::class, 'toggle']);
        Route::post('users/{id}/impersonate', [UserController::class, 'impersonate']);

        Route::get('logs', [LogController::class, 'index']);
        Route::get('logs/export', [LogController::class, 'export']);
        Route::post('logs/clean', [LogController::class, 'clean']);
        Route::get('logs/{id}', [LogController::class, 'show']);
        Route::get('users/{id}/activity', [LogController::class, 'userActivity']);
    });

    Route::middleware('role:super_admin,admin_ministere')->prefix('ministry')->group(function () {
        Route::post('pages/reorder', [PageController::class, 'reorder']);
        Route::patch('pages/{id}/publish', [PageController::class, 'publish']);
        Route::patch('pages/{id}/unpublish', [PageController::class, 'unpublish']);
        Route::apiResource('pages', PageController::class);

        Route::patch('articles/{id}/publish', [ArticleController::class, 'publish']);
        Route::patch('articles/{id}/feature', [ArticleController::class, 'feature']);
        Route::apiResource('articles', ArticleController::class);

        Route::patch('events/{id}/cancel', [EvenementController::class, 'cancel']);
        Route::apiResource('events', EvenementController::class);

        Route::post('media/upload', [MediaController::class, 'upload']);
        Route::post('media/bulk-delete', [MediaController::class, 'bulkDelete']);
        Route::apiResource('media', MediaController::class)->except(['store']);
        Route::patch('media/{id}/toggle-visibility', [MediaController::class, 'toggleVisibility']);

        Route::patch('worship-schedules/{id}/toggle-active', [WorshipScheduleController::class, 'toggleActive']);
        Route::apiResource('worship-schedules', WorshipScheduleController::class);

        Route::get('contact-messages', [MessageContactController::class, 'index']);
        Route::get('contact-messages/{id}', [MessageContactController::class, 'show']);
        Route::patch('contact-messages/{id}/read', [MessageContactController::class, 'markRead']);
        Route::patch('contact-messages/{id}/unread', [MessageContactController::class, 'markUnread']);
        Route::post('contact-messages/{id}/reply', [MessageContactController::class, 'reply']);
        Route::delete('contact-messages/{id}', [MessageContactController::class, 'destroy']);
        Route::get('message-reponses', [MessageContactController::class, 'allReplies']);

        Route::get('users', [UserController::class, 'ministryIndex']);
        Route::post('users', [UserController::class, 'ministryStore']);
        Route::put('users/{id}', [UserController::class, 'ministryUpdate']);
        Route::delete('users/{id}', [UserController::class, 'ministryDestroy']);

        Route::get('logs', [LogController::class, 'ministryLogs']);

        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
        Route::get('settings/theme', [SettingController::class, 'getTheme']);
        Route::put('settings/theme', [SettingController::class, 'updateTheme']);
        Route::get('settings/seo', [SettingController::class, 'getSeo']);
        Route::put('settings/seo', [SettingController::class, 'updateSeo']);
        Route::get('settings/social', [SettingController::class, 'getSocial']);
        Route::put('settings/social', [SettingController::class, 'updateSocial']);
        Route::get('settings/content', [SettingController::class, 'getContent']);
        Route::put('settings/content', [SettingController::class, 'updateContent']);

        Route::get('dashboard', [DashboardController::class, 'ministry']);
        Route::get('stats/content', [DashboardController::class, 'statsContent']);
        Route::get('stats/engagement', [DashboardController::class, 'statsEngagement']);

        Route::post('faq/reorder', [FaqController::class, 'reorder']);
        Route::patch('faq/{id}/toggle', [FaqController::class, 'toggle']);
        Route::apiResource('faq', FaqController::class);

        Route::post('sliders/reorder', [SliderController::class, 'reorder']);
        Route::patch('sliders/{id}/toggle', [SliderController::class, 'toggle']);
        Route::apiResource('sliders', SliderController::class);

        Route::get('tags/popular', [TagController::class, 'popular']);
        Route::post('tags/attach', [TagController::class, 'attach']);
        Route::post('tags/detach', [TagController::class, 'detach']);
        Route::apiResource('tags', TagController::class)->except(['show']);

        Route::prefix('comments')->group(function () {
            Route::get('/', [ArticleCommentaireController::class, 'index']);
            Route::get('pending', [ArticleCommentaireController::class, 'pending']);
            Route::patch('{id}/approve', [ArticleCommentaireController::class, 'approve']);
            Route::patch('{id}/reject', [ArticleCommentaireController::class, 'reject']);
            Route::delete('{id}', [ArticleCommentaireController::class, 'destroy']);
            Route::post('bulk-approve', [ArticleCommentaireController::class, 'bulkApprove']);
            Route::post('bulk-reject', [ArticleCommentaireController::class, 'bulkReject']);
            Route::post('bulk-delete', [ArticleCommentaireController::class, 'bulkDelete']);
        });
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('change-password', [ProfileController::class, 'changePassword']);
        Route::get('activity', [ProfileController::class, 'activity']);
    });
});