<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Filter;

use Byfareska\Crud\Filter\Compared;
use Byfareska\Crud\Filter\Comparison;
use Byfareska\Crud\Filter\ComparisonType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

final class ComparisonTypeTest extends TestCase
{
    public function testComparesTheFirstWayUntilAnotherIsChosen(): void
    {
        $form = $this->submit(Comparison::NUMBER, ['value' => '5']);

        self::assertEquals(new Compared(Comparison::Equals, 5), $form->getData());
    }

    public function testTakesTheComparisonChosen(): void
    {
        $form = $this->submit(Comparison::NUMBER, ['comparison' => 'gte', 'value' => '5']);

        self::assertEquals(new Compared(Comparison::AtLeast, 5), $form->getData());
    }

    public function testTakesAPlainValueForTheValue(): void
    {
        $form = $this->submit(Comparison::NUMBER, '7');

        self::assertTrue($form->isValid());
        self::assertEquals(new Compared(Comparison::Equals, 7), $form->getData());
    }

    /**
     * @return iterable<string, array{array<string, string|null>|null}>
     */
    public static function nothing(): iterable
    {
        yield 'nothing sent' => [null];
        yield 'a comparison alone' => [['comparison' => 'gt']];
        yield 'empty parts' => [['comparison' => null, 'value' => null, 'to' => null]];
        yield 'an empty range' => [['comparison' => 'between', 'value' => '', 'to' => '']];
    }

    /**
     * @param array<string, string|null>|null $submitted
     */
    #[DataProvider('nothing')]
    public function testAsksForNothingWithoutAValue(?array $submitted): void
    {
        $form = $this->submit(Comparison::NUMBER, $submitted);

        self::assertTrue($form->isValid());
        self::assertNull($form->getData());
    }

    public function testARangeMayBeOpenAtEitherEnd(): void
    {
        $from = $this->submit(Comparison::NUMBER, ['comparison' => 'between', 'value' => '3', 'to' => '']);
        $to = $this->submit(Comparison::NUMBER, ['comparison' => 'between', 'value' => '', 'to' => '9']);

        self::assertEquals(new Compared(Comparison::Between, 3), $from->getData());
        self::assertEquals(new Compared(Comparison::Between, null, 9), $to->getData());
    }

    public function testTheEndOfARangeCountsOnlyForBetween(): void
    {
        $form = $this->submit(Comparison::NUMBER, ['comparison' => 'lt', 'value' => '3', 'to' => '9']);

        self::assertEquals(new Compared(Comparison::LessThan, 3), $form->getData());
    }

    public function testHasNoEndOfARangeWithoutBetween(): void
    {
        $form = $this->submit(Comparison::CHOICE, ['value' => '3']);

        self::assertFalse($form->has('to'));
    }

    public function testRefusesAComparisonItDoesNotOffer(): void
    {
        $form = $this->submit(Comparison::CHOICE, ['comparison' => 'gt', 'value' => '3']);

        self::assertFalse($form->isValid());
    }

    /**
     * @param list<Comparison> $comparisons
     * @param array<string, string|null>|string|null $submitted
     *
     * @return FormInterface<mixed>
     */
    private function submit(array $comparisons, array|string|null $submitted): FormInterface
    {
        $form = Forms::createFormFactory()->create(ComparisonType::class, null, [
            'comparisons' => $comparisons,
            'value_type' => IntegerType::class,
        ]);

        return $form->submit($submitted);
    }
}
