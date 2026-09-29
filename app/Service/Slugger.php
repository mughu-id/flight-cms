<?php

declare(strict_types=1);

namespace App\Service;

use Cocur\Slugify\Slugify;

class Slugger
{
    private Slugify $slugify;

    public function __construct()
    {
        $this->slugify = new Slugify(['lowercase' => true, 'separator' => '-']);
    }

    public function slug(string $text, string $fallback = 'item'): string
    {
        $slug = $this->slugify->slugify($text);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : $fallback;
    }
}
