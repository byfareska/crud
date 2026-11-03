<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Dto;

use Byfareska\Crud\UseCase\MapsToRequest;

/**
 * Makes the DTOs the "dto" driver runs and its forms start with. The bundle's is {@see ConstructorDtoFactory}: the
 * constructor, given the id when it takes one (a readonly id), its other arguments left to their defaults. Alias the
 * interface for DTOs made another way - a named constructor, arguments from a service.
 */
interface DtoFactory
{
    /**
     * @template T of MapsToRequest
     *
     * @param class-string<T> $class
     * @param string|null $id the record it is addressed to; null = a new one
     *
     * @return T
     */
    public function create(string $class, ?string $id = null): MapsToRequest;
}
