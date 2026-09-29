<?php

declare(strict_types=1);

namespace App\Core;

class Permalinks
{
    public function __construct(private Settings $settings)
    {
    }

    /** @param array<string, mixed> $post */
    public function post(array $post): string
    {
        if ($post['type'] === 'page') {
            return (string) $post['path'];
        }
        $date = strtotime(((string) ($post['published_at'] ?? '')) . ' UTC') ?: time();
        $path = match ((string) $this->settings->get('permalink_structure', 'post')) {
            'day' => '/' . gmdate('Y/m/d', $date) . '/' . $post['slug'],
            'month' => '/' . gmdate('Y/m', $date) . '/' . $post['slug'],
            default => '/' . $post['slug'],
        };
        return (string) apply_filters('post.permalink', $path, $post);
    }

    public function term(array $term): string
    {
        $base = $term['taxonomy'] === 'tag'
            ? (string) $this->settings->get('tag_base', 'tag')
            : (string) $this->settings->get('category_base', 'category');
        return '/' . trim($base, '/') . '/' . $term['slug'];
    }
}
