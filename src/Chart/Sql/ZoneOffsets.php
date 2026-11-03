<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart\Sql;

use DateTimeImmutable;
use DateTimeZone;

/**
 * For databases without time zones: a CASE that shifts a UTC column by the offset the zone had at that moment, one
 * branch per change of the offset from 1970 to a year from now (later dates get the last offset).
 *
 * @internal
 */
final class ZoneOffsets
{
    /**
     * @param callable(int): string $shifted the SQL of the period of the column moved by that many seconds
     * @param DateTimeZone|null $stored the zone the column holds its times in (null = UTC)
     */
    public static function sql(
        string $column,
        DateTimeZone $zone,
        callable $shifted,
        ?DateTimeZone $stored = null,
    ): string {
        $periods = self::periods($zone, $stored ?? new DateTimeZone('UTC'));
        $last = array_pop($periods);
        if ($periods === []) {
            return $shifted($last['offset']);
        }

        $sql = 'CASE';
        foreach ($periods as $period) {
            $sql .= sprintf(" WHEN %s < '%s' THEN %s", $column, $period['until'], $shifted($period['offset']));
        }

        return $sql . ' ELSE ' . $shifted($last['offset']) . ' END';
    }

    /**
     * The shift from the zone of the column to the zone of the periods, for each stretch of time the two keep the same
     * offsets; "until" is when the stretch ends, as the column has it (in its zone).
     *
     * @return non-empty-list<array{offset: int, until: string|null}> oldest first; "until" is "Y-m-d H:i:s"
     */
    private static function periods(DateTimeZone $zone, DateTimeZone $stored): array
    {
        $end = new DateTimeImmutable('+1 year');
        $moments = [0];
        foreach ([$zone, $stored] as $of) {
            foreach ($of->getTransitions(0, $end->getTimestamp()) ?: [] as $transition) {
                $moments[] = $transition['ts'];
            }
        }
        $moments = array_values(array_unique(array_filter($moments, static fn (int $ts): bool => $ts >= 0)));
        sort($moments);

        $periods = [];
        $offset = null;
        foreach ($moments as $ts) {
            $at = new DateTimeImmutable('@' . $ts);
            $shift = $zone->getOffset($at) - $stored->getOffset($at);
            if ($shift === $offset) {
                continue;
            }
            if ($offset !== null) {
                $periods[] = ['offset' => $offset, 'until' => $at->setTimezone($stored)->format('Y-m-d H:i:s')];
            }
            $offset = $shift;
        }
        $periods[] = ['offset' => $offset ?? $zone->getOffset($end) - $stored->getOffset($end), 'until' => null];

        return $periods;
    }
}
