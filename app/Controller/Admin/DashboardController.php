<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Controller;
use App\Core\AdminMenu;
use App\Security\Capabilities;

class DashboardController extends Controller
{
    public function dashboard(): void
    {
        $db = $this->app->db();
        $counts = [
            'posts' => (int) $db->fetchField("SELECT COUNT(*) FROM posts WHERE type = 'post' AND status = 'publish'"),
            'pages' => (int) $db->fetchField("SELECT COUNT(*) FROM posts WHERE type = 'page' AND status = 'publish'"),
            'comments' => (int) $db->fetchField("SELECT COUNT(*) FROM comments WHERE status = 'pending'"),
            'media' => (int) $db->fetchField('SELECT COUNT(*) FROM media'),
        ];
        $recent = $db->fetchAll(
            "SELECT id, title, type, status, updated_at FROM posts ORDER BY updated_at DESC LIMIT 6"
        );
        $statuses = \App\Support\Row::all($db->fetchAll(
            'SELECT status, COUNT(*) AS n FROM posts GROUP BY status ORDER BY n DESC'
        ));
        $this->admin('admin/dashboard', [
            'title' => 'Dashboard',
            'counts' => $counts,
            'recent' => \App\Support\Row::all($recent),
            'activity' => $this->activity(14),
            'statuses' => $this->donut($statuses),
        ]);
    }

    /** @return array{points: list<array{label: string, posts: int, comments: int}>, posts: int, comments: int} */
    private function activity(int $days): array
    {
        $db = $this->app->db();
        $start = gmdate('Y-m-d', time() - ($days - 1) * 86400);
        $posts = $this->daily($db, 'posts', $start);
        $comments = $this->daily($db, 'comments', $start);
        $points = [];
        $postsTotal = 0;
        $commentsTotal = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $stamp = strtotime($start . " +{$i} days UTC");
            $day = gmdate('Y-m-d', $stamp);
            $postsN = $posts[$day] ?? 0;
            $commentsN = $comments[$day] ?? 0;
            $postsTotal += $postsN;
            $commentsTotal += $commentsN;
            $points[] = ['label' => gmdate('M j', $stamp), 'posts' => $postsN, 'comments' => $commentsN];
        }
        return ['points' => $points, 'posts' => $postsTotal, 'comments' => $commentsTotal];
    }

    /** @param list<array<string, mixed>> $rows */
    private function donut(array $rows): array
    {
        $total = array_sum(array_map(static fn (array $row): int => (int) $row['n'], $rows)) ?: 1;
        $offset = 0.0;
        $circ = 100.0;
        $slices = [];
        foreach ($rows as $row) {
            $pct = round(((int) $row['n'] / $total) * 100, 1);
            $slices[] = [
                'status' => (string) $row['status'],
                'n' => (int) $row['n'],
                'pct' => $pct,
                'dash' => $pct . ' ' . ($circ - $pct),
                'offset' => round(-$offset, 1),
            ];
            $offset += $pct;
        }
        return ['total' => $total === 1 && $rows === [] ? 0 : array_sum(array_column($slices, 'n')), 'slices' => $slices];
    }

    /** @return array<string, int> */
    private function daily(\flight\database\SimplePdo $db, string $table, string $start): array
    {
        $rows = \App\Support\Row::all($db->fetchAll(
            "SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS n FROM {$table} WHERE created_at >= ? GROUP BY day",
            [$start . ' 00:00:00']
        ));
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['day']] = (int) $row['n'];
        }
        return $map;
    }

    /** @param array<string, mixed> $data */
    protected function admin(string $view, array $data = []): void
    {
        $caps = $this->app->get('capabilities');
        $menu = $caps instanceof Capabilities ? (new AdminMenu($this->app->get('hooks'), $caps))->items() : [];
        $this->render($view, $data + ['menu' => $menu, 'current' => $this->app->request()->url]);
    }

    protected function forbid(string $capability): void
    {
        $caps = $this->app->get('capabilities');
        if (!$caps instanceof Capabilities || !$caps->can($capability)) {
            $this->app->halt(403, 'You cannot do that.');
        }
    }
}
