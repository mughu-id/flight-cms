<?php

declare(strict_types=1);

namespace App\Support;

use flight\util\Collection;

final class Row
{
    public static function one(mixed $row): ?array
    {
        if ($row === null || $row === false) {
            return null;
        }
        if ($row instanceof Collection) {
            return $row->getData();
        }
        return (array) $row;
    }

    /** @param array<int, mixed> $rows */
    public static function all(array $rows): array
    {
        return array_map(static fn (mixed $row): array => self::one($row) ?? [], $rows);
    }
}
