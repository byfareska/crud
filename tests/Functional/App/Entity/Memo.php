<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Entity;

use Byfareska\Crud\Action\DuplicateAction;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineForm;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Doctrine\ORM\Mapping as ORM;

/**
 * A note with a remark nobody is to see ("hidden"), which its form - the fields the application names - edits all
 * the same; copied into a new one by its DuplicateAction.
 */
#[ORM\Entity]
#[AsCrud(
    name: 'memo',
    path: '/memos',
    icon: 'sticky',
    source: new DoctrineSource(Memo::class, form: new DoctrineForm(fields: ['title', 'remark'])),
    columns: ['title', 'remark'],
    actions: [new DuplicateAction()],
    hidden: ['remark'],
)]
class Memo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $title = '';

    #[ORM\Column(nullable: true)]
    public ?string $remark = null;
}
