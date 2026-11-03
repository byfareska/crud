<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrudPage;

/** A page of the records of every resource. */
#[AsCrudPage(resource: AsCrudPage::EVERY, name: 'audit', component: 'Audit:Log')]
final class AuditPage
{
}
