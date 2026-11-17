<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Value;

use ArrayIterator;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingStatus;
use Byfareska\Crud\Value\PlainValueNormalizer;
use Byfareska\Crud\Value\TranslatableEnumNormalizer;
use Byfareska\Crud\Value\TypedValue;
use Byfareska\Crud\Value\ValueNormalizer;
use DateTimeImmutable;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * Values are made ready to print by the first normalizer that supports each.
 */
final class CrudValuesTest extends TestCase
{
    public function testAnEnumThatTranslatesItselfIsItsTranslationAndInJsonItsValue(): void
    {
        $translator = new Translator('pl');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['app.status.active' => 'aktywna'], 'pl');
        $values = new CrudValues([new TranslatableEnumNormalizer($translator), new PlainValueNormalizer()]);

        $status = $values->normalize(ThingStatus::Active);

        self::assertInstanceOf(TypedValue::class, $status);
        self::assertSame('enum', $status->kind);
        self::assertSame('aktywna', (string) $status);
        self::assertSame('"active"', json_encode($status));
        self::assertSame(['active', 'aktywna'], json_decode((string) json_encode([$status, (string) $status]), true));
        // One that does not translate itself is its value, as before.
        self::assertSame('read', $values->normalize(CrudOperation::Read));
    }

    public function testThePlainValuesOfPhp(): void
    {
        $values = new CrudValues([new PlainValueNormalizer()]);
        $uuid = new readonly class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return '0192f4a0-0000-7000-8000-000000000001';
            }
        };

        self::assertSame(
            [null, 3, 'read', '2026-10-31T12:00:00+00:00', '0192f4a0-0000-7000-8000-000000000001', ['a', 'read']],
            [
                $values->normalize(null),
                $values->normalize(3),
                $values->normalize(CrudOperation::Read),
                $values->normalize(new DateTimeImmutable('2026-10-31 12:00 UTC')),
                $values->normalize($uuid),
                $values->normalize(new ArrayIterator(['x' => 'a', 'y' => CrudOperation::Read])),
            ],
        );
        self::assertNull($values->normalize(new stdClass()), 'What none supports is nothing.');
    }

    public function testOneOfTheApplicationGoesFirstAndCanSayWhatAValueIs(): void
    {
        $money = new readonly class implements ValueNormalizer {
            public function supports(mixed $value): bool
            {
                return is_string($value) && str_ends_with($value, ' PLN');
            }

            public function normalize(mixed $value, CrudValues $values): TypedValue
            {
                assert(is_string($value));

                return new TypedValue('money', substr($value, 0, -4));
            }
        };
        $values = new CrudValues([$money, new PlainValueNormalizer()]);

        $price = $values->normalize('12.50 PLN');
        self::assertInstanceOf(TypedValue::class, $price);
        self::assertSame(['money', '12.50', '12.50'], [$price->kind, $price->value, (string) $price]);
        self::assertSame('12.50', $values->normalize('12.50'));
        self::assertSame($price, $values->normalize($price), 'A TypedValue is ready already.');
    }
}
