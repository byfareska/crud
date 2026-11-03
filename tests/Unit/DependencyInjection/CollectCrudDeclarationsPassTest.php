<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\DependencyInjection;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\DependencyInjection\CollectCrudDeclarationsPass;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Tests\Unit\Fixtures\Gadget;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingCrud;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CollectCrudDeclarationsPassTest extends TestCase
{
    public function testFindsServicesAndExcludedClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(CrudRegistry::class, CrudRegistry::class)->setPublic(true);
        $container->register(ThingCrud::class, ThingCrud::class)->addTag(AsCrud::TAG);
        // What Symfony keeps of a class of an excluded directory (src/Entity).
        $container->register(Gadget::class, Gadget::class)->setAbstract(true)->addTag('container.excluded');
        $container->register('unrelated', stdClass::class)->addTag('container.excluded');

        new CollectCrudDeclarationsPass()->process($container);

        self::assertSame(
            [Gadget::class, ThingCrud::class],
            $container->getDefinition(CrudRegistry::class)->getArgument('$resources'),
        );

        $registry = new CrudRegistry([Gadget::class, ThingCrud::class]);
        self::assertSame(['gadget', 'gizmo', 'thing'], array_keys($registry->all()));
        self::assertSame('admin', $registry->get('gizmo')->scope);
    }

    public function testANameIsOneResource(): void
    {
        $this->expectException(LogicException::class);

        new CrudRegistry([ThingCrud::class, ThingCrud::class])->all();
    }
}
