<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Filter\Compared;
use Byfareska\Crud\Filter\Comparison;
use Byfareska\Crud\Filter\ComparisonType;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;

/**
 * The field of a {@see FieldFilter} - a {@see ComparisonType} - and what it keeps of the list:
 *
 * - a text in any case; "contains", "starts" and "ends" only compare texts;
 * - a date and time by the day: "on" is the whole day, "until" includes it, "between" both ends; by the minute when
 *   its form type is a DateTimeType ("createdAt" => DateTimeType::class) - the filter then picks a time too;
 * - a relation: = and ≠ only (a collection: has, has not);
 * - ≠ and "does not contain" keep what has no value too.
 */
final readonly class FieldFilterHandler implements DoctrineFilterHandler
{
    private const array TEXT_TYPES = ['string', 'text', 'ascii_string'];

    private const array DATETIME_TYPES = ['datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable'];

    public function __construct(
        private FormFactoryInterface $forms,
        private FormRegistryInterface $types,
        private DoctrineTextMatch $texts = new LowerTextMatch(),
    ) {
    }

    public function field(DoctrineFilter $filter, string $name, array $options): FormBuilderInterface
    {
        assert($filter instanceof FieldFilter);
        $type = $filter->type ?? TextType::class;
        $valueOptions = $filter->options;
        if ($this->extends($type, DateType::class) || $this->extends($type, DateTimeType::class)) {
            // One field the calendar of the theme fills.
            $valueOptions += ['widget' => 'single_text', 'input' => 'datetime_immutable'];
        }

        return $this->forms->createNamedBuilder($name, ComparisonType::class, null, $options + [
            'comparisons' => $filter->comparisons,
            'value_type' => $type,
            'value_options' => $valueOptions,
        ]);
    }

    public function apply(DoctrineFilter $filter, DoctrineListQuery $query, string $name, mixed $value): void
    {
        assert($filter instanceof FieldFilter);
        if (!$value instanceof Compared) {
            return;
        }
        [$metadata, $field] = $this->mapping($query, $filter->field);
        $path = $query->path($filter->field);
        $parameter = $query->parameter($name);
        $collection = $metadata->hasAssociation($field) && $metadata->isCollectionValuedAssociation($field);
        // A way of the application's own: what it adds is its own.
        if ($value->comparison instanceof DoctrineComparison) {
            $value->comparison->apply($query, $path, $parameter, $value);

            return;
        }

        if (!$value->comparison->needsValue()) {
            $text = $metadata->hasField($field) && in_array($metadata->getTypeOfField($field), self::TEXT_TYPES, true);
            $query->query->andWhere(match (true) {
                $collection => sprintf(
                    '%s IS %sEMPTY',
                    $path,
                    $value->comparison === Comparison::IsEmpty ? '' : 'NOT ',
                ),
                $text => $value->comparison === Comparison::IsEmpty
                    ? sprintf("(%1\$s IS NULL OR %1\$s = '')", $path)
                    : sprintf("(%1\$s IS NOT NULL AND %1\$s != '')", $path),
                default => sprintf('%s IS %sNULL', $path, $value->comparison === Comparison::IsEmpty ? '' : 'NOT '),
            });

            return;
        }
        if ($value->comparison->isMany()) {
            $this->many($query, $path, $parameter, $value, $collection);

            return;
        }

        if ($metadata->hasAssociation($field)) {
            $this->relation($query, $path, $parameter, $value, $metadata->isCollectionValuedAssociation($field));

            return;
        }

        $mapping = $metadata->hasField($field) ? $metadata->getFieldMapping($field) : null;
        // An enum is a parameter of its own kind: Doctrine takes its value.
        $type = $mapping?->enumType === null ? $mapping?->type : null;
        $nullable = $mapping->nullable ?? false;

        if (in_array($type, self::TEXT_TYPES, true) && is_string($value->value)) {
            $this->text($query, $path, $parameter, $value, $value->value, $nullable);
        } elseif (in_array($type, self::DATETIME_TYPES, true)) {
            $moments = $filter->type !== null && $this->extends($filter->type, DateTimeType::class);
            $this->periods($query, $path, $parameter, $value, $type, $nullable, $moments);
        } else {
            $this->compare($query, $path, $parameter, $value, $type, $nullable);
        }
    }

    /**
     * The metadata of the entity that has the field, and its name there: "category.name" is "name" of the category.
     *
     * @return array{ClassMetadata<object>, string}
     */
    private function mapping(DoctrineListQuery $query, string $field): array
    {
        // An embedded object's field ("price.amount") is the entity's own; a relation further away is walked.
        return $query->metadataOf($field);
    }

    /**
     * One of the values chosen, or none of them: IN for a field or a relation to one, MEMBER OF each for a relation to
     * many.
     */
    private function many(
        DoctrineListQuery $query,
        string $path,
        string $parameter,
        Compared $value,
        bool $collection,
    ): void {
        $values = is_array($value->value) ? array_values($value->value) : [$value->value];
        $in = $value->comparison === Comparison::In;
        if (!$collection) {
            $query->query->andWhere($in
                ? sprintf('%s IN (:%s)', $path, $parameter)
                : sprintf('(%1$s NOT IN (:%2$s) OR %1$s IS NULL)', $path, $parameter))
                ->setParameter($parameter, $values);

            return;
        }
        $members = [];
        foreach ($values as $index => $member) {
            $name = $parameter . '_' . $index;
            $members[] = sprintf(':%s MEMBER OF %s', $name, $path);
            $query->query->setParameter($name, $member);
        }
        $any = '(' . implode(' OR ', $members) . ')';
        $query->query->andWhere($in ? $any : 'NOT ' . $any);
    }

    private function relation(
        DoctrineListQuery $query,
        string $path,
        string $parameter,
        Compared $value,
        bool $collection,
    ): void {
        $where = match ($value->comparison) {
            Comparison::Equals => $collection
                ? sprintf(':%s MEMBER OF %s', $parameter, $path)
                : sprintf('%s = :%s', $path, $parameter),
            Comparison::NotEquals => $collection
                ? sprintf(':%s NOT MEMBER OF %s', $parameter, $path)
                : sprintf('(%1$s != :%2$s OR %1$s IS NULL)', $path, $parameter),
            default => throw new LogicException(sprintf(
                'A relation (%s) is compared by = and ≠ only, not "%s".',
                $path,
                $value->comparison->key(),
            )),
        };
        $query->query->andWhere($where)->setParameter($parameter, $value->value);
    }

    private function text(
        DoctrineListQuery $query,
        string $path,
        string $parameter,
        Compared $value,
        string $typed,
        bool $nullable,
    ): void {
        $text = $this->texts->text($typed);
        $like = LikePattern::escape($text);
        [$operator, $pattern] = match ($value->comparison) {
            Comparison::Contains => ['LIKE', '%' . $like . '%'],
            Comparison::NotContains => ['NOT LIKE', '%' . $like . '%'],
            Comparison::StartsWith => ['LIKE', $like . '%'],
            Comparison::EndsWith => ['LIKE', '%' . $like],
            Comparison::Equals => ['=', $text],
            Comparison::NotEquals => ['!=', $text],
            default => [null, null],
        };
        if ($operator === null) {
            $this->compare($query, $path, $parameter, $value, null, $nullable);

            return;
        }
        $where = sprintf('%s %s :%s', $this->texts->column($path), $operator, $parameter);
        if (str_contains($operator, 'LIKE')) {
            $where .= sprintf(" ESCAPE '%s'", LikePattern::ESCAPE);
        }
        if ($nullable && in_array($value->comparison, [Comparison::NotContains, Comparison::NotEquals], true)) {
            $where = sprintf('(%s OR %s IS NULL)', $where, $path);
        }
        $query->query->andWhere($where)->setParameter($parameter, $pattern);
    }

    /**
     * A date and time compared by the day it is in - each day of the value is from its midnight to the next one - or
     * by the minute.
     */
    private function periods(
        DoctrineListQuery $query,
        string $path,
        string $parameter,
        Compared $value,
        string $type,
        bool $nullable,
        bool $minutes,
    ): void {
        $builder = $query->query;
        $length = new DateInterval($minutes ? 'PT1M' : 'P1D');
        $start = static function (mixed $moment) use ($minutes): ?DateTimeImmutable {
            if (!$moment instanceof DateTimeInterface) {
                return null;
            }
            $moment = DateTimeImmutable::createFromInterface($moment);

            return $minutes
                ? $moment->setTime((int) $moment->format('G'), (int) $moment->format('i'))
                : $moment->setTime(0, 0);
        };
        $from = $start($value->value);
        $next = $from?->add($length);

        $bounds = match ($value->comparison) {
            Comparison::Equals => [['>=', $from], ['<', $next]],
            Comparison::GreaterThan => [['>=', $next]],
            Comparison::AtLeast => [['>=', $from]],
            Comparison::LessThan => [['<', $from]],
            Comparison::AtMost => [['<', $next]],
            Comparison::Between => [['>=', $from], ['<', $start($value->to)?->add($length)]],
            Comparison::NotEquals => null,
            default => throw new LogicException(sprintf(
                'A date (%s) is not compared by "%s".',
                $path,
                $value->comparison->key(),
            )),
        };

        if ($bounds === null) {
            $where = sprintf('(%1$s < :%2$s_from OR %1$s >= :%2$s_to', $path, $parameter);
            $where .= $nullable ? sprintf(' OR %s IS NULL)', $path) : ')';
            $builder->andWhere($where)
                ->setParameter($parameter . '_from', $from, $type)
                ->setParameter($parameter . '_to', $next, $type);

            return;
        }
        foreach ($bounds as $index => [$operator, $day]) {
            if (!$day instanceof DateTimeImmutable) {
                continue;
            }
            $name = $parameter . '_' . $index;
            $builder->andWhere(sprintf('%s %s :%s', $path, $operator, $name))->setParameter($name, $day, $type);
        }
    }

    private function compare(
        DoctrineListQuery $query,
        string $path,
        string $parameter,
        Compared $value,
        ?string $type,
        bool $nullable,
    ): void {
        $builder = $query->query;
        if ($value->comparison === Comparison::Between) {
            foreach ([['>=', $value->value, '_from'], ['<=', $value->to, '_to']] as [$operator, $bound, $suffix]) {
                if ($bound !== null) {
                    $builder->andWhere(sprintf('%s %s :%s%s', $path, $operator, $parameter, $suffix))
                        ->setParameter($parameter . $suffix, $bound, $type);
                }
            }

            return;
        }

        $operator = match ($value->comparison) {
            Comparison::Equals => '=',
            Comparison::NotEquals => '!=',
            Comparison::GreaterThan => '>',
            Comparison::AtLeast => '>=',
            Comparison::LessThan => '<',
            Comparison::AtMost => '<=',
            default => throw new LogicException(sprintf(
                '"%s" compares texts only, %s is none.',
                $value->comparison->key(),
                $path,
            )),
        };
        $where = sprintf('%s %s :%s', $path, $operator, $parameter);
        if ($nullable && $value->comparison === Comparison::NotEquals) {
            $where = sprintf('(%s OR %s IS NULL)', $where, $path);
        }
        $builder->andWhere($where)->setParameter($parameter, $value->value, $type);
    }

    /**
     * Whether a form type is the other one or made of it (its parent, the parent's parent...).
     */
    private function extends(string $type, string $parent): bool
    {
        $resolved = $this->types->getType($type);
        for (; $resolved instanceof ResolvedFormTypeInterface; $resolved = $resolved->getParent()) {
            if ($resolved->getInnerType() instanceof $parent) {
                return true;
            }
        }

        return false;
    }
}
