<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Makes values ready to print by the first {@see ValueNormalizer} that supports each; a value none supports is
 * nothing (null). A TypedValue is ready already.
 */
final readonly class CrudValues
{
    /**
     * @param iterable<ValueNormalizer> $normalizers by priority
     */
    public function __construct(
        #[AutowireIterator(ValueNormalizer::TAG)]
        private iterable $normalizers,
    ) {
    }

    public function normalize(mixed $value): mixed
    {
        if ($value instanceof TypedValue) {
            return $value;
        }
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($value)) {
                return $normalizer->normalize($value, $this);
            }
        }

        return null;
    }
}
