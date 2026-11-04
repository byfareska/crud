<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileLibrary;
use Byfareska\Crud\Files\NoFileLibrary;
use Byfareska\Crud\Files\Testing\FileLibraryContractTestCase;

final class NoFileLibraryTest extends FileLibraryContractTestCase
{
    protected function library(): FileLibrary
    {
        return new NoFileLibrary();
    }
}
