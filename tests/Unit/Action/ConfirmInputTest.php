<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Action;

use Byfareska\Crud\Action\ConfirmInput;
use Byfareska\Crud\Resource\CrudFailure;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * What the dialog asked for is checked on the server: the browser could send anything, or nothing.
 */
final class ConfirmInputTest extends TestCase
{
    public function testARequiredValueIsThere(): void
    {
        $reason = new ConfirmInput('app.delete_reason');

        self::assertSame('a duplicate', $reason->accepted('  a duplicate '));

        foreach ([null, '', '   '] as $nothing) {
            try {
                $reason->accepted($nothing);
                self::fail('Nothing was accepted for a required value.');
            } catch (CrudFailure $failure) {
                self::assertSame(422, $failure->status);
                self::assertSame(ConfirmInput::REQUIRED, $failure->translationKey);
            }
        }
    }

    public function testAnOptionalValueMayBeMissing(): void
    {
        self::assertNull(new ConfirmInput('app.delete_reason', required: false)->accepted(' '));
    }

    public function testANumberAndADay(): void
    {
        $days = new ConfirmInput('app.postpone_by', type: 'number');
        $until = new ConfirmInput('app.postpone_until', type: 'date');

        self::assertSame('14', $days->accepted('14'));
        self::assertSame('2026-12-24', $until->accepted('2026-12-24'));
        $wrong = [[$days, 'two weeks', ConfirmInput::NOT_A_NUMBER], [$until, '2026-02-30', ConfirmInput::NOT_A_DAY]];
        foreach ($wrong as [$input, $value, $key]) {
            try {
                $input->accepted($value);
                self::fail($value . ' was taken.');
            } catch (CrudFailure $failure) {
                self::assertSame($key, $failure->translationKey);
            }
        }
    }

    public function testChoicesOfFinishedTexts(): void
    {
        $owner = new ConfirmInput('app.owner', choices: ['Anna Kowalska' => '7'], translateChoices: false);
        $attributes = $owner->attributes(new IdentityTranslator());

        self::assertStringContainsString('"label":"Anna Kowalska"', $attributes['data-confirm-input-value']);
    }

    public function testAChoiceIsOneOfTheChoices(): void
    {
        $status = new ConfirmInput('app.status', choices: ['app.status.open' => 'open', 'app.status.done' => 'done']);

        self::assertSame('done', $status->accepted('done'));

        $this->expectException(CrudFailure::class);
        $this->expectExceptionMessage('none of the choices');

        $status->accepted('paid');
    }
}
