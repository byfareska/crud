<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Routing;

use Byfareska\Crud\Routing\PageParameters;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A job of a queue runs within the parameters of the page it was sent from.
 */
final class PageParametersTest extends TestCase
{
    public function testWorkIsDoneWithinTheParametersItCarries(): void
    {
        $parameters = new PageParameters();

        $seen = $parameters->within(['tenant' => 'acme'], static fn (): ?string => $parameters->get('tenant'));

        self::assertSame('acme', $seen);
        self::assertSame([], $parameters->all(), 'Those there were, after it.');

        try {
            $parameters->within(['tenant' => 'acme'], static fn (): never => throw new RuntimeException('failed'));
        } catch (RuntimeException) {
        }
        self::assertSame([], $parameters->all(), 'Also when it fails.');
    }
}
