<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrudDetailsSection;
use Byfareska\Crud\Attribute\AsCrudFormSection;

#[AsCrudFormSection('thing', 'notes', 'notes', ListThingsDto::class)]
#[AsCrudFormSection('thing', 'costs', 'costs', ListThingsDto::class, role: 'ROLE_FINANCE')]
#[AsCrudDetailsSection('thing', 'history', 'Test:History')]
#[AsCrudDetailsSection('thing', 'margins', 'Test:Margins', role: 'ROLE_FINANCE')]
// Of another part of the application, declared later but placed first.
#[AsCrudDetailsSection('thing', 'summary', 'Test:Summary', priority: -10)]
final class ThingSections
{
}
