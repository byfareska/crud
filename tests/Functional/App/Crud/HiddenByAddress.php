<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Crud;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudDefinitionExtension;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hides the properties of a customer the address names (?hidden=number) for that request, as an extension of an
 * application hides one from a role - and has the list searched by the number too. Without "hidden" the customer is as
 * declared.
 */
final readonly class HiddenByAddress implements CrudDefinitionExtension
{
    public function __construct(private RequestStack $requests)
    {
    }

    public function resource(): string
    {
        return 'customer';
    }

    public function extend(AsCrud $crud): AsCrud
    {
        $hidden = $this->requests->getMainRequest()?->query->getString('hidden') ?? '';
        if ($hidden === '' || !$crud->source instanceof DoctrineSource) {
            return $crud;
        }

        return $crud->with(
            hidden: [...$crud->hidden, ...explode(',', $hidden)],
            source: $crud->source->with(search: ['name', 'number']),
        );
    }
}
