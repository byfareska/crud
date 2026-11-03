<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DefaultDoctrineExceptionMapper;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

final class DefaultDoctrineExceptionMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{Throwable, int, string}>
     */
    public static function errors(): iterable
    {
        $driver = new class ('refused') extends AbstractException {
        };

        yield 'a unique key' => [new UniqueConstraintViolationException($driver, null), 409, 'crud.error.taken'];
        yield 'a foreign key' => [new ForeignKeyConstraintViolationException($driver, null), 409, 'crud.error.in_use'];
        yield 'changed meanwhile' => [OptimisticLockException::lockFailed(new stdClass()), 409, 'crud.error.changed'];
        yield 'a deadlock' => [new DeadlockException($driver, null), 503, 'crud.error.retry'];
        yield 'a value required' => [new NotNullConstraintViolationException($driver, null), 422, 'crud.error.refused'];
    }

    #[DataProvider('errors')]
    public function testAnErrorOfTheDatabaseIsAFailureToShow(Throwable $error, int $status, string $key): void
    {
        $failure = new DefaultDoctrineExceptionMapper()->map($this->crud(), CrudOperation::Update, $error);

        self::assertInstanceOf(CrudFailure::class, $failure);
        self::assertSame($status, $failure->status);
        self::assertSame($key, $failure->translationKey);
        self::assertSame($error, $failure->getPrevious());
    }

    public function testAFailureIsShownAsItIsAndAnErrorOfTheApplicationIsNone(): void
    {
        $mapper = new DefaultDoctrineExceptionMapper();
        $failure = new CrudFailure(409, 'paid', translationKey: 'app.invoice.paid');

        self::assertSame($failure, $mapper->map($this->crud(), CrudOperation::Delete, $failure));
        self::assertNull($mapper->map($this->crud(), CrudOperation::Delete, new LogicException('a bug')));
    }

    private function crud(): AsCrud
    {
        return new AsCrud(name: 'thing', path: '/things', icon: 'box', list: ListThingsDto::class);
    }
}
