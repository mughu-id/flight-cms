<?php

declare(strict_types=1);

$root = dirname(__DIR__);

foreach ([
    'storage/database',
    'storage/cache/twig',
    'storage/cache/pages',
    'storage/logs',
    'storage/sessions',
    'storage/backups',
    'public/uploads',
    'public/assets/vendor',
    'app/config',
] as $dir) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

$config = $root . '/app/config/config.php';
$sample = $root . '/app/config/config.sample.php';
if (!is_file($config) && is_file($sample)) {
    $key = bin2hex(random_bytes(32));
    $body = str_replace('__APP_KEY__', $key, (string) file_get_contents($sample));
    file_put_contents($config, $body);
}

$copies = [
    'vendor/twbs/bootstrap-icons/font' => 'public/assets/vendor/bootstrap-icons',
    'vendor/tinymce/tinymce' => 'public/assets/vendor/tinymce',
];

foreach ($copies as $from => $to) {
    $src = $root . '/' . $from;
    $dst = $root . '/' . $to;
    if (!is_dir($src)) {
        continue;
    }
    copy_tree($src, $dst);
}

function copy_tree(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0775, true);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0775, true);
            }
            continue;
        }
        if (!is_file($target) || filemtime($item->getPathname()) > filemtime($target)) {
            copy($item->getPathname(), $target);
        }
    }
}
