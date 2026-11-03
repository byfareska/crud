<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrudPage;

/** A page of the whole resource "thing". */
#[AsCrudPage(resource: 'thing', name: 'report', component: 'Thing:Report', collection: true)]
final class ThingReportPage
{
}
