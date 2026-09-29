<?php

declare(strict_types=1);

$all = [
    'read',
    'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts',
    'delete_others_posts', 'delete_published_posts',
    'edit_pages', 'edit_others_pages', 'edit_published_pages', 'publish_pages', 'delete_pages',
    'delete_others_pages', 'delete_published_pages',
    'upload_files', 'moderate_comments', 'manage_categories', 'manage_options', 'edit_theme_options',
    'switch_themes', 'activate_plugins', 'edit_users', 'create_users', 'delete_users', 'list_users',
    'unfiltered_html', 'manage_post_types',
];

$editor = array_values(array_diff($all, [
    'manage_options', 'switch_themes', 'activate_plugins', 'edit_users', 'create_users',
    'delete_users', 'manage_post_types',
]));

$author = [
    'read', 'edit_posts', 'publish_posts', 'delete_posts', 'upload_files',
];

return [
    'administrator' => $all,
    'editor' => $editor,
    'author' => $author,
    'subscriber' => ['read'],
    'labels' => [
        'administrator' => 'Administrator',
        'editor' => 'Editor',
        'author' => 'Author',
        'subscriber' => 'Subscriber',
    ],
];
