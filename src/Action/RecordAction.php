<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * Something that can be done with one record of a #[AsCrud] resource, beyond showing, editing and deleting it -
 * declared in {@see AsCrud::$actions}. It only says what it is: the {@see RecordActionHandler} it names (a service)
 * makes its button for a record, so a kind of action of the application's own is two small classes.
 *
 * The bundle's kinds: {@see RouteAction} (a link to a page), {@see ComponentAction} (a Twig component draws it) and
 * the built-in {@see DetailsAction}, {@see EditAction} and {@see DeleteAction}.
 *
 * Its text is the translation crud.<resource>.action.<name>. It is offered wherever a record has its actions - the
 * list, the preview drawer, the details page and the edit form; in the list it waits in the row menu, unless it is
 * "inline": then it is a button of its own on every row.
 */
interface RecordAction
{
    /** Lower-case; part of the translation key, tells the actions of a resource apart. */
    public string $name { get; }

    /** Only somebody with this role is offered it; null = everybody the resource is for. */
    public ?string $role { get; }

    /** In the list a button on the row instead of an item of the row menu. */
    public bool $inline { get; }

    /**
     * What the action needs of an item of the list besides its columns, e.g. the field it copies.
     *
     * @return list<string>
     */
    public function fields(): array;

    /**
     * The service that makes its button.
     *
     * @return class-string<RecordActionHandler>
     */
    public function handler(): string;
}
