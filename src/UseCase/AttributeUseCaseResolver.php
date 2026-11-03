<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use Byfareska\Crud\Attribute\UseCase;
use LogicException;
use ReflectionClass;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The use case of a DTO by its #[UseCase], read once a class.
 */
final class AttributeUseCaseResolver implements UseCaseResolver, ResetInterface
{
    /** @var array<class-string, UseCase> */
    private array $read = [];

    public function of(MapsToRequest $dto): UseCase
    {
        if (isset($this->read[$dto::class])) {
            return $this->read[$dto::class];
        }
        $attribute = new ReflectionClass($dto)->getAttributes(UseCase::class)[0] ?? null;
        if ($attribute === null) {
            throw new LogicException(sprintf('"%s" does not name its use case with #[UseCase].', $dto::class));
        }

        return $this->read[$dto::class] = $attribute->newInstance();
    }

    public function reset(): void
    {
        $this->read = [];
    }
}
