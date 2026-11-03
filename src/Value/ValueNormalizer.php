<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

/**
 * Makes a value of a record ready to print: what a driver puts into an item, and what a page prints of a value a use
 * case returned. A service, autoconfigured; the first one (by the priority of its tag, #[AsTaggedItem]) that supports
 * a value normalizes it. The bundle's own come last (negative priorities): plain values, enums, dates, Stringable,
 * JsonSerializable and lists ({@see PlainValueNormalizer}), then entities of Doctrine ORM (their id).
 *
 * One of the application's own goes first: a value object of money as `new TypedValue('money', '12.50')`, say.
 */
interface ValueNormalizer
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.value_normalizer';

    public function supports(mixed $value): bool;

    /**
     * A scalar, null, a list of those, or a TypedValue.
     *
     * @param CrudValues $values to normalize what the value holds (the entries of a list)
     */
    public function normalize(mixed $value, CrudValues $values): mixed;
}
