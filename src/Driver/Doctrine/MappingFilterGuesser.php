<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Filter\Comparison;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;

/**
 * The filter of a field from its mapping ({@see DoctrineFilterGuesser}), a {@see FieldFilter}:
 *
 * - a relation: is / is not one of the related entities ({@see RelationFilterField} says how it is chosen);
 * - a boolean: yes / no;
 * - a date, a date and time: between two days (from - to, either may be left empty), on, from or until a day;
 * - an enum: is / is not one of its cases;
 * - a number: =, ≠, >, ≥, <, ≤ or between two;
 * - a text: contains (any case), does not contain, is, is not, starts or ends with;
 * - anything else: is what was typed.
 *
 * A form type the source names takes the place of the guessed one, the comparisons stay.
 */
final readonly class MappingFilterGuesser implements DoctrineFilterGuesser
{
    private const array TEXT_TYPES = ['string', 'text', 'ascii_string'];

    private const array DATE_TYPES = [
        'date',
        'date_immutable',
        'datetime',
        'datetime_immutable',
        'datetimetz',
        'datetimetz_immutable',
    ];

    private const array NUMBER_TYPES = ['integer', 'smallint', 'bigint', 'decimal', 'float', 'smallfloat'];

    public function __construct(
        private FormRegistryInterface $forms,
        private RelationFilterField $relations,
    ) {
    }

    public function guess(AsCrud $crud, ClassMetadata $metadata, string $field, ?string $type): DoctrineFilter
    {
        if ($metadata->hasAssociation($field)) {
            $type ??= EntityType::class;
            $options = $this->extends($type, EntityType::class)
                ? $this->relations->options($crud, $field, $metadata->getAssociationTargetClass($field))
                : [];

            return $this->filter($field, Comparison::CHOICE, $type, $options);
        }
        if (!$metadata->hasField($field)) {
            throw new LogicException(sprintf(
                'CRUD resource "%s": the filter "%s" is no field nor relation of %s. A filter of the application\'s '
                . 'own is a DoctrineFilter: "%s" => new MyFilter().',
                $crud->name,
                $field,
                $metadata->getName(),
                $field,
            ));
        }

        $mapping = $metadata->getFieldMapping($field);
        if ($mapping->type === 'boolean') {
            return $type === null
                ? $this->filter($field, [Comparison::Equals], ChoiceType::class, [
                    'choices' => ['crud.filter.yes' => true, 'crud.filter.no' => false],
                ])
                : $this->filter($field, [Comparison::Equals], $type);
        }
        if (in_array($mapping->type, self::DATE_TYPES, true)) {
            // A day, not a moment: what one filters a date and time by.
            return $type === null
                ? $this->filter($field, Comparison::DATE, DateType::class, [
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                ])
                : $this->filter($field, Comparison::DATE, $type);
        }

        $comparisons = match (true) {
            $mapping->enumType !== null => Comparison::CHOICE,
            in_array($mapping->type, self::NUMBER_TYPES, true) => Comparison::NUMBER,
            in_array($mapping->type, self::TEXT_TYPES, true) => Comparison::TEXT,
            default => [Comparison::Equals],
        };
        if ($type !== null) {
            return $this->filter($field, $comparisons, $type);
        }
        $guess = $this->forms->getTypeGuesser()?->guessType($metadata->getName(), $field);
        /** @var array<string, mixed> $options */
        $options = $guess?->getOptions() ?? [];

        return $this->filter($field, $comparisons, $guess?->getType() ?? TextType::class, $options);
    }

    /**
     * @param non-empty-list<Comparison> $comparisons
     * @param string $type a form type
     * @param array<string, mixed> $options
     */
    private function filter(string $field, array $comparisons, string $type, array $options = []): FieldFilter
    {
        if ($this->extends($type, ChoiceType::class)) {
            // A filter is one value, also of a collection (MEMBER OF).
            $options = ['placeholder' => 'crud.filter.any'] + $options;
            $options['multiple'] = false;
        }

        return new FieldFilter($field, $comparisons, $type, $options);
    }

    private function extends(string $type, string $class): bool
    {
        $current = $this->forms->getType($type);
        for (; $current instanceof ResolvedFormTypeInterface; $current = $current->getParent()) {
            if ($current->getInnerType() instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
