<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Driver\Dto\ConstructorDtoFactory;
use Byfareska\Crud\Driver\Dto\DtoIds;
use Byfareska\Crud\Driver\Dto\DtoRecords;
use Byfareska\Crud\Tests\Unit\Fixtures\NumberedThingDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingCode;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingPriority;
use Byfareska\Crud\UseCase\InvalidIdException;
use Byfareska\Crud\UseCase\UseCaseExecutor;
use Byfareska\Crud\UseCase\UseCaseFailed;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * The id of an address as the "id" of a DTO is typed: a DTO shared with an API keeps its types.
 */
final class DtoIdsTest extends TestCase
{
    public function testAnIdOfTheTypeOfTheDto(): void
    {
        $type = static fn (string $name): ?\ReflectionType => new ReflectionFunction(match ($name) {
            'int' => static fn (int $id): int => $id,
            'enum' => static fn (ThingPriority $id): ThingPriority => $id,
            'code' => static fn (ThingCode $id): ThingCode => $id,
            default => static fn (string $id): string => $id,
        })->getParameters()[0]->getType();

        self::assertSame(7, DtoIds::of($type('int'), '7'));
        self::assertSame(ThingPriority::High, DtoIds::of($type('enum'), 'high'));
        self::assertEquals(ThingCode::fromString('ABC-12'), DtoIds::of($type('code'), 'ABC-12'));
        self::assertSame('7', DtoIds::of($type('text'), '7'));
        self::assertSame('7', DtoIds::of(null, '7'), 'Of no type: as it is.');

        $this->expectException(InvalidIdException::class);
        DtoIds::of($type('code'), 'nothing');
    }

    public function testADtoOfAnIdOfANumber(): void
    {
        $factory = new ConstructorDtoFactory(PropertyAccess::createPropertyAccessor());

        $dto = $factory->create(NumberedThingDto::class, '7');

        self::assertInstanceOf(NumberedThingDto::class, $dto);
        self::assertSame(7, $dto->id);
    }

    public function testAnAddressThatIsNoIdOfItsTypeIsNotFound(): void
    {
        $executor = new UseCaseExecutor(new ServiceLocator([]));
        $records = new DtoRecords($executor, PropertyAccess::createPropertyAccessor());

        try {
            $records->run(NumberedThingDto::class, 'abc');
            self::fail('A text for a number.');
        } catch (UseCaseFailed $failure) {
            self::assertSame(404, $failure->status);
        }
    }
}
