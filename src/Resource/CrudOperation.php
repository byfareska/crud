<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * What can be done with the records of a resource; its source says which of them it can do (CrudSource::supports()).
 * List, Create and Read are also what the command palette can offer (AsCrud: "palette").
 */
enum CrudOperation: string
{
    case List = 'list';
    case Read = 'read';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
