<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Whether something may be done with a record: every {@see RecordGuard} of its resource allows it. Whether the
 * resource can do it at all is AsCrud::can().
 */
final readonly class RecordGuards
{
    /**
     * @param iterable<RecordGuard> $guards
     * @param iterable<RecordActionGuard> $actionGuards
     */
    public function __construct(
        #[AutowireIterator(RecordGuard::TAG)]
        private iterable $guards = [],
        #[AutowireIterator(RecordActionGuard::TAG)]
        private iterable $actionGuards = [],
    ) {
    }

    /**
     * Whether an action of the record (RecordAction, AsCrudPage) is for it - see RecordActionGuard.
     */
    public function allowsAction(string $action, RecordView $record): bool
    {
        foreach ($this->actionGuards as $guard) {
            if (
                in_array($guard->resource(), [$record->crud->name, RecordActionGuard::EVERY], true)
                && !$guard->allowsAction($action, $record)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * What runs an action of the application's own asks it, as the bundle's pages do.
     *
     * @throws CrudFailure 403 - refused
     */
    public function assertActionAllowed(string $action, RecordView $record): void
    {
        $refusal = $this->actionRefusal($action, $record);
        if ($refusal instanceof CrudFailure) {
            throw $refusal;
        }
    }

    /**
     * Why an action of the record is refused: what the guard that refused says (ExplainsRefusal), else a 403; null =
     * it is allowed.
     */
    public function actionRefusal(string $action, RecordView $record): ?CrudFailure
    {
        foreach ($this->actionGuards as $guard) {
            if (
                in_array($guard->resource(), [$record->crud->name, RecordActionGuard::EVERY], true)
                && !$guard->allowsAction($action, $record)
            ) {
                return ($guard instanceof ExplainsRefusal ? $guard->refusal($action, $record) : null)
                    ?? CrudFailure::forbidden();
            }
        }

        return null;
    }

    /**
     * Why an operation of the record is refused: what the guard that refused says (ExplainsRefusal), else a 403;
     * null = it is allowed.
     */
    public function refusal(CrudOperation $operation, RecordView $record): ?CrudFailure
    {
        foreach ($this->of($record->crud) as $guard) {
            if (!$guard->allows($operation, $record)) {
                return ($guard instanceof ExplainsRefusal ? $guard->refusal($operation, $record) : null)
                    ?? CrudFailure::forbidden();
            }
        }

        return null;
    }

    /**
     * Whether a refusal says why (ExplainsRefusal): a button is then shown disabled, with it as its hint.
     */
    public static function explains(CrudFailure $refusal): bool
    {
        return $refusal->translationKey !== null && $refusal->translationKey !== 'crud.error.forbidden';
    }

    public function allows(CrudOperation $operation, RecordView $record): bool
    {
        foreach ($this->of($record->crud) as $guard) {
            if (!$guard->allows($operation, $record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws CrudFailure 403 - refused
     */
    public function assertAllowed(CrudOperation $operation, RecordView $record): void
    {
        $refusal = $this->refusal($operation, $record);
        if ($refusal instanceof CrudFailure) {
            throw $refusal;
        }
    }

    /**
     * Whether a resource has guards of its records at all (RecordGuard): without them every record its scopes leave
     * is allowed, and nothing has to be read to tell.
     */
    public function guards(AsCrud $crud): bool
    {
        foreach ($this->of($crud) as $_) {
            return true;
        }

        return false;
    }

    /**
     * What the guards of a resource (and of its actions) need of an item of the list.
     *
     * @return list<string>
     */
    public function fields(AsCrud $crud): array
    {
        $fields = [];
        foreach ($this->of($crud) as $guard) {
            array_push($fields, ...$guard->fields());
        }
        foreach ($this->actionGuards as $guard) {
            if (in_array($guard->resource(), [$crud->name, RecordActionGuard::EVERY], true)) {
                array_push($fields, ...$guard->fields());
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * @return iterable<RecordGuard>
     */
    private function of(AsCrud $crud): iterable
    {
        foreach ($this->guards as $guard) {
            if ($guard->resource() === $crud->name || $guard->resource() === RecordGuard::EVERY) {
                yield $guard;
            }
        }
    }
}
