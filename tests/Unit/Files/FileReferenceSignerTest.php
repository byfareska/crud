<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileReferenceSigner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A reference a field takes back is one the server signed: not another file, nor one signed by another secret.
 */
final class FileReferenceSignerTest extends TestCase
{
    public function testOnlyTheReferenceItWasGivenForIsTaken(): void
    {
        $signer = new FileReferenceSigner('secret');
        $invoice = FileReference::parse('private://invoices/7.pdf');
        $signature = $signer->sign($invoice);

        self::assertTrue($signer->verify($invoice, $signature));
        self::assertFalse($signer->verify(FileReference::parse('private://invoices/8.pdf'), $signature));
        self::assertFalse($signer->verify(FileReference::parse('public://invoices/7.pdf'), $signature));
        self::assertFalse($signer->verify($invoice, ''));
        self::assertFalse($signer->verify($invoice, substr($signature, 0, -1)));
        self::assertFalse(new FileReferenceSigner('another secret')->verify($invoice, $signature));
    }

    public function testNoSecretSignsNothing(): void
    {
        // With an empty key, anybody could make the signature of any file of any storage.
        $this->expectException(InvalidArgumentException::class);

        new FileReferenceSigner('');
    }
}
