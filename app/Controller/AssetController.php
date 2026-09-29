<?php

declare(strict_types=1);

namespace App\Controller;

use flight\Engine;

class AssetController
{
    public function __construct(private Engine $app)
    {
    }

    public function theme(string $theme, string $path): void
    {
        $this->send($this->app->get('root') . '/content/themes/' . $theme . '/assets/' . $path);
    }

    public function plugin(string $plugin, string $path): void
    {
        $this->send($this->app->get('root') . '/content/plugins/' . $plugin . '/assets/' . $path);
    }

    private function send(string $file): void
    {
        $real = realpath($file);
        $base = realpath(dirname($file, 3));
        if ($real === false || $base === false || !str_starts_with($real, $base) || !is_file($real)) {
            $this->app->halt(404, 'Not found');
        }
        $type = mime_content_type($real) ?: 'application/octet-stream';
        $this->app->response()->header('Content-Type', $type);
        $this->app->response()->header('Cache-Control', 'public, max-age=604800');
        $this->app->response()->write((string) file_get_contents($real));
    }
}
