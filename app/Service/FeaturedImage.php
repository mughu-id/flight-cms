<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\MediaRepository;

/** Store an image and return its media id, for a post's featured image. */
class FeaturedImage
{
    public function __construct(private MediaRepository $media, private ImageProcessor $images)
    {
    }

    /** Use a given image URL, or the first image in the HTML. Returns 0 when nothing usable is found. */
    public function fromHtml(string $root, int $userId, string $html, string $src = ''): int
    {
        if ($src === '' && preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $match)) {
            $src = html_entity_decode($match[1], ENT_QUOTES);
        }
        if ($src === '') {
            return 0;
        }
        try {
            $bytes = $this->bytes($root, $src);
            if ($bytes === null) {
                return 0;
            }
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
            if ($ext === null) {
                return 0;
            }
            $dir = gmdate('Y/m');
            $abs = $root . '/uploads/' . $dir;
            if (!is_dir($abs)) {
                mkdir($abs, 0775, true);
            }
            $name = bin2hex(random_bytes(8)) . '.' . $ext;
            $relative = $dir . '/' . $name;
            file_put_contents($abs . '/' . $name, $bytes);
            [$width, $height] = $this->images->dimensions($abs . '/' . $name);
            return $this->media->create([
                'user_id' => $userId,
                'path' => $relative,
                'mime_type' => $mime,
                'size' => (int) filesize($abs . '/' . $name),
                'width' => $width,
                'height' => $height,
                'alt' => '',
                'title' => pathinfo(parse_url($src, PHP_URL_PATH) ?: $name, PATHINFO_FILENAME),
                'caption' => '',
                'sizes' => $this->images->sizes($abs . '/' . $name, $relative),
            ]);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function bytes(string $root, string $src): ?string
    {
        if (str_starts_with($src, '/uploads/')) {
            $file = $root . $src;
            return is_file($file) ? (string) file_get_contents($file) : null;
        }
        if (!preg_match('#^https?://#i', $src)) {
            return null;
        }
        $bytes = @file_get_contents($src, false, stream_context_create([
            'http' => ['timeout' => 8, 'follow_location' => 1, 'header' => "User-Agent: FlightCMS\r\n"],
        ]), 0, 6_000_000);
        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }
}
