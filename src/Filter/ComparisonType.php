<?php

declare(strict_types=1);

namespace Byfareska\Crud\Filter;

use InvalidArgumentException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A filter of a list that says how it compares: a small choice of the {@see Comparison}s it offers (none to choose
 * from with one), the field of the value and, when it offers Between, a second one for where the range ends. Its
 * data is a {@see Compared}, null while nothing is typed.
 *
 * Children: "comparison", "value", "to" (with Between), "values" (with In / NotIn: the field as a multiple choice).
 * IsEmpty and IsNotEmpty compare with nothing typed. A plain value instead of them (?filters[category]=5, a link, a
 * click on the list's head) is the value, compared the first way.
 *
 *     $builder->add('price', ComparisonType::class, [
 *         'comparisons' => Comparison::NUMBER,
 *         'value_type' => NumberType::class,
 *     ]);
 */
final class ComparisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var non-empty-list<ComparisonKind> $comparisons */
        $comparisons = $options['comparisons'];
        /** @var class-string<FormTypeInterface> $type */
        $type = $options['value_type'];
        /** @var array<string, mixed> $valueOptions */
        $valueOptions = $options['value_options'];
        $value = ['label' => false, 'required' => false] + $valueOptions;

        $builder->add('comparison', ChoiceType::class, [
            'label' => false,
            'choices' => $comparisons,
            'choice_value' => static fn (?ComparisonKind $comparison): ?string => $comparison?->key(),
            'choice_label' => static fn (ComparisonKind $comparison): string => $comparison->label(),
            'placeholder' => false,
            'required' => false,
            'empty_data' => $comparisons[0]->key(),
        ]);
        $builder->add('value', $type, $value);
        if (in_array(Comparison::Between, $comparisons, true)) {
            $builder->add('to', $type, $value);
        }
        // Several values at once (In, NotIn): the same field, a multiple choice - a choice or a relation.
        if (array_any($comparisons, static fn (ComparisonKind $comparison): bool => $comparison->isMany())) {
            $builder->add('values', $type, ['multiple' => true] + $value);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (is_scalar($data)) {
                $event->setData(['value' => (string) $data]);
            }
        });

        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?Compared $compared): array => [
                'comparison' => $compared->comparison ?? $comparisons[0],
                'value' => $compared?->comparison->isMany() ? null : $compared?->value,
                'to' => $compared?->to,
                'values' => $compared?->comparison->isMany() ? $compared->value : [],
            ],
            static function (mixed $data) use ($comparisons): ?Compared {
                if (!is_array($data)) {
                    return null;
                }
                $comparison = $data['comparison'] ?? null;
                $comparison = $comparison instanceof ComparisonKind ? $comparison : $comparisons[0];
                if (!$comparison->needsValue()) {
                    return new Compared($comparison, null);
                }
                if ($comparison->isMany()) {
                    $values = $data['values'] ?? null;
                    $values = $values instanceof \Traversable ? iterator_to_array($values, false) : $values;

                    return Compared::isEmpty($values) ? null : new Compared($comparison, $values);
                }
                $value = Compared::isEmpty($data['value'] ?? null) ? null : $data['value'];
                if ($comparison !== Comparison::Between) {
                    return $value === null ? null : new Compared($comparison, $value);
                }
                $to = Compared::isEmpty($data['to'] ?? null) ? null : $data['to'];

                return $value === null && $to === null ? null : new Compared($comparison, $value, $to);
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('comparisons');
        $resolver->setAllowedTypes('comparisons', ComparisonKind::class . '[]');
        $resolver->setNormalizer('comparisons', static function (Options $options, array $comparisons): array {
            if ($comparisons === []) {
                throw new InvalidArgumentException('A filter compares at least one way ("comparisons").');
            }

            $unique = [];
            foreach ($comparisons as $comparison) {
                if ($comparison instanceof ComparisonKind) {
                    $unique[$comparison->key()] = $comparison;
                }
            }

            return array_values($unique);
        });
        $resolver->setDefaults([
            'value_type' => TextType::class,
            'value_options' => [],
            'required' => false,
            'error_bubbling' => false,
        ]);
        $resolver->setAllowedTypes('value_type', 'string');
        $resolver->setAllowedTypes('value_options', 'array');
    }

    /**
     * What the theme and the "comparison" controller tell the ways apart by: which of them take several values
     * ("many") and which nothing typed ("presence") - the application's own among them.
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var list<ComparisonKind> $comparisons */
        $comparisons = $options['comparisons'];
        $keys = static fn (callable $which): array => array_values(array_map(
            static fn (ComparisonKind $comparison): string => $comparison->key(),
            array_filter($comparisons, $which),
        ));
        $view->vars['many_comparisons'] = $keys(static fn (ComparisonKind $comparison): bool => $comparison->isMany());
        $view->vars['presence_comparisons'] = $keys(
            static fn (ComparisonKind $comparison): bool => !$comparison->needsValue(),
        );
    }

    public function getBlockPrefix(): string
    {
        return 'crud_comparison';
    }
}
