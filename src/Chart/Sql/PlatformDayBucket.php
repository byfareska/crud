<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart\Sql;

use Byfareska\Crud\Chart\DayBucket;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * A {@see DayBucket} that {@see DoctrineDayBucket} picks for the platforms it supports. Tagged {@see self::TAG}; the
 * one with the highest priority that supports the platform wins.
 */
interface PlatformDayBucket extends DayBucket
{
    public const string TAG = 'byfareska_crud.platform_day_bucket';

    public function supports(AbstractPlatform $platform): bool;
}
