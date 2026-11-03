<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudDefinitionExtension;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudResourceProvider;
use Byfareska\Crud\Routing\CrudRoutes;
use Byfareska\Crud\Tests\Unit\Fixtures\Gadget;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingCrud;
use Closure;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CrudRegistryTest extends TestCase
{
    public function testAProviderDeclaresResourcesNextToTheAttributes(): void
    {
        $registry = new CrudRegistry([ThingCrud::class], providers: [$this->provider('report_sales', 'report_stock')]);

        self::assertSame(['thing', 'report_sales', 'report_stock'], array_keys($registry->declared()));
        self::assertSame('/report_stock', $registry->get('report_stock')->path);
        // Its routes are made like those of an attribute, and rebuilt when its class changes.
        self::assertNotNull(new CrudRoutes($registry)->of('panel')->get('crud_report_sales_list'));
        self::assertContains(new \ReflectionClass(self::class)->getFileName(), $registry->files());
    }

    public function testAProviderDoesNotTakeTheNameOfAnotherResource(): void
    {
        $registry = new CrudRegistry([ThingCrud::class], providers: [$this->provider('thing')]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"thing" is declared twice');

        $registry->declared();
    }

    public function testAProviderYieldsResourcesOnly(): void
    {
        $provider = new class implements CrudResourceProvider {
            public function resources(): iterable
            {
                // @phpstan-ignore generator.valueType (what it yields is the point)
                yield new stdClass();
            }
        };

        $this->expectException(LogicException::class);

        new CrudRegistry(providers: [$provider])->declared();
    }

    public function testAnExtensionChangesTheResourceOfTheRequestNotTheDeclaration(): void
    {
        $margin = $this->extension('thing', static fn (AsCrud $crud): AsCrud => $crud->with(
            columns: [...$crud->columns, $crud->column('margin', 'money')],
        ));
        $star = static fn (AsCrud $crud): AsCrud => $crud->with(icon: 'star');
        $everyIcon = $this->extension(CrudDefinitionExtension::EVERY, $star);
        $registry = new CrudRegistry(
            [ThingCrud::class],
            providers: [$this->provider('report_sales')],
            extensions: [$margin, $everyIcon],
        );

        self::assertSame(['margin'], array_column($registry->get('thing')->columns, 'field'));
        self::assertSame('star', $registry->get('thing')->icon);
        self::assertSame('star', $registry->get('report_sales')->icon);
        self::assertSame([], $registry->get('report_sales')->columns);
        self::assertSame($registry->get('thing'), $registry->all()['thing']);
        // The routes are made of the declarations.
        self::assertSame([], $registry->declared()['thing']->columns);
        self::assertSame('box', $registry->declared()['thing']->icon);
    }

    public function testAnExtensionIsAskedOnceARequest(): void
    {
        $asked = 0;
        $extension = $this->extension('thing', static function (AsCrud $crud) use (&$asked): AsCrud {
            ++$asked;

            return $crud->with(priority: $asked);
        });
        $registry = new CrudRegistry([ThingCrud::class], extensions: [$extension]);

        $registry->get('thing');
        $registry->get('thing');
        $registry->all();
        self::assertSame(1, $registry->get('thing')->priority);

        // The next request may be somebody else's.
        $registry->reset();
        self::assertSame(2, $registry->get('thing')->priority);
    }

    public function testAnExtensionDoesNotMakeAnotherResource(): void
    {
        $elsewhere = new AsCrud('thing', '/elsewhere', 'box', list: ListThingsDto::class);
        $moved = static fn (AsCrud $crud): AsCrud => $elsewhere;
        $registry = new CrudRegistry([ThingCrud::class], extensions: [$this->extension('thing', $moved)]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('stay as declared');

        $registry->get('thing');
    }

    public function testAnExtensionChangesTheSourceForOneOfTheSameRecords(): void
    {
        $source = new DoctrineSource(Gadget::class, filters: ['name']);
        $gadgets = new AsCrud('gadget', '/gadgets', 'box', source: $source);
        $tenant = static function (AsCrud $crud): AsCrud {
            $source = $crud->source;
            self::assertInstanceOf(DoctrineSource::class, $source);

            return $crud->with(source: $source->with(filters: ['name', 'margin'], manager: 'tenant_a'));
        };
        $registry = new CrudRegistry(
            providers: [$this->definitions($gadgets)],
            extensions: [$this->extension('gadget', $tenant)],
        );

        $source = $registry->get('gadget')->source;

        self::assertInstanceOf(DoctrineSource::class, $source);
        self::assertSame('tenant_a', $source->manager);
        self::assertSame(['name', 'margin'], $source->filters);
    }

    private function definitions(AsCrud ...$definitions): CrudResourceProvider
    {
        return new readonly class (array_values($definitions)) implements CrudResourceProvider {
            /**
             * @param list<AsCrud> $definitions
             */
            public function __construct(private array $definitions)
            {
            }

            public function resources(): iterable
            {
                yield from $this->definitions;
            }
        };
    }

    private function provider(string ...$names): CrudResourceProvider
    {
        return new readonly class (array_values($names)) implements CrudResourceProvider {
            /**
             * @param list<string> $names
             */
            public function __construct(private array $names)
            {
            }

            public function resources(): iterable
            {
                foreach ($this->names as $name) {
                    yield new AsCrud($name, '/' . $name, 'graph-up', list: ListThingsDto::class);
                }
            }
        };
    }

    /**
     * @param Closure(AsCrud): AsCrud $extend
     */
    private function extension(string $resource, Closure $extend): CrudDefinitionExtension
    {
        return new readonly class ($resource, $extend) implements CrudDefinitionExtension {
            /**
             * @param Closure(AsCrud): AsCrud $extend
             */
            public function __construct(private string $resource, private Closure $extend)
            {
            }

            public function resource(): string
            {
                return $this->resource;
            }

            public function extend(AsCrud $crud): AsCrud
            {
                return ($this->extend)($crud);
            }
        };
    }
}
