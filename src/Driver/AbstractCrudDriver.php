<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Filter\Compared;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudListQuery;
use Byfareska\Crud\UseCase\Page;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * A start of a driver of your own - a list of an API, an index of ElasticSearch: the list form the list drives made
 * for you (listFormBuilder(): "page", "perPage", "fields", "sort" as CrudDriver says them, and a search), and what a
 * source that only lists and reads does not do refused. A read-only driver is name(), list() and read():
 *
 *     final class InvoicesApiDriver extends AbstractCrudDriver
 *     {
 *         public function name(): string { return 'invoices_api'; }
 *
 *         public function listForm(AsCrud $crud): FormInterface
 *         {
 *             return $this->listFormBuilder($crud, sortable: ['number', 'issuedAt'], sort: ['-issuedAt'])
 *                 ->add('status', ChoiceType::class, ['choices' => [...], 'required' => false])
 *                 ->getForm();
 *         }
 *
 *         public function list(AsCrud $crud, FormInterface $form): Page { ... $this->criteria($form) ... }
 *
 *         public function read(AsCrud $crud, string $id): array { ... }
 *     }
 *
 * Its source says what it can do (CrudSource::supports()): what it does not, has no page, no route and no button,
 * and is refused here too.
 */
abstract class AbstractCrudDriver implements CrudDriver
{
    protected FormFactoryInterface $formFactory;

    #[Required]
    public function setFormFactory(FormFactoryInterface $formFactory): void
    {
        $this->formFactory = $formFactory;
    }

    /**
     * The list form with what the list drives: the page, its size (1 to $maxPerPage), the fields an item has to carry
     * and the order - a multiple choice of the $sortable fields each way, $sort when nobody chose one - and, with
     * $search, the field of a typed text (CrudSource::searchField()). Add the filters, then getForm().
     *
     * @param list<string> $sortable
     * @param list<string> $sort the order of a list nobody sorted, "-" in front for descending
     *
     * @return FormBuilderInterface<mixed>
     */
    protected function listFormBuilder(
        AsCrud $crud,
        array $sortable = [],
        array $sort = [],
        ?string $search = null,
        int $defaultPerPage = 15,
        int $maxPerPage = 100,
    ): FormBuilderInterface {
        $builder = $this->formFactory->createNamedBuilder('filters', FormType::class, null, [
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
        if ($search !== null) {
            $builder->add($search, SearchType::class, [
                'label' => 'crud.filter.query',
                'required' => false,
                'empty_data' => '',
            ]);
        }
        $sorts = [];
        foreach ([...$sortable, ...array_map(static fn (string $one): string => ltrim($one, '-'), $sort)] as $field) {
            $sorts[$field] = $field;
            $sorts['-' . $field] = '-' . $field;
        }

        return $builder
            ->add('sort', ChoiceType::class, [
                'choices' => $sorts,
                'multiple' => true,
                'required' => false,
                'empty_data' => $sort,
            ])
            ->add('page', IntegerType::class, [
                'required' => false,
                'empty_data' => '1',
                'constraints' => [new Positive()],
            ])
            // As many as the largest size the resource offers - not more by an address.
            ->add('perPage', IntegerType::class, [
                'required' => false,
                'empty_data' => (string) $defaultPerPage,
                'constraints' => [new Range(min: 1, max: $maxPerPage)],
            ])
            ->add('fields', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'required' => false,
            ]);
    }

    /**
     * The filters of a submitted list form, those with a value: what an API is asked with.
     *
     * @param FormInterface<mixed> $form
     *
     * @return array<string, mixed>
     */
    protected function criteria(FormInterface $form): array
    {
        $criteria = [];
        foreach ($form->all() as $name => $field) {
            $value = $field->getData();
            if (!in_array($name, CrudListQuery::CONTROLLED, true) && !Compared::isEmpty($value)) {
                $criteria[(string) $name] = $value;
            }
        }

        return $criteria;
    }

    public function list(AsCrud $crud, FormInterface $form): Page
    {
        throw CrudFailure::forbidden();
    }

    public function read(AsCrud $crud, string $id): array
    {
        throw CrudFailure::notFound();
    }

    public function delete(AsCrud $crud, string $id): void
    {
        throw CrudFailure::forbidden();
    }

    public function form(AsCrud $crud, ?string $id, array $with = []): FormBuilderInterface
    {
        throw CrudFailure::forbidden();
    }

    public function save(AsCrud $crud, FormInterface $form, ?string $id): ?string
    {
        throw CrudFailure::forbidden();
    }
}
