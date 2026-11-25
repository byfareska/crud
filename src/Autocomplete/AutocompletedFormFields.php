<?php

declare(strict_types=1);

namespace Byfareska\Crud\Autocomplete;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudFormExtension;
use Byfareska\Crud\Resource\CrudLayouts;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\OwnRecords;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Whether the one asking has a relation of a guessed form as the bundle's field searched as one types
 * (AutocompleteRelationFormField) - what RelationAutocompleter answers a form with only then. The form is built as the
 * form component builds it for this request (CrudForm::instantiateForm()): the add form, or the edit form of the
 * record - one they may edit (its scopes, its RecordGuards, "own") - by the driver, changed by the CrudFormExtensions,
 * without what a When of the layout keeps off the page. The field has to be in it, not disabled, mapped, and still the
 * bundle's field of that relation: not one an extension put in its place with options of its own.
 *
 * The address of the search is signed by symfony/ux-autocomplete but bound to nobody: without this, one who may edit
 * the resource could search a relation the form keeps from them (removed or disabled for their role) with an address
 * somebody else's form had.
 */
final readonly class AutocompletedFormFields
{
    /**
     * @param iterable<CrudFormExtension> $formExtensions
     */
    public function __construct(
        private CrudAccess $access,
        private CrudDrivers $drivers,
        private CrudRecords $records,
        private RecordGuards $guards,
        private CrudLayouts $layouts,
        #[AutowireIterator(CrudFormExtension::TAG)]
        private iterable $formExtensions = [],
        private ?OwnRecords $ownRecords = null,
    ) {
    }

    /**
     * @param string $field the relation of the resource's entity
     * @param string|null $id the record of the edit form; null = the add form
     */
    public function has(AsCrud $crud, string $field, ?string $id): bool
    {
        if (!$this->access->isGranted($crud, $id === null ? CrudOperation::Create : CrudOperation::Update)) {
            return false;
        }
        try {
            // One's own record is nobody else's (and an own resource has no add form).
            if ($crud->own && $id !== ($this->ownRecords?->idOf($crud) ?? $this->access->ownId())) {
                return false;
            }
            $record = null;
            if ($id !== null) {
                $record = $this->records->view($crud, $id, RecordScreen::Form);
                if (!$this->guards->allows(CrudOperation::Update, $record)) {
                    return false;
                }
            }
            $builder = $this->drivers->of($crud)->form($crud, $id);
        } catch (CrudFailure | AccessDeniedHttpException) {
            return false;
        }
        foreach ($this->formExtensions as $extension) {
            if (in_array($extension->resource(), [$crud->name, CrudFormExtension::EVERY], true)) {
                $extension->buildForm($crud, $builder, $record);
            }
        }
        if (in_array($field, $this->layouts->hiddenInForm($crud, $record), true) || !$builder->has($field)) {
            return false;
        }

        $child = $builder->get($field);
        $extra = $child->getOption('extra_options');

        return !$child->getDisabled()
            && $child->getMapped()
            && $child->getOption('options_as_html') !== true
            && is_array($extra)
            && ($extra[RelationAutocompleter::RESOURCE] ?? null) === $crud->name
            && ($extra[RelationAutocompleter::FIELD] ?? null) === $field
            && isset($extra[RelationAutocompleter::FORM])
            && ($extra[RelationAutocompleter::RECORD] ?? null) === $id;
    }
}
