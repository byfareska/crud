<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrudPage;

/** A second page "history" of the things: a mistake. */
#[AsCrudPage(resource: 'thing', name: 'history', component: 'Other:History')]
final class OtherHistoryPage
{
}
