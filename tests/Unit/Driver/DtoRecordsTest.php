<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Driver\Dto\ConstructorDtoFactory;
use Byfareska\Crud\Driver\Dto\DtoRecords;
use Byfareska\Crud\Tests\Unit\Fixtures\EditThingDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ReadonlyThingDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ReadThingDto;
use Byfareska\Crud\Tests\Unit\Fixtures\RunUseCase;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingPriority;
use Byfareska\Crud\UseCase\UseCaseExecutor;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Byfareska\Crud\Tests\Unit\Fixtures\ShowThingDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

final class DtoRecordsTest extends TestCase
{
    public function testADtoOfAReadonlyIdIsMadeWithIt(): void
    {
        $factory = new ConstructorDtoFactory(PropertyAccess::createPropertyAccessor());

        $dto = $factory->create(ReadonlyThingDto::class, '7');

        self::assertSame('7', $dto->id);
        self::assertSame('7', $factory->create(ShowThingDto::class, '7')->id, 'A public one: written.');
    }

    public function testARecordIsItsPublicPropertiesAndWhatItsGettersSay(): void
    {
        $record = new class {
            public string $name = 'Mug';

            private int $stock = 3;

            private bool $active = true;

            public function getStock(): int
            {
                return $this->stock;
            }

            public function isActive(): bool
            {
                return $this->active;
            }

            public function getName(): string
            {
                return 'not this: the property says';
            }

            public function getPriceIn(string $currency): string
            {
                return '1 ' . $currency;
            }
        };

        self::assertSame(['name' => 'Mug', 'stock' => 3, 'active' => true], DtoRecords::propertiesOf($record));
        self::assertSame(['id' => 7], DtoRecords::propertiesOf(['id' => 7]));
        self::assertSame([], DtoRecords::propertiesOf(null));
    }

    public function testAnEditDtoGetsTheEnumsDatesAndObjectsItIsTypedFor(): void
    {
        $due = new DateTimeImmutable('2026-12-24 10:00');
        $read = [
            'priority' => ThingPriority::High,
            'priorityText' => ThingPriority::Low,
            'dueAt' => $due,
            // A date for a property of another class of dates: left out, not an error.
            'startsAt' => new \DateTime('2026-01-01'),
        ];
        $records = new DtoRecords(
            new UseCaseExecutor(new ServiceLocator([
                RunUseCase::class => static fn (): \Closure => static fn (): array => $read,
            ])),
            PropertyAccess::createPropertyAccessor(),
        );

        $dto = $records->editDto(EditThingDto::class, ReadThingDto::class, '7');

        self::assertInstanceOf(EditThingDto::class, $dto);
        self::assertSame(ThingPriority::High, $dto->priority);
        self::assertSame('low', $dto->priorityText, 'An enum to a string: its value.');
        self::assertSame($due, $dto->dueAt);
        self::assertNull($dto->startsAt);
    }
}
