<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrud;

/** Stands for an entity: a class of a directory excluded from the services. */
#[AsCrud(name: 'gadget', path: '/gadgets', icon: 'cpu', list: ListThingsDto::class)]
#[AsCrud(name: 'gizmo', path: '/gizmos', icon: 'cpu', list: ListThingsDto::class, scope: 'admin')]
final class Gadget
{
}
