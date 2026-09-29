<?php

declare(strict_types=1);

namespace App\Service;

use flight\database\SimplePdo;
use App\Support\Row;

class RateLimiter
{
    public function __construct(private SimplePdo $db)
    {
    }

    public function allow(string $bucket, int $max, int $windowSeconds): bool
    {
        $now = time();
        $row = Row::one($this->db->fetchRow('SELECT hits, reset_at FROM rate_limits WHERE bucket = ?', [$bucket]));
        if ($row === null || (int) $row['reset_at'] <= $now) {
            $this->db->runQuery(
                'INSERT INTO rate_limits (bucket, hits, reset_at) VALUES (?, 1, ?)
                 ON CONFLICT(bucket) DO UPDATE SET hits = 1, reset_at = excluded.reset_at',
                [$bucket, $now + $windowSeconds]
            );
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        $this->db->runQuery('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$bucket]);
        return true;
    }
}
