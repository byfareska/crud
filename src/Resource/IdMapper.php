<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * Turns an id (an int, a string, a Uuid, ...) into text, the way an address carries it. The bundle's own is
 * {@see DefaultIdMapper}; an application with ids of its own kind aliases this interface to its service.
 */
interface IdMapper
{
    /**
     * @return string|null null = the value is no id
     */
    public function toId(mixed $value): ?string;
}
