<?php

declare(strict_types=1);

namespace Byfareska\Crud\Filter;

/**
 * How a filter of a list compares a field with what was typed - the small choice next to the field
 * ({@see ComparisonType}). Its label is the translation crud.filter.comparison.<value>. A way of the application's
 * own is a {@see ComparisonKind} of its own.
 */
enum Comparison: string implements ComparisonKind
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts';
    case EndsWith = 'ends';
    case GreaterThan = 'gt';
    case AtLeast = 'gte';
    case LessThan = 'lt';
    case AtMost = 'lte';
    /** From the value to "to", both included; either may be left empty. */
    case Between = 'between';
    /** Is one of the values chosen (a choice, a relation: several at once). */
    case In = 'in';
    /** Is none of the values chosen; nothing at all is none of them either. */
    case NotIn = 'not_in';
    /** Has nothing: no relation, no value, an empty text - "orders without an invoice". No value to type. */
    case IsEmpty = 'empty';
    /** Has something. No value to type. */
    case IsNotEmpty = 'filled';

    /** What a text is compared by, a part of it first. */
    public const array TEXT = [
        self::Contains,
        self::NotContains,
        self::Equals,
        self::NotEquals,
        self::StartsWith,
        self::EndsWith,
    ];

    /** What a number is compared by. */
    public const array NUMBER = [
        self::Equals,
        self::NotEquals,
        self::GreaterThan,
        self::AtLeast,
        self::LessThan,
        self::AtMost,
        self::Between,
    ];

    /** What a date is compared by, a range first: "from - to". */
    public const array DATE = [self::Between, self::Equals, self::AtLeast, self::AtMost];

    /** What one of a set (a relation, a choice) is compared by. */
    public const array CHOICE = [self::Equals, self::NotEquals];

    /** One of a set compared also by several of it, and by having one at all: "assigned to me or to nobody". */
    public const array CHOICES = [
        self::Equals,
        self::NotEquals,
        self::In,
        self::NotIn,
        self::IsEmpty,
        self::IsNotEmpty,
    ];

    /** Whether there is anything at all - to add to the others: [...Comparison::TEXT, ...Comparison::PRESENCE]. */
    public const array PRESENCE = [self::IsEmpty, self::IsNotEmpty];

    public function key(): string
    {
        return $this->value;
    }

    /** Whether it compares with nothing typed. */
    public function needsValue(): bool
    {
        return !in_array($this, self::PRESENCE, true);
    }

    /** Whether it compares with several values at once. */
    public function isMany(): bool
    {
        return $this === self::In || $this === self::NotIn;
    }

    public function label(): string
    {
        return 'crud.filter.comparison.' . $this->value;
    }
}
