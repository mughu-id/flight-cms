<?php

declare(strict_types=1);

namespace App\Core;

/** Drop generated caches. Uploads and the database are left alone. */
class Cache
{
    public static function clear(string $root): void
    {
        foreach (['storage/cache/pages', 'storage/cache/twig', 'uploads/thumbs'] as $dir) {
            $path = $root . '/' . $dir;
            if (is_dir($path)) {
                self::wipe($path);
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private static function wipe(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name === '.gitkeep') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                self::wipe($path);
                @rmdir($path);
                continue;
            }
            @unlink($path);
        }
    }
}
