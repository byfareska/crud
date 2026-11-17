<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\DependencyInjection;

use Byfareska\Crud\CrudBundle;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

/**
 * Every interface of the bundle with a TAG is autoconfigured by CrudBundle::build(): a service of the application
 * implementing it gets the tag. #[AutoconfigureTag] on the interface would not do - Symfony reads it only off an
 * interface in a directory loaded as services, and the bundle excludes some of its directories.
 */
final class AutoconfigurationTest extends TestCase
{
    public function testEveryInterfaceWithATagIsAutoconfigured(): void
    {
        $container = new ContainerBuilder();
        new CrudBundle()->build($container);
        $autoconfigured = $container->getAutoconfiguredInstanceof();

        $interfaces = $this->interfacesWithATag();
        self::assertGreaterThan(30, count($interfaces));
        foreach ($interfaces as $interface => $tag) {
            self::assertArrayHasKey($interface, $autoconfigured, $interface);
            self::assertTrue($autoconfigured[$interface]->hasTag($tag), $interface);
        }
    }

    public function testNoInterfaceSaysItsTagByAnAttribute(): void
    {
        foreach (array_keys($this->interfacesWithATag()) as $interface) {
            self::assertSame(
                [],
                new ReflectionClass($interface)->getAttributes(AutoconfigureTag::class),
                sprintf('%s: its tag is added by CrudBundle::build(), twice with the attribute.', $interface),
            );
        }
    }

    /**
     * @return array<class-string, string> the interfaces of src/ that declare a TAG, and the tag
     */
    private function interfacesWithATag(): array
    {
        $interfaces = [];
        foreach (new Finder()->files()->in(__DIR__ . '/../../../src')->name('*.php') as $file) {
            $class = 'Byfareska\\Crud\\' . str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            if (!interface_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            $constant = $reflection->getReflectionConstant('TAG');
            // One that extends another (PageLayoutCondition) is autoconfigured as that one.
            if ($constant === false || $constant->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $tag = $constant->getValue();
            if (is_string($tag)) {
                $interfaces[$class] = $tag;
            }
        }

        return $interfaces;
    }
}
