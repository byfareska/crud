<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * The "sort" field of a list form (see CrudDriver::listForm()): a multiple choice of the sortable fields, each also
 * with a minus in front for descending, and the order of a list nobody sorted when nothing is chosen.
 *
 * A hidden property (AsCrud::hides()) is ordered by for nobody who asks: it is no choice. The order of a list nobody
 * sorted may name one - the application chose it - and is kept, but a request naming it (?sort=-margin) has it left
 * out; with nothing else asked, the list is in that order.
 */
final class SortField
{
    public const string NAME = 'sort';

    /** Of the listener leaving out a hidden property: before those of ChoiceType. */
    private const int PRIORITY = 512;

    /**
     * @param FormBuilderInterface<mixed> $builder the list form
     * @param list<string> $sortable what a heading orders by
     * @param list<string> $default the order of a list nobody sorted, "-" in front for descending
     *
     * @return FormBuilderInterface<mixed>
     */
    public static function add(
        FormBuilderInterface $builder,
        AsCrud $crud,
        array $sortable,
        array $default,
    ): FormBuilderInterface {
        $sorts = [];
        $fields = [
            ...array_filter($sortable, static fn (string $field): bool => !$crud->hides($field)),
            // A choice, or the order nobody chose would not be one the field takes.
            ...array_map(static fn (string $sort): string => ltrim($sort, '-'), $default),
        ];
        foreach ($fields as $field) {
            $sorts[$field] = $field;
            $sorts['-' . $field] = '-' . $field;
        }
        $builder->add(self::NAME, ChoiceType::class, [
            'choices' => $sorts,
            'multiple' => true,
            'required' => false,
            'empty_data' => $default,
        ]);
        $builder->get(self::NAME)->addEventListener(
            FormEvents::PRE_SUBMIT,
            static function (FormEvent $event) use ($crud): void {
                $asked = $event->getData();
                if (!is_array($asked) || $asked === []) {
                    return;
                }
                $allowed = array_values(array_filter(
                    $asked,
                    static fn (mixed $sort): bool => !is_string($sort) || !$crud->hides(ltrim($sort, '-')),
                ));
                // Nothing left: the order of a list nobody sorted ("empty_data").
                $event->setData($allowed === [] ? null : $allowed);
            },
            // Before the choice field itself looks at what was sent.
            self::PRIORITY,
        );

        return $builder;
    }
}
