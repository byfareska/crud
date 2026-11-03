<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Action;

use Byfareska\Crud\Action\BulkSelection;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use PHPUnit\Framework\TestCase;

final class BulkSelectionTest extends TestCase
{
    public function testTheRecordsChosenByTheirIds(): void
    {
        $selection = new BulkSelection($this->crud(), ['7', '8']);

        self::assertSame(['7', '8'], $selection->ids);
        self::assertSame(2, $selection->count);
        self::assertFalse($selection->allMatching);
    }

    public function testEveryMatchIsReadOnlyWhenAskedForAndOnce(): void
    {
        $read = 0;
        $selection = new BulkSelection(
            $this->crud(),
            ['ignored'],
            matching: static function () use (&$read): array {
                ++$read;

                return ['1', '2', '3'];
            },
            count: 3,
        );

        self::assertTrue($selection->allMatching);
        self::assertSame(3, $selection->count);
        self::assertSame(0, $read, 'A button counts without reading.');
        self::assertSame(['1', '2', '3'], $selection->ids);
        // Asked again: read once.
        // @phpstan-ignore staticMethod.alreadyNarrowedType (a property hook: asked again, not the value phpstan knows)
        self::assertSame(['1', '2', '3'], $selection->ids);
        self::assertSame(1, $read);
    }

    private function crud(): AsCrud
    {
        return new AsCrud('thing', '/things', 'box', list: ListThingsDto::class);
    }
}
