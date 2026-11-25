<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\RecordChanges;
use Byfareska\Crud\Resource\RecordGuard;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * A change of a record whose id the browser sent (a live action of a row, Crud:Run) goes only to a record of the
 * scopes of its resource - also for somebody who may not read the record.
 */
final class RecordChangesTest extends TestCase
{
    public function testARecordOutOfTheScopesIsNotChangedByOneWhoMayNotReadIt(): void
    {
        $crud = $this->invoices()->refusing(CrudOperation::Read);
        $done = [];

        try {
            new RecordChanges($this->records(), new RecordGuards())->change(
                $crud,
                '9',
                'approve',
                static function (RecordView $record) use (&$done): void {
                    $done[] = $record->id;
                },
            );
            self::fail('A record of another tenant was changed.');
        } catch (CrudFailure $failure) {
            self::assertSame(404, $failure->status);
        }
        self::assertSame([], $done);
    }

    public function testTheGuardsGoByTheRecordAlsoForOneWhoMayNotReadIt(): void
    {
        $crud = $this->invoices()->refusing(CrudOperation::Read);
        $paid = new readonly class implements RecordGuard {
            public function resource(): string
            {
                return 'invoice';
            }

            public function fields(): array
            {
                return ['paid'];
            }

            public function allows(CrudOperation $operation, RecordView $record): bool
            {
                return $operation !== CrudOperation::Update || ($record->item['paid'] ?? null) === false;
            }
        };

        $this->expectException(CrudFailure::class);

        new RecordChanges($this->records(), new RecordGuards([$paid]))->change(
            $crud,
            '7',
            'approve',
            static fn (RecordView $record): never => self::fail('A paid invoice was changed.'),
        );
    }

    public function testARecordOfTheScopesIsChanged(): void
    {
        $seen = new RecordChanges($this->records(), new RecordGuards())->change(
            $this->invoices()->refusing(CrudOperation::Read),
            '7',
            'approve',
            static fn (RecordView $record): array => $record->item,
            operation: null,
        );

        self::assertSame(['id' => '7', 'paid' => true], $seen);
    }

    private function invoices(): AsCrud
    {
        return new AsCrud('invoice', '/invoices', 'receipt', source: new DoctrineSource(stdClass::class));
    }

    /**
     * Records as the bundle's CrudRecords has them: nothing read for one who may not read (view()), and only invoice
     * 7 in the scopes of the resource.
     */
    private function records(): CrudRecords
    {
        return new class implements CrudRecords {
            public function read(AsCrud $crud, string $id): array
            {
                return $id === '7' ? ['id' => '7', 'paid' => true] : throw CrudFailure::notFound();
            }

            public function details(AsCrud $crud, string $id): array
            {
                return ['id' => $id, 'title' => null, 'rows' => [], 'item' => $this->read($crud, $id)];
            }

            public function view(AsCrud $crud, string $id, RecordScreen $screen): RecordView
            {
                return new RecordView(
                    $crud,
                    $id,
                    $crud->can(CrudOperation::Read) ? $this->read($crud, $id) : [],
                    $screen,
                );
            }

            public function delete(AsCrud $crud, string $id): void
            {
            }
        };
    }
}
