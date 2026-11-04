<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Doctrine\Record;

use Byfareska\Crud\Files\Doctrine\Mapped\AbstractFileRecord;
use Doctrine\ORM\Mapping as ORM;

/**
 * A file of a library in the table "crud_file": the bundle's entity, mapped only when byfareska_crud.files is enabled
 * with the library "doctrine" and no entity of the application's (files.entity). Create the table with a migration of
 * the application (doctrine:migrations:diff).
 */
#[ORM\Entity]
#[ORM\Table(name: 'crud_file')]
#[ORM\UniqueConstraint(name: 'crud_file_reference', columns: ['storage', 'path'])]
#[ORM\Index(name: 'crud_file_created', columns: ['storage', 'created_at'])]
class CrudFileRecord extends AbstractFileRecord
{
}
