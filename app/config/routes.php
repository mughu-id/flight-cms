<?php

declare(strict_types=1);

use App\Controller\Admin\AppearanceController;
use App\Controller\Admin\CommentController as AdminCommentController;
use App\Controller\Admin\ContentController;
use App\Controller\Admin\DashboardController;
use App\Controller\Admin\FieldGroupController;
use App\Controller\Admin\MediaController;
use App\Controller\Admin\PluginController;
use App\Controller\Admin\PostTypeController;
use App\Controller\Admin\RemoteController;
use App\Controller\Admin\SettingsController;
use App\Controller\Admin\TermController;
use App\Controller\Admin\ToolsController;
use App\Controller\Admin\UserController;
use App\Controller\Api\ApiController;
use App\Controller\AssetController;
use App\Controller\AuthController;
use App\Controller\CommentController;
use App\Controller\FeedController;
use App\Controller\FrontController;
use App\Controller\InstallController;
use App\Controller\PreviewController;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use flight\net\Router;

/** @var flight\Engine $app */
$router = $app->router();

$router->group('', static function (Router $router) use ($app): void {
    if (!$app->get('installed')) {
        $router->get('/install', [InstallController::class, 'form']);
        $router->post('/install', [InstallController::class, 'run']);
        $router->get('*', static function () use ($app): void {
            $app->redirect('/install');
        });
        return;
    }

    $router->get('/admin/login', [AuthController::class, 'loginForm']);
    $router->post('/admin/login', [AuthController::class, 'login']);
    $router->get('/admin/forgot-password', [AuthController::class, 'forgotForm']);
    $router->post('/admin/forgot-password', [AuthController::class, 'forgot']);
    $router->get('/admin/reset-password/@token', [AuthController::class, 'resetForm']);
    $router->post('/admin/reset-password/@token', [AuthController::class, 'reset']);
    $router->get('/admin/register', [AuthController::class, 'registerForm']);
    $router->post('/admin/register', [AuthController::class, 'register']);

    $router->group('/admin', static function (Router $router): void {
        $router->post('/logout', [AuthController::class, 'logout']);
        $router->get('', [DashboardController::class, 'dashboard']);
        $router->get('/', [DashboardController::class, 'dashboard']);

        $router->get('/users', [UserController::class, 'index']);
        $router->get('/users/new', [UserController::class, 'create']);
        $router->post('/users', [UserController::class, 'store']);
        $router->get('/users/@id:[0-9]+', [UserController::class, 'edit']);
        $router->post('/users/@id:[0-9]+', [UserController::class, 'update']);
        $router->post('/users/@id:[0-9]+/delete', [UserController::class, 'delete']);
        $router->get('/profile', [UserController::class, 'profile']);
        $router->post('/profile', [UserController::class, 'updateProfile']);

        $router->get('/remote', [RemoteController::class, 'index']);
        $router->post('/remote/tokens', [RemoteController::class, 'createToken']);
        $router->post('/remote/tokens/@id:[0-9]+/delete', [RemoteController::class, 'deleteToken']);
        $router->get('/settings/@section', [SettingsController::class, 'form']);
        $router->post('/settings/@section', [SettingsController::class, 'save']);

        $router->get('/terms/@taxonomy', [TermController::class, 'index']);
        $router->post('/terms/@taxonomy', [TermController::class, 'store']);
        $router->post('/terms/@taxonomy/@id:[0-9]+', [TermController::class, 'update']);
        $router->post('/terms/@taxonomy/@id:[0-9]+/delete', [TermController::class, 'delete']);

        $router->get('/media', [MediaController::class, 'index']);
        $router->post('/media/upload', [MediaController::class, 'upload']);
        $router->post('/media/@id:[0-9]+', [MediaController::class, 'update']);
        $router->post('/media/@id:[0-9]+/delete', [MediaController::class, 'delete']);

        $router->get('/content/@type', [ContentController::class, 'index']);
        $router->get('/content/@type/new', [ContentController::class, 'create']);
        $router->post('/content/@type', [ContentController::class, 'store']);
        $router->post('/content/@type/bulk', [ContentController::class, 'bulk']);
        $router->post('/content/@type/@id:[0-9]+/feature', [ContentController::class, 'feature']);
        $router->get('/content/@type/@id:[0-9]+', [ContentController::class, 'edit']);
        $router->post('/content/@type/@id:[0-9]+', [ContentController::class, 'update']);
        $router->post('/content/@type/@id:[0-9]+/autosave', [ContentController::class, 'autosave']);
        $router->get('/content/@type/@id:[0-9]+/revisions', [ContentController::class, 'revisions']);
        $router->post('/content/@type/@id:[0-9]+/revisions/@rev:[0-9]+/restore', [ContentController::class, 'restore']);

        $router->get('/comments', [AdminCommentController::class, 'index']);
        $router->post('/comments/bulk', [AdminCommentController::class, 'bulk']);

        $router->get('/appearance/themes', [AppearanceController::class, 'themes']);
        $router->post('/appearance/themes/@slug/activate', [AppearanceController::class, 'activate']);
        $router->get('/appearance/customize', [AppearanceController::class, 'customize']);
        $router->post('/appearance/customize', [AppearanceController::class, 'saveCustomize']);
        $router->get('/appearance/sidebar', [AppearanceController::class, 'sidebar']);
        $router->post('/appearance/sidebar', [AppearanceController::class, 'saveSidebar']);
        $router->get('/appearance/menus', [AppearanceController::class, 'menus']);
        $router->post('/appearance/menus', [AppearanceController::class, 'createMenu']);
        $router->post('/appearance/menus/@id:[0-9]+', [AppearanceController::class, 'saveMenu']);
        $router->post('/appearance/menus/@id:[0-9]+/delete', [AppearanceController::class, 'deleteMenu']);

        $router->get('/plugins', [PluginController::class, 'index']);
        $router->post('/plugins/@slug/activate', [PluginController::class, 'activate']);
        $router->post('/plugins/@slug/deactivate', [PluginController::class, 'deactivate']);
        $router->post('/plugins/@slug/delete', [PluginController::class, 'delete']);

        $router->get('/post-types', [PostTypeController::class, 'index']);
        $router->get('/post-types/new', [PostTypeController::class, 'create']);
        $router->post('/post-types', [PostTypeController::class, 'store']);
        $router->get('/post-types/@slug', [PostTypeController::class, 'edit']);
        $router->post('/post-types/@slug', [PostTypeController::class, 'update']);
        $router->post('/post-types/@slug/delete', [PostTypeController::class, 'delete']);

        $router->get('/field-groups', [FieldGroupController::class, 'index']);
        $router->get('/field-groups/new', [FieldGroupController::class, 'create']);
        $router->post('/field-groups', [FieldGroupController::class, 'store']);
        $router->get('/field-groups/@id:[0-9]+', [FieldGroupController::class, 'edit']);
        $router->post('/field-groups/@id:[0-9]+', [FieldGroupController::class, 'update']);
        $router->post('/field-groups/@id:[0-9]+/delete', [FieldGroupController::class, 'delete']);

        $router->get('/tools', [ToolsController::class, 'index']);
        $router->get('/tools/redirects', [ToolsController::class, 'redirects']);
        $router->get('/tools/log', [ToolsController::class, 'log']);
        $router->post('/tools/backup', [ToolsController::class, 'backup']);
        $router->post('/tools/optimize', [ToolsController::class, 'optimize']);
        $router->post('/tools/purge-cache', [ToolsController::class, 'purgeCache']);
        $router->post('/tools/maintenance', [ToolsController::class, 'maintenance']);
        $router->post('/tools/logging', [ToolsController::class, 'saveLog']);
        $router->post('/tools/logging/clear', [ToolsController::class, 'clearLog']);
        $router->post('/tools/redirects', [ToolsController::class, 'saveRedirect']);
        $router->post('/tools/redirects/@id:[0-9]+/delete', [ToolsController::class, 'deleteRedirect']);
    }, [AuthMiddleware::class, CsrfMiddleware::class]);

    $router->group('/api/v1', static function (Router $router): void {
        $router->get('', [ApiController::class, 'index']);
        $router->get('/', [ApiController::class, 'index']);
        $router->post('/posts', [ApiController::class, 'importPost']);
        $router->get('/content/@type', [ApiController::class, 'contentIndex']);
        $router->get('/content/@type/@id:[0-9]+', [ApiController::class, 'contentShow']);
        $router->post('/content/@type', [ApiController::class, 'contentCreate']);
        $router->put('/content/@type/@id:[0-9]+', [ApiController::class, 'contentUpdate']);
        $router->patch('/content/@type/@id:[0-9]+', [ApiController::class, 'contentUpdate']);
        $router->delete('/content/@type/@id:[0-9]+', [ApiController::class, 'contentDelete']);
        $router->get('/terms/@taxonomy', [ApiController::class, 'terms']);
        $router->get('/media', [ApiController::class, 'media']);
        $router->get('/comments', [ApiController::class, 'comments']);
        $router->get('/menus/@location', [ApiController::class, 'menu']);
        $router->get('/users/me', [ApiController::class, 'me']);
        $router->get('/settings', [ApiController::class, 'settings']);
        $router->post('/settings', [ApiController::class, 'settings']);
        $router->get('/search', [ApiController::class, 'search']);
    }, [CorsMiddleware::class, RateLimitMiddleware::class, ApiAuthMiddleware::class]);

    $router->get('/preview/@id:[0-9]+', [PreviewController::class, 'show']);
    $router->post('/comments', [CommentController::class, 'store']);
    $router->get('/feed', [FeedController::class, 'feed']);
    $router->get('/robots.txt', [FeedController::class, 'robots']);
    $router->get('/sitemap.xml', [FeedController::class, 'sitemap']);
    $router->get('/sitemap-@name.xml', [FeedController::class, 'sitemapName']);

    $router->get('/', [FrontController::class, 'home']);
    $router->get('/page/@n:[0-9]+', [FrontController::class, 'home']);
    $router->get('/search', [FrontController::class, 'search']);
    $router->get('/search/@query', [FrontController::class, 'search']);
    $router->get('/author/@username', [FrontController::class, 'author']);
    $router->get('/@year:[0-9]{4}/@month:[0-9]{2}/@day:[0-9]{2}', [FrontController::class, 'date']);
    $router->get('/@year:[0-9]{4}/@month:[0-9]{2}', [FrontController::class, 'date']);
    $router->get('/@year:[0-9]{4}', [FrontController::class, 'date']);
    $router->get('/category/@slug', static fn (string $slug) => $app->make(FrontController::class)->term('category', $slug));
    $router->get('/tag/@slug', static fn (string $slug) => $app->make(FrontController::class)->term('tag', $slug));
    $router->get('/assets/themes/@theme/@path', [AssetController::class, 'theme']);
    $router->get('/assets/plugins/@plugin/@path', [AssetController::class, 'plugin']);

    $app->get('hooks')->doAction('routes.register', $router);
    $app->get('hooks')->doAction('api.routes.register', $router);

    $router->get('*', [FrontController::class, 'resolve']);
}, [SecurityHeadersMiddleware::class]);
