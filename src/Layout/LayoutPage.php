<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * Where a layout of a resource is drawn: in the add and edit forms, or on the details page and in the preview drawer.
 */
enum LayoutPage: string
{
    case Form = 'form';
    case Detail = 'detail';
}
