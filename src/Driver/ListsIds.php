<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\Form\FormInterface;

/**
 * A {@see CrudDriver} that gives the ids of every record a list form matches at once, without reading the records -
 * what a bulk action of "all N matching" goes through. Without it the list reads them page by page
 * (CrudDriver::list()), every record of every page made ready to show. The doctrine driver has it (one query of the
 * ids, in the order of the list).
 */
interface ListsIds
{
    /**
     * The ids, as in an address, in the order of the list; null when it cannot tell them this way (the list reads
     * them page by page then).
     *
     * @param FormInterface<mixed> $form the submitted, valid list form (its "page" and "perPage" do not matter)
     *
     * @return iterable<string>|null
     */
    public function ids(AsCrud $crud, FormInterface $form): ?iterable;
}
