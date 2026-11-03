<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * A filter of the list of a {@see DoctrineSource} of the application's own, given in place of a form type:
 * `filters: ['priceFrom' => new AtLeast('price')]`. It only says what it is: the {@see DoctrineFilterHandler} it names
 * (a service) makes its field and adds it to the query. The name is that of the field of the list form; its label is
 * crud.<resource>.field.<name>.name.
 */
interface DoctrineFilter
{
    /**
     * The service that makes its field and narrows the query.
     *
     * @return class-string<DoctrineFilterHandler>
     */
    public function handler(): string;
}
