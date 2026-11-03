<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The drivers there are, by name.
 */
final readonly class CrudDrivers
{
    /**
     * @param iterable<CrudDriver> $drivers
     */
    public function __construct(
        #[AutowireIterator(CrudDriver::TAG)]
        private iterable $drivers,
    ) {
    }

    /**
     * The driver of a resource.
     *
     * @throws LogicException when no driver has the name its source gives, or two have it
     */
    public function of(AsCrud $crud): CrudDriver
    {
        $name = $crud->source->driver();
        $found = null;
        foreach ($this->drivers as $driver) {
            if ($driver->name() !== $name) {
                continue;
            }
            // Two of one name would be a choice by the order of the services: decorate the bundle's instead.
            if ($found instanceof CrudDriver) {
                throw new LogicException(sprintf(
                    'Two drivers are named "%s" (%s, %s): to change the bundle\'s, decorate it '
                    . '(#[AsDecorator(%s::class)]) or give yours another name.',
                    $name,
                    $found::class,
                    $driver::class,
                    $found::class,
                ));
            }
            $found = $driver;
        }
        if ($found instanceof CrudDriver) {
            return $found;
        }

        throw new LogicException(sprintf(
            'CRUD resource "%s" needs the driver "%s", and there is none of that name (a service implementing %s).',
            $crud->name,
            $name,
            CrudDriver::class,
        ));
    }
}
