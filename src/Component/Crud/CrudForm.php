<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Crud;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudFormSection;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Driver\Dto\DtoRecords;
use Byfareska\Crud\Driver\PrefillsForm;
use Byfareska\Crud\Event\RecordSaved;
use Byfareska\Crud\Event\RecordSaving;
use Byfareska\Crud\Layout\CrudLayoutView;
use Byfareska\Crud\Panel\ScopedComponent;
use Byfareska\Crud\Resource\AfterSave;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudFill;
use Byfareska\Crud\Resource\CrudFormExtension;
use Byfareska\Crud\Resource\CrudFormFiller;
use Byfareska\Crud\Resource\CrudFormFillers;
use Byfareska\Crud\Resource\CrudLayouts;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudReturn;
use Byfareska\Crud\Resource\CrudSaveButton;
use Byfareska\Crud\Resource\CrudSaveReturn;
use Byfareska\Crud\Resource\CrudToast;
use Byfareska\Crud\Resource\FailureMessage;
use Byfareska\Crud\Resource\NoRecordTransaction;
use Byfareska\Crud\Resource\OwnRecords;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordTransaction;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\UseCase\MapsToRequest;
use Byfareska\Crud\UseCase\UseCaseExecutor;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Byfareska\Crud\Driver\CopiesRecords;
use Byfareska\Crud\Event\RecordCopying;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * The add / edit form of any #[AsCrud] resource: the form its driver builds (CrudDriver::form()), saved by the
 * driver. The edit form also has the sections other parts of the application add with #[AsCrudFormSection] (the add
 * form those with a "create" DTO): each is a DTO of its own, saved through its own use case once the record has been,
 * in the same transaction. The fields are laid out by the
 * resource's layout (#[AsCrud(layout: ...)]). The add form can be filled in from something pasted (CrudFormFiller).
 *
 * A resource may have a form of its own - more live actions (a field computed as one types, a value looked up), what is
 * done before or after save(): a class extending this one, named in #[AsCrud(formComponent:)] and drawn by the same
 * template (#[AsLiveComponent('App:InvoiceForm', template: CrudForm::TEMPLATE)]). Services its actions need are
 * arguments of the actions themselves (a LiveAction gets them autowired); refuse() shows a failure at its field.
 */
#[AsLiveComponent('Crud:Form', template: self::TEMPLATE)]
class CrudForm implements ScopedComponent
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    /** The template of the form, in the theme in use - also that of a form of a resource's own. */
    public const string TEMPLATE = 'components/Crud/Form.html.twig';

    /** Name of the resource, as in #[AsCrud]. */
    #[LiveProp]
    public string $resource = '';

    /** The record being edited, as in its URL; null when adding one. */
    #[LiveProp]
    public ?string $id = null;

    /**
     * The tab in view, as in the address (?tab=...); kept by the "tabs" controller, so a re-render does not go back
     * to the first one. A refused save shows the first tab with an error instead.
     */
    #[LiveProp(writable: true)]
    public ?string $tab = null;

    /**
     * What a new record starts with, as in the address (?with[parent]=...): a link can open the form already
     * filled in where it came from. Only plain values of properties the DTO has.
     *
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $with = [];

    /**
     * The record a new one starts as a copy of, as in the address (?from=<id>, DuplicateAction): only one the one
     * looking may read, only for fields of the add form they may fill in.
     */
    #[LiveProp]
    public ?string $from = null;

    /**
     * What is pasted into the fillers of the add form (CrudFormFiller), by their names.
     *
     * @var array<string, string>
     */
    #[LiveProp(writable: true)]
    public array $fillerInput = [];

    /** What the last filler made of it - its notes or why it could not; the values are in the fields already. */
    public ?CrudFill $filled = null;

    /** The filler that was last used. */
    public ?string $filledBy = null;

    /** Whether the guards of the record allowed updating it in this request. */
    private bool $guarded = false;

    /**
     * The record being edited as it was read for its guards - before the form of this request changed it; what the
     * events of a save get as "before".
     */
    private ?RecordView $before = null;

    /**
     * The bundle adds to these (at the end, with defaults): a class extending this one gets a service of its own
     * by a method marked #[Required], or as an argument of its live action - not by a constructor of its own.
     *
     * @param iterable<CrudFormExtension> $formExtensions
     * @param iterable<CrudSaveButton> $saveButtons
     */
    public function __construct(
        protected readonly CrudRegistry $registry,
        protected readonly CrudDrivers $drivers,
        protected readonly DtoRecords $dtoRecords,
        protected readonly UseCaseExecutor $executor,
        protected readonly RequestStack $requestStack,
        protected readonly TranslatorInterface $translator,
        protected readonly FailureMessage $failureMessage,
        protected readonly CrudAccess $access,
        protected readonly CrudLayouts $layouts,
        protected readonly CrudReturn $return,
        protected readonly CrudFormFillers $fillers,
        protected readonly CrudRecords $records,
        protected readonly RecordGuards $guards,
        protected readonly RecordTransaction $transaction = new NoRecordTransaction(),
        protected readonly ?EventDispatcherInterface $events = null,
        #[AutowireIterator(CrudFormExtension::TAG)]
        protected readonly iterable $formExtensions = [],
        protected readonly ?OwnRecords $ownRecords = null,
        #[AutowireIterator(CrudSaveButton::TAG)]
        protected readonly iterable $saveButtons = [],
        protected readonly ?RecordActions $recordActions = null,
    ) {
    }

    public function panelScope(): ?string
    {
        return $this->resource === '' ? null : $this->registry->get($this->resource)->scope;
    }

    /**
     * The fillers of the add form of the resource (none for the edit form: they would replace what is stored).
     *
     * @return list<CrudFormFiller>
     */
    public function getFillers(): array
    {
        if ($this->id !== null) {
            return [];
        }

        return array_values($this->fillers->of($this->resource));
    }

    /**
     * Every filler starts with its key in fillerInput: the browser sets "fillerInput.<name>" only on a key that is
     * there - on an empty list it refuses the model.
     */
    #[PostMount]
    public function prepareFillers(): void
    {
        foreach ($this->getFillers() as $filler) {
            $this->fillerInput[$filler->name()] ??= '';
        }
    }

    /**
     * Fills the visible fields in from what was pasted into a filler; the form is not checked nor saved.
     */
    #[LiveAction]
    public function fill(#[LiveArg] string $filler): void
    {
        $this->getDefinition();
        $found = array_find(
            $this->getFillers(),
            static fn (CrudFormFiller $candidate): bool => $candidate->name() === $filler,
        );
        if (!$found instanceof CrudFormFiller) {
            return;
        }

        $this->filledBy = $found->name();
        $this->filled = $found->fill($this->fillerInput[$found->name()] ?? '');
        if ($this->filled->values !== []) {
            // Rendered again from these values (submitted, not checked): what the filler did not say stays as typed.
            $this->formValues = array_replace($this->formValues, $this->filled->values);
        }
    }

    public function getDefinition(): AsCrud
    {
        $crud = $this->registry->get($this->resource);
        // The component is reached without its page too (the live endpoint).
        $this->access->assertGranted($crud, $this->id === null ? CrudOperation::Create : CrudOperation::Update);
        // One's own record is nobody else's, whoever signed the id in.
        if ($crud->own && $this->id !== ($this->ownRecords?->idOf($crud) ?? $this->access->ownId())) {
            throw new AccessDeniedHttpException(sprintf('"%s" is the record of the one signed in.', $crud->name));
        }
        if ($this->id !== null && !$this->guarded) {
            // Its guards, once a request: the page asked them already, the live endpoint has not.
            try {
                $this->before = $this->records->view($crud, $this->id, RecordScreen::Form);
                $this->guards->assertAllowed(CrudOperation::Update, $this->before);
            } catch (CrudFailure $failure) {
                throw new HttpException($failure->status, $this->failureMessage->of($failure), $failure);
            }
            $this->guarded = true;
        }

        return $crud;
    }

    /**
     * How the fields are laid out - see crud_layout().
     */
    public function getLayout(): CrudLayoutView
    {
        $fillers = [];
        foreach ($this->getFillers() as $filler) {
            $fillers[$filler->name()] = [
                'name' => $filler->name(),
                'input' => $this->fillerInput[$filler->name()] ?? '',
                'filled' => $this->filledBy === $filler->name() ? $this->filled : null,
                'used' => $this->filledBy === $filler->name(),
            ];
        }

        return $this->layouts->form($this->getDefinition(), $this->getFormView(), $this->id, $this->tab, $fillers);
    }

    /**
     * The buttons of the form besides "save" (AsCrud::$afterSave): "save and add another" on the add form, "save and
     * keep editing".
     *
     * @return list<AfterSave>
     */
    public function getSaveChoices(): array
    {
        $crud = $this->getDefinition();

        return array_values(array_filter(
            $crud->afterSave,
            fn (AfterSave $then): bool => match ($then) {
                AfterSave::New => $this->id === null && $crud->can(CrudOperation::Create),
                AfterSave::Edit => $crud->can(CrudOperation::Update),
                AfterSave::Return => false,
            },
        ));
    }

    /**
     * What can be done with the record being edited (RecordAction) - its details, deleting it... - for the buttons
     * next to the title; none on the add form.
     *
     * @return list<ActionButton>
     */
    public function getActions(): array
    {
        $this->getDefinition();

        return $this->before instanceof RecordView && $this->recordActions instanceof RecordActions
            ? $this->recordActions->buttons($this->before)
            : [];
    }

    /**
     * Fills the add form in from the record it copies: one in its scopes and readable by its guards, only the fields
     * the form still has, enabled and mapped; then RecordCopying, for what else a copy takes.
     *
     * @param FormBuilderInterface<mixed> $builder
     */
    protected function copyFrom(AsCrud $crud, CopiesRecords $driver, FormBuilderInterface $builder, string $from): void
    {
        $this->access->assertGranted($crud, CrudOperation::Read);
        try {
            $source = $this->records->view($crud, $from, RecordScreen::Details);
            $this->guards->assertAllowed(CrudOperation::Read, $source);
            $fields = [];
            // Not a section (unmapped), not a field kept from the user (disabled).
            foreach ($builder->all() as $name => $field) {
                if (!$field->getDisabled() && $field->getMapped()) {
                    $fields[] = (string) $name;
                }
            }
            $driver->copy($crud, $from, $builder, $fields);
        } catch (CrudFailure $failure) {
            throw new HttpException($failure->status, $this->failureMessage->of($failure), $failure);
        }
        $this->events?->dispatch(new RecordCopying($crud, $source, $builder->getData()));
    }

    /**
     * The buttons of the application's own besides "save" (CrudSaveButton), for this form.
     *
     * @return list<CrudSaveButton>
     */
    public function getSaveButtons(): array
    {
        $crud = $this->getDefinition();
        $buttons = [];
        foreach ($this->saveButtons as $button) {
            if (
                in_array($button->resource(), [$crud->name, CrudSaveButton::EVERY], true)
                && $button->isFor($crud, $this->id)
            ) {
                $buttons[] = $button;
            }
        }

        return $buttons;
    }

    /**
     * @param string|null $then where the save leads: the button that saved - AfterSave, or the name of a
     *     CrudSaveButton; null = the way back
     */
    #[LiveAction]
    public function save(#[LiveArg] ?string $then = null): ?RedirectResponse
    {
        // The chosen files come with the action itself ("files|save" in the template), under the names of their
        // fields - so they fall into place among the values of the form. Once submitted, the values are read back
        // from the form, where a file field is always empty.
        $files = $this->requestStack->getCurrentRequest()?->files->all()[$this->getFormName()] ?? [];
        if (is_array($files)) {
            $this->formValues = array_replace_recursive($this->formValues, $files);
        }

        // Read before the form changes it (an entity is changed in place): what the events get as "before".
        $crud = $this->getDefinition();
        $before = $this->before;
        $created = $this->id === null;

        // Invalid: the trait answers 422 and the form is rendered again with its errors.
        $this->submitForm();
        $form = $this->getForm();

        // Where a refusal is shown: the form, or the section that refused.
        $refusedAt = $form;
        try {
            $save = function () use ($crud, $form, $before, $created, $then, &$refusedAt): ?string {
                $data = $form->getData();
                $this->events?->dispatch(new RecordSaving($crud, $this->id, $data, $before, $then));
                $savedId = $this->drivers->of($crud)->save($crud, $form, $this->id);

                foreach ($this->sections() as $name => $section) {
                    $refusedAt = $form->get($name);
                    $sectionDto = $refusedAt->getData();
                    if (!$sectionDto instanceof MapsToRequest) {
                        throw new LogicException(sprintf(
                            'The section "%s" of the form of "%s" did not produce its DTO.',
                            $name,
                            $this->resource,
                        ));
                    }
                    // The record's id - of a new one, now that it is saved.
                    $this->dtoRecords->address($sectionDto, $this->id ?? $savedId ?? throw new LogicException(sprintf(
                        'The section "%s" of the add form of "%s" is saved for the new record, whose id the driver '
                        . 'did not tell (CrudDriver::save()).',
                        $name,
                        $this->resource,
                    )));
                    $this->executor->execute($sectionDto);
                }
                $refusedAt = $form;

                $this->events?->dispatch(new RecordSaved($crud, $savedId, $created, $data, $before, $then));

                return $savedId;
            };
            $savedId = $this->transaction->run($crud, $save);
        } catch (CrudFailure $failure) {
            // In a transaction (RecordTransaction) nothing is kept - neither the record nor a section before the one
            // that refused.
            return $this->refuse($refusedAt, $failure);
        }

        $session = $this->requestStack->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            // Translated now: the page that shows it is rendered by another request.
            $session->getFlashBag()->add(
                'toast',
                CrudToast::text(
                    $this->translator,
                    $crud,
                    $created ? 'created' : 'updated',
                    CrudToast::parametersOf($savedId ?? $this->id, $form->getData()),
                ),
            );
        }

        $button = array_find(
            $this->getSaveButtons(),
            static fn (CrudSaveButton $button): bool => $button->name() === $then,
        );
        if ($button instanceof CrudSaveButton) {
            return new RedirectResponse($button->afterSave($crud, $savedId, $created));
        }
        $after = AfterSave::tryFrom((string) $then) ?? AfterSave::Return;

        return new RedirectResponse(
            $after !== AfterSave::Return && $this->return instanceof CrudSaveReturn
                ? $this->return->afterSaveTo($crud, $savedId, $created, $after)
                : $this->return->afterSave($crud, $savedId, $created),
        );
    }

    /**
     * Shows why a use case refused, at the field the refusal is about.
     *
     * @param FormInterface<mixed> $form
     */
    protected function refuse(FormInterface $form, CrudFailure $failure): null
    {
        if ($failure->violations === []) {
            $target = $failure->field !== null && $form->has($failure->field) ? $form->get($failure->field) : $form;
            $target->addError(new FormError($this->failureMessage->of($failure)));
        }
        // Each at its field: "address.city" at the field "city" of "address", as far as the form has them.
        foreach ($failure->violations as $path => $messages) {
            $target = $form;
            foreach (preg_split('/[.\[\]]+/', $path, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $name) {
                if (!$target->has($name)) {
                    break;
                }
                $target = $target->get($name);
            }
            foreach ($messages as $message) {
                $target->addError(new FormError($message));
            }
        }
        // The view was built before the use case refused; build it again, with the error.
        $this->formView = null;

        return null;
    }

    /**
     * What other parts of the application add to this form: to the add form those with a "create" DTO.
     *
     * @return array<string, AsCrudFormSection>
     */
    protected function sections(): array
    {
        $sections = $this->registry->formSectionsOf($this->resource);

        return $this->id === null
            ? array_filter($sections, static fn (AsCrudFormSection $section): bool => $section->create !== null)
            : $sections;
    }

    /**
     * @return FormInterface<mixed>
     */
    protected function instantiateForm(): FormInterface
    {
        $crud = $this->getDefinition();
        $driver = $this->drivers->of($crud);
        // A driver that can fills it in at the end, from what is left of the form (see PrefillsForm).
        $builder = $driver->form($crud, $this->id, $driver instanceof PrefillsForm ? [] : $this->with);
        // What the request makes of it: a field a role may not change, one a tenant has not.
        foreach ($this->formExtensions as $extension) {
            if (in_array($extension->resource(), [$crud->name, CrudFormExtension::EVERY], true)) {
                $extension->buildForm($crud, $builder, $this->before);
            }
        }

        foreach ($this->sections() as $name => $section) {
            // Not a property of the record: it is saved by itself, in save(). A class marked #[AsFormType] is
            // a form type by its name, which the signature of add() does not know.
            // @phpstan-ignore argument.type
            $builder->add($name, $this->id === null ? $section->create : $section->update, [
                'mapped' => false,
                // A heading, not a field somebody has to fill in.
                'required' => false,
                'label' => $section->label,
                // The form only validates the DTO it is built for by itself; a section's DTO hangs beside it.
                'constraints' => [new Valid()],
                // A new record's section starts with the defaults of its DTO; another one with what "read" says.
                'data' => $this->id === null && $section->create !== null
                    ? $this->dtoRecords->newDto($section->create)
                    : $this->dtoRecords->sectionDto($section, (string) $this->id),
            ]);
        }

        // What a When of the layout keeps off the page for this record is not in the form either: it is not only
        // hidden, it cannot be sent.
        foreach ($this->layouts->hiddenInForm($crud, $this->before) as $hidden) {
            if ($builder->has($hidden)) {
                $builder->remove($hidden);
            }
        }

        // A copy of another record (?from=...): first, so what the address gives (?with) comes over it.
        if ($this->id === null && $this->from !== null && $this->from !== '' && $driver instanceof CopiesRecords) {
            $this->copyFrom($crud, $driver, $builder, $this->from);
        }

        // What the address gives a new record: only for fields the user may fill in here - not one an extension
        // disabled or removed, nor one a When keeps off the page.
        if ($this->id === null && $this->with !== [] && $driver instanceof PrefillsForm) {
            $driver->prefill($crud, $builder, array_filter(
                $this->with,
                static fn (int|string $field): bool => $builder->has((string) $field)
                    && !$builder->get((string) $field)->getDisabled()
                    && $builder->get((string) $field)->getMapped(),
                ARRAY_FILTER_USE_KEY,
            ));
        }

        return $builder->getForm();
    }
}
