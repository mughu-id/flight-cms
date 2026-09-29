<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Settings;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageProcessor
{
    private ImageManager $images;

    public function __construct(private Settings $settings)
    {
        $this->images = new ImageManager(new Driver());
    }

    /** @return array<string, array{path: string, width: int, height: int}> */
    public function sizes(string $absolute, string $relative): array
    {
        $image = $this->images->decodePath($absolute)->orient();
        $image->save($absolute);
        $out = [];
        $dir = dirname($absolute);
        $name = pathinfo($absolute, PATHINFO_FILENAME);
        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        $relDir = dirname($relative);
        foreach ((array) $this->settings->get('image_sizes', []) as $key => $size) {
            [$w, $h, $crop] = array_pad((array) $size, 3, false);
            $copy = $this->images->decodePath($absolute);
            $copy = $crop ? $copy->cover((int) $w, (int) $h) : $copy->scaleDown((int) $w, (int) $h);
            $file = $name . '-' . $key . '.' . $ext;
            $copy->save($dir . DIRECTORY_SEPARATOR . $file);
            $out[$key] = [
                'path' => ($relDir === '.' ? '' : $relDir . '/') . $file,
                'width' => $copy->width(),
                'height' => $copy->height(),
            ];
        }
        return $out;
    }

    /** @return array{0: int, 1: int} */
    public function dimensions(string $absolute): array
    {
        $image = $this->images->decodePath($absolute);
        return [$image->width(), $image->height()];
    }
}
