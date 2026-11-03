<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\UseCase\MapsToRequest;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * The edit form of a thing: an enum, a date and the enum kept as text, as a form of choices of strings has it.
 */
final class EditThingDto implements MapsToRequest
{
    public string $id;

    public ?ThingPriority $priority = null;

    public string $priorityText = '';

    public ?DateTimeInterface $dueAt = null;

    public ?DateTimeImmutable $startsAt = null;

    public function toRequest(): self
    {
        return $this;
    }
}
