<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizer
{
    private SymfonySanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            ->allowElement('figure', ['class'])
            ->allowElement('figcaption')
            ->allowElement('iframe', ['src', 'width', 'height', 'allow', 'allowfullscreen'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->forceHttpsUrls(false);
        $this->sanitizer = new SymfonySanitizer($config);
    }

    public function clean(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
