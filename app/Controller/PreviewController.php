<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\ThemeManager;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;

class PreviewController extends Controller
{
    public function show(string $id): void
    {
        $caps = $this->app->get('capabilities');
        if ($caps === null || $caps->userId() === null) {
            $this->redirect('/admin/login');
        }
        $post = $this->app->make(PostRepository::class)->find((int) $id);
        if ($post === null) {
            $this->app->halt(404, 'Not found');
        }
        $own = (int) $post['author_id'] === $caps->userId();
        $noun = $post['type'] === 'page' ? 'pages' : 'posts';
        if (!$caps->can($own ? 'edit_' . $noun : 'edit_others_' . $noun)) {
            $this->app->halt(403, 'You cannot preview this.');
        }
        $type = (string) $post['type'];
        $templates = $type === 'page'
            ? array_filter(['page-' . $post['template'], 'page-' . $post['slug'], 'page', 'singular', 'index'])
            : ['single-' . $type . '-' . $post['slug'], 'single-' . $type, 'single', 'singular', 'index'];
        $comments = $this->app->make(CommentRepository::class)->forPost((int) $post['id']);
        $manager = $this->app->make(ThemeManager::class);
        echo $manager->render($templates, [
            'post' => $post,
            'comments' => $comments,
            'preview' => true,
            'site_title' => (string) $this->settings->get('site_title', ''),
            'nonce' => $this->app->get('csp_nonce'),
        ]);
    }
}
