<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(\S+)/i', (string) ($_SERVER['HTTP_CGI_AUTHORIZATION'] ?? ''), $match)) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $match[1];
}
$first = explode('/', trim($path, '/'))[0] ?? '';
if (in_array($first, ['app', 'vendor', 'storage', 'database', 'scripts', 'tests', 'resources', 'content', 'node_modules', 'deploy'], true)
    || in_array($path, ['/composer.json', '/composer.lock', '/package.json', '/package-lock.json', '/phpunit.xml', '/artisan'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($file)) {
    if (str_starts_with(str_replace('\\', '/', $path), '/uploads/')) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'phtml', 'phar', 'htaccess'], true)) {
            http_response_code(403);
            echo 'Forbidden';
            return true;
        }
    }
    return false;
}

require __DIR__ . '/index.php';
