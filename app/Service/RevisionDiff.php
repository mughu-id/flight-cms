<?php

declare(strict_types=1);

namespace App\Service;

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Renderer\RendererConstant;

class RevisionDiff
{
    public function html(string $old, string $new): string
    {
        return DiffHelper::calculate($old, $new, 'Inline', [
            'context' => 2,
            'ignoreCase' => false,
            'ignoreLineEnding' => true,
            'ignoreWhitespace' => false,
        ], [
            'detailLevel' => 'word',
            'lineNumbers' => false,
            'showHeader' => false,
            'separateBlock' => true,
            'wrapperClasses' => ['diff-wrapper'],
        ]);
    }
}
