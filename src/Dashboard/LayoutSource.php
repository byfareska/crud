<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * Whose layout a dashboard shows.
 */
enum LayoutSource: string
{
    /** The one signed in arranged it. */
    case Own = 'own';
    /** The one stored for everybody. */
    case Shared = 'shared';
    /** Nobody stored one: the widgets marked "default", packed. */
    case Code = 'code';
}
