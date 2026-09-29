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
        if ($src === '') {
            $src = $this->firstSrc($html);
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
                $converted = $this->asJpeg($bytes);
                if ($converted === null) {
                    return 0;
                }
                $bytes = $converted;
                $mime = 'image/jpeg';
                $ext = 'jpg';
            }
            $dir = gmdate('Y/m');
            $abs = $root . '/uploads/' . $dir;
            if (!is_dir($abs)) {
                mkdir($abs, 0775, true);
            }
            $name = bin2hex(random_bytes(8)) . '.' . $ext;
            $relative = $dir . '/' . $name;
            file_put_contents($abs . '/' . $name, $bytes);
            $width = null;
            $height = null;
            $sizes = [];
            try {
                [$width, $height] = $this->images->dimensions($abs . '/' . $name);
                $sizes = $this->images->sizes($abs . '/' . $name, $relative);
            } catch (\Throwable) {
            }
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
                'sizes' => $sizes,
            ]);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function firstSrc(string $html): string
    {
        if (preg_match('/<img\b[^>]*\b(?:src|data-src)=["\']([^"\']+)["\']/i', $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES);
        }
        return '';
    }

    private function asJpeg(string $bytes): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return null;
        }
        ob_start();
        imagejpeg($img, null, 85);
        imagedestroy($img);
        $out = ob_get_clean();
        return is_string($out) && $out !== '' ? $out : null;
    }

    private function bytes(string $root, string $src): ?string
    {
        if (str_starts_with($src, '//')) {
            $src = 'https:' . $src;
        }
        $path = (string) (parse_url($src, PHP_URL_PATH) ?? '');
        if (str_starts_with($src, '/uploads/') || str_contains($path, '/uploads/')) {
            $file = $root . (str_starts_with($path, '/uploads/') ? $path : $src);
            if (is_file($file)) {
                return (string) file_get_contents($file);
            }
        }
        if (!preg_match('#^https?://#i', $src)) {
            return null;
        }
        $src = $this->smallSource($src);
        if (function_exists('curl_init')) {
            $ch = curl_init($src);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]);
            $got = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (is_string($got) && $got !== '' && $code < 400) {
                return $got;
            }
        }
        $got = @file_get_contents($src, false, stream_context_create([
            'http' => ['timeout' => 12, 'follow_location' => 1, 'header' => "User-Agent: Mozilla/5.0\r\n"],
        ]), 0, 6_000_000);
        return is_string($got) && $got !== '' ? $got : null;
    }

    private function smallSource(string $src): string
    {
        if (preg_match('#^(https://upload\.wikimedia\.org/wikipedia/commons/)((?:[0-9a-f]/){1,2})([^/]+\.(?:jpe?g|png|webp))$#i', $src, $m)) {
            return $m[1] . 'thumb/' . $m[2] . $m[3] . '/960px-' . $m[3];
        }
        return $src;
    }
}
