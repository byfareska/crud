<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Crud;

use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\RecordGuard;
use Byfareska\Crud\Resource\RecordView;

/**
 * A contract whose number says it is signed ("SIGNED-...") is not edited nor deleted any more - the guard of "a paid
 * invoice is not changed" of an application. Every other contract is left alone.
 */
final readonly class SignedContracts implements RecordGuard
{
    public function resource(): string
    {
        return 'contract';
    }

    public function fields(): array
    {
        return ['number'];
    }

    public function allows(CrudOperation $operation, RecordView $record): bool
    {
        $number = $record->item['number'] ?? null;

        return $operation === CrudOperation::Read || !is_string($number) || !str_starts_with($number, 'SIGNED-');
    }
}
