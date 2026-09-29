<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Service\Mailer;
use App\Service\RateLimiter;

class CommentController extends Controller
{
    public function store(): void
    {
        $postId = (int) $this->input('post_id', '0');
        $post = $this->app->make(PostRepository::class)->find($postId);
        if ($post === null || $post['comment_status'] !== 'open' || $post['status'] !== 'publish') {
            $this->app->halt(403, 'Comments closed');
        }
        $ip = (string) ($this->app->request()->ip ?? '0.0.0.0');
        if (!$this->app->make(RateLimiter::class)->allow('comment:' . $ip, 5, 600)) {
            $this->flash->set('error', 'Too many comments. Slow down.');
            $this->redirect($this->input('redirect', '/'));
        }
        if ($this->input('website') !== '') {
            $this->redirect($this->input('redirect', '/'));
        }
        $started = (int) $this->input('started', '0');
        if ($started > 0 && time() - $started < 3) {
            $this->redirect($this->input('redirect', '/'));
        }
        $content = $this->input('content');
        $name = $this->input('author_name');
        $email = $this->input('author_email');
        $user = null;
        $caps = $this->app->get('capabilities');
        if ($caps && $caps->userId()) {
            $user = $this->app->make(\App\Repository\UserRepository::class)->find($caps->userId());
            $name = (string) $user['display_name'];
            $email = (string) $user['email'];
        }
        if ($content === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash->set('error', 'Name, email and comment are required.');
            $this->redirect($this->input('redirect', '/'));
        }
        $content = strip_tags($content);
        $links = preg_match_all('#https?://#i', $content) ?: 0;
        if ($links > (int) $this->settings->get('comment_max_links', 2)) {
            $this->flash->set('error', 'Too many links.');
            $this->redirect($this->input('redirect', '/'));
        }
        $disallowed = array_filter(array_map('trim', explode("\n", (string) $this->settings->get('comment_disallowed', ''))));
        foreach ($disallowed as $word) {
            if ($word !== '' && stripos($content, $word) !== false) {
                $this->flash->set('error', 'Comment rejected.');
                $this->redirect($this->input('redirect', '/'));
            }
        }
        $status = 'pending';
        $repo = $this->app->make(CommentRepository::class);
        if (!$this->settings->get('comment_moderation', true) && ($user || $repo->hasApprovedFrom($email))) {
            $status = 'approved';
        }
        $payload = apply_filters('comment.pre_save', [
            'post_id' => $postId,
            'parent_id' => (int) $this->input('parent_id', '0'),
            'user_id' => $user['id'] ?? null,
            'author_name' => $name,
            'author_email' => $email,
            'author_url' => $this->input('author_url'),
            'ip' => $ip,
            'user_agent' => substr((string) ($this->app->request()->user_agent ?? ''), 0, 255),
            'content' => $content,
            'status' => $status,
        ]);
        if ($payload === false) {
            $this->redirect($this->input('redirect', '/'));
        }
        $id = $repo->create($payload);
        $this->app->get('hooks')->doAction('comment.posted', $repo->find($id));
        try {
            $this->app->make(Mailer::class)->send(
                (string) $this->settings->get('mail_from', 'cms@localhost'),
                'New comment on ' . $post['title'],
                '<p>' . e($name) . ' wrote:</p><p>' . e($content) . '</p>'
            );
        } catch (\Throwable) {
        }
        $this->flash->set('success', $status === 'approved' ? 'Comment posted.' : 'Comment awaiting moderation.');
        $this->redirect($this->input('redirect', '/'));
    }
}
