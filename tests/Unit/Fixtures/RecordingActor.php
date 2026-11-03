<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Security\Actor;

/**
 * An actor with some roles, remembering what it was asked.
 */
final class RecordingActor implements Actor
{
    /** @var list<array{string, mixed}> */
    public array $asked = [];

    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly array $roles,
    ) {
    }

    public function id(): string
    {
        return '1';
    }

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        $this->asked[] = [$attribute, $subject];

        return in_array($attribute, $this->roles, true);
    }
}
