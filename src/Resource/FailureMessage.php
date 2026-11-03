<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * What to tell the person an operation was refused for (a form, a toast, an error page). The bundle's is
 * {@see DefaultFailureMessage}: the translation the failure names, else its own text. Alias this interface to change
 * it - other texts, a reference to the log for what has no translation.
 */
interface FailureMessage
{
    public function of(CrudFailure $failure): string;
}
