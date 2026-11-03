<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

/**
 * A {@see Page} whose total may be as far as the records were counted, not all of them: a table too large to count,
 * a search engine or an API saying "10 000+". The list then says "more than N" and offers no "select all N".
 * ListPage has it ($more).
 */
interface CountsApproximately
{
    /** Whether there are more records than $total. */
    public bool $more { get; }
}
