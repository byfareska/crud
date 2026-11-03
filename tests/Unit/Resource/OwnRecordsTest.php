<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\OwnRecordResolver;
use Byfareska\Crud\Resource\OwnRecords;
use Byfareska\Crud\Tests\Unit\Fixtures\ShowThingDto;
use PHPUnit\Framework\TestCase;

final class OwnRecordsTest extends TestCase
{
    public function testTheResolverOfTheResourceSaysWhichRecordItIs(): void
    {
        $access = self::createStub(CrudAccess::class);
        $access->method('ownId')->willReturn('user-7');
        $company = new readonly class implements OwnRecordResolver {
            public function resource(): string
            {
                return 'my_company';
            }

            public function ownId(AsCrud $crud): string
            {
                return 'company-3';
            }
        };
        $records = new OwnRecords($access, [$company]);

        self::assertSame('company-3', $records->idOf($this->own('my_company')));
        // Another own resource: the one signed in.
        self::assertSame('user-7', $records->idOf($this->own('my_account')));
    }

    private function own(string $name): AsCrud
    {
        return new AsCrud($name, '/' . $name, 'person', read: ShowThingDto::class, own: true);
    }
}
