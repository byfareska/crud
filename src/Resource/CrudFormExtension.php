<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Changes the add and edit forms of a resource for the request being answered, whatever its driver: a field only
 * somebody with a role may change, one shown but fixed once the record is approved, one a tenant does not have. A
 * service, autoconfigured; it gets the form as the driver (and the extensions before it) made it, and the record being
 * edited:
 *
 *     final readonly class CreditLimitForFinance implements CrudFormExtension
 *     {
 *         public function __construct(private Actor $actor) {}
 *
 *         public function resource(): string { return 'customer'; }
 *
 *         public function buildForm(AsCrud $crud, FormBuilderInterface $builder, ?RecordView $record): void
 *         {
 *             if ($builder->has('creditLimit') && !$this->actor->isGranted('ROLE_FINANCE')) {
 *                 $builder->get('creditLimit')->setDisabled(true); // shown, not changed: what is sent is ignored
 *                 // or $builder->remove('creditLimit'); - not there at all
 *             }
 *         }
 *     }
 *
 * The form is made anew for every live request, the save too, so what it takes away cannot be sent back. By the
 * priority of its tag.
 */
#[AutoconfigureTag(self::TAG)]
interface CrudFormExtension
{
    public const string TAG = 'byfareska_crud.form_extension';

    /** The resource of an extension of every resource. */
    public const string EVERY = '*';

    /** The name of the resource whose forms it changes; EVERY for all. */
    public function resource(): string;

    /**
     * @param FormBuilderInterface<mixed> $builder the add form, or the edit form of $record
     * @param RecordView|null $record the record being edited, as it is (every property it can read); null = added
     */
    public function buildForm(AsCrud $crud, FormBuilderInterface $builder, ?RecordView $record): void;
}
