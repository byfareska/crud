<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A driver that fills a new record in from the address (?with[category]=5) by itself, once the form is what the
 * request makes of it: after the CrudFormExtensions and the Whens of the layout. The form then gets from its driver
 * an add form without $with, and what is left of it here - only fields the form still has, enabled and mapped:
 * nothing an extension keeps from the user (disabled, removed) is set by the address.
 *
 * A driver without it gets $with in form() itself, before the extensions; it has to leave out what they would.
 */
interface PrefillsForm
{
    /**
     * @param FormBuilderInterface<mixed> $builder the add form, as the extensions left it
     * @param array<string, mixed> $with plain values by field, only of fields of the form that may be changed
     */
    public function prefill(AsCrud $crud, FormBuilderInterface $builder, array $with): void;
}
