<?php

require __DIR__ . '/articles.php';

$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/storage/database/cms.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$find = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
$update = $pdo->prepare('UPDATE posts SET content = ? WHERE id = ?');
$fts = $pdo->prepare('UPDATE posts_fts SET body = ? WHERE rowid = ?');

foreach (dummy_articles() as $slug => $html) {
    $find->execute([$slug]);
    $id = $find->fetchColumn();
    if (!$id) {
        echo "missing $slug\n";
        continue;
    }
    $update->execute([$html, $id]);
    $fts->execute([trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? ''), $id]);
    echo "updated $slug\n";
}

foreach (glob(dirname(__DIR__) . '/storage/cache/pages/*') ?: [] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
