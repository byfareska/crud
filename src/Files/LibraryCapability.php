<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * What a library can do: the window of the library offers only that.
 */
enum LibraryCapability: string
{
    /** Its files can be listed, to pick one again. */
    case Browse = 'browse';

    /** Its files can be found by what is typed (their names). */
    case Search = 'search';

    /** Its files can be deleted from the panel. */
    case Remove = 'remove';
}
