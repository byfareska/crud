<?php

declare(strict_types=1);

namespace Byfareska\Crud\Filter;

/**
 * A way a filter compares a field with what was typed: the bundle's {@see Comparison}s, or one of the application's
 * own - "in the last N days", "starts with a word", "near" - which its driver knows how to apply (the doctrine driver:
 * a {@see \Byfareska\Crud\Driver\Doctrine\DoctrineComparison}). Offered by a filter next to the others:
 * `new FieldFilter('updatedAt', [new LastDays(), ...Comparison::DATE])`.
 */
interface ComparisonKind
{
    /** What it is in the address and the choice (?filters[updatedAt][comparison]=last_days): unique, lower-case. */
    public function key(): string;

    /** Translation key of its label in the choice. */
    public function label(): string;

    /** Whether it compares with something typed; false = it is chosen alone ("is empty"). */
    public function needsValue(): bool;

    /** Whether it compares with several values at once, chosen in a multiple choice ("is one of"). */
    public function isMany(): bool;
}
