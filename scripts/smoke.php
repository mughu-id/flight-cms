<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$db = $root . '/storage/database/cms.sqlite';
foreach ([$db, $db . '-wal', $db . '-shm'] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
@unlink(sys_get_temp_dir() . '/cms-smoke.txt');

function hit(string $method, string $path, array $post = []): array
{
    $ch = curl_init('http://127.0.0.1:8123' . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/cms-smoke.txt',
        CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/cms-smoke.txt',
    ]);
    if ($post !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $body = substr($raw, (int) strpos($raw, "\r\n\r\n") + 4);
    return [$status, $body];
}

$server = proc_open(
    'php -S 127.0.0.1:8123 -t public public/router.php',
    [1 => ['file', sys_get_temp_dir() . '/cms-server.log', 'w'], 2 => ['file', sys_get_temp_dir() . '/cms-server.err', 'w']],
    $pipes,
    $root
);
usleep(400000);

[$s, $b] = hit('GET', '/install');
preg_match('/name="csrf" value="([^"]+)"/', $b, $m);
hit('POST', '/install', ['csrf' => $m[1] ?? '', 'site_title' => 'Smoke', 'username' => 'admin', 'email' => 'a@b.co', 'password' => 'secret123', 'display_name' => 'Admin']);
[$s, $b] = hit('GET', '/admin/login');
preg_match('/name="csrf" value="([^"]+)"/', $b, $m);
hit('POST', '/admin/login', ['csrf' => $m[1] ?? '', 'login' => 'admin', 'password' => 'secret123']);

$checks = [
    ['GET', '/admin', 'Dashboard'],
    ['GET', '/', 'Hello world'],
    ['GET', '/hello-world', 'Welcome to Flight CMS'],
    ['GET', '/admin/appearance/themes', 'Horizon'],
    ['GET', '/admin/appearance/menus', 'Menus'],
    ['GET', '/admin/comments', 'Comments'],
    ['GET', '/admin/plugins', 'Plugins'],
    ['GET', '/admin/post-types', 'Post types'],
    ['GET', '/admin/tools', 'Site Health'],
    ['GET', '/admin/settings/seo', 'SEO'],
    ['GET', '/feed', 'rss'],
    ['GET', '/sitemap.xml', 'sitemapindex'],
    ['GET', '/robots.txt', 'User-agent'],
    ['GET', '/api/v1/', 'Flight CMS API'],
    ['GET', '/api/v1/content/post', 'Hello world'],
    ['GET', '/search/Hello', 'Search'],
];

foreach ($checks as [$method, $path, $needle]) {
    [$status, $body] = hit($method, $path);
    $ok = $status < 400 && str_contains($body, $needle);
    echo ($ok ? 'OK' : 'FAIL') . " {$method} {$path} ({$status})\n";
    if (!$ok && $status >= 400) {
        echo substr($body, (int) strpos($body, '<title>'), 200) . "\n";
    }
}

proc_terminate($server);
echo "done\n";
