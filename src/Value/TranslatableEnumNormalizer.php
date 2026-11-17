<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use BackedEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnitEnum;

/**
 * A case of an enum that translates itself (TranslatableInterface): a TypedValue "enum" of a TranslatedValue - printed
 * as its trans() in the locale of the request, written in JSON as its value (or name). Before the plain values, which
 * take any other enum as its value.
 */
#[AsTaggedItem(priority: -90)]
final readonly class TranslatableEnumNormalizer implements ValueNormalizer
{
    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function supports(mixed $value): bool
    {
        return $value instanceof UnitEnum && $value instanceof TranslatableInterface;
    }

    public function normalize(mixed $value, CrudValues $values): TypedValue
    {
        assert($value instanceof UnitEnum && $value instanceof TranslatableInterface);
        $plain = $value instanceof BackedEnum ? $value->value : $value->name;
        $label = $this->translator instanceof TranslatorInterface ? $value->trans($this->translator) : (string) $plain;

        return new TypedValue('enum', new TranslatedValue($plain, $label));
    }
}
