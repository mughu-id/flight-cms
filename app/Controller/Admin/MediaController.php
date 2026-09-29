<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\MediaRepository;
use App\Service\ImageProcessor;

class MediaController extends DashboardController
{
    private const ALLOWED = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];

    public function index(): void
    {
        $this->forbid('upload_files');
        $query = $this->app->request()->query;
        $q = (string) ($query->q ?? '');
        $type = (string) ($query->type ?? '');
        $page = max(1, (int) ($query->page ?? 1));
        $result = $this->media()->list(['q' => $q, 'type' => $type], $page, 24);
        $this->admin('admin/media/index', [
            'title' => 'Media',
            'items' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'per_page' => 24,
            'q' => $q,
            'type' => $type,
        ]);
    }

    public function upload(): void
    {
        $this->forbid('upload_files');
        $files = $this->app->request()->files->file ?? null;
        if (!is_array($files) || ($files['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->app->json(['error' => 'No file'], 400);
            return;
        }
        $item = $this->store($files);
        if (is_string($item)) {
            $this->app->json(['error' => $item], 422);
            return;
        }
        $this->app->get('hooks')->doAction('media.uploaded', $item);
        $this->app->json(['data' => $item]);
    }

    public function update(string $id): void
    {
        $this->forbid('upload_files');
        $item = $this->require((int) $id);
        $this->media()->update((int) $item['id'], [
            'alt' => $this->input('alt'),
            'title' => $this->input('title'),
            'caption' => $this->input('caption'),
        ]);
        $this->flash->set('success', 'Saved.');
        $this->redirect('/admin/media');
    }

    public function delete(string $id): void
    {
        $this->forbid('upload_files');
        $item = $this->require((int) $id);
        $root = $this->app->get('root') . '/uploads/';
        @unlink($root . $item['path']);
        foreach ((array) $item['sizes'] as $size) {
            @unlink($root . $size['path']);
        }
        $this->media()->delete((int) $item['id']);
        $this->flash->set('success', 'Deleted.');
        $this->redirect('/admin/media');
    }

    /** @param array<string, mixed> $file */
    private function store(array $file): array|string
    {
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $allowed = $this->app->get('hooks')->applyFilters('upload.allowed_types', self::ALLOWED);
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
            return 'File type is not allowed.';
        }
        $dir = gmdate('Y/m');
        $abs = $this->app->get('root') . '/uploads/' . $dir;
        if (!is_dir($abs)) {
            mkdir($abs, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $relative = $dir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $abs . '/' . $name)) {
            return 'Could not store the file.';
        }
        $sizes = [];
        $width = null;
        $height = null;
        if (str_starts_with($mime, 'image/')) {
            $processor = $this->app->make(ImageProcessor::class);
            [$width, $height] = $processor->dimensions($abs . '/' . $name);
            $sizes = $processor->sizes($abs . '/' . $name, $relative);
        }
        $id = $this->media()->create([
            'user_id' => (int) $this->app->get('capabilities')->userId(),
            'path' => $relative,
            'mime_type' => $mime,
            'size' => (int) filesize($abs . '/' . $name),
            'width' => $width,
            'height' => $height,
            'alt' => '',
            'title' => pathinfo((string) $file['name'], PATHINFO_FILENAME),
            'caption' => '',
            'sizes' => $sizes,
        ]);
        return $this->media()->find($id) ?? [];
    }

    /** @return array<string, mixed> */
    private function require(int $id): array
    {
        $item = $this->media()->find($id);
        if ($item === null) {
            $this->app->halt(404, 'Not found');
        }
        return $item;
    }

    private function media(): MediaRepository
    {
        return $this->app->make(MediaRepository::class);
    }
}
