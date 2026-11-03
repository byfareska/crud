<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

use Closure;

/**
 * Something a part of the application offers in the command palette that is the same for everybody and does not depend
 * on stored data: a page, a filtered list, a command. Its texts are translation keys, so it can be shown and searched
 * in every language.
 */
final readonly class PaletteEntry
{
    /**
     * @param string $key stable and unique across the application, e.g. "crud.user.list"; what the search index and the
     *     browser remember it by
     * @param string $group translation key of the heading it is listed under
     * @param string $name translation key
     * @param string $icon identifier without vendor prefix (e.g. "box-seam")
     * @param string|null $description translation key; a key without a text is skipped
     * @param string|null $keywords translation key of extra search terms that are not shown, separated by commas; a key
     *     without a text is skipped
     * @param string|null $routeName null when the entry only triggers a client action
     * @param array<string, scalar|list<scalar>> $routeParameters
     * @param string|null $clientAction name of a browser event (e.g. "theme:toggle")
     * @param bool $command commands are the only entries listed after ">"
     * @param string|null $role only somebody with this role is offered the entry
     * @param string|null $scope only the palette of this panel (the route scope of the page, e.g. "admin") offers it;
     *     null = every panel
     * @param (Closure(): bool)|null $offered whether the one asking is offered it, besides $role and $scope - what
     *     depends on more than a role (an operation the request refuses); not asked when an index is built for
     *     everybody
     * @param bool $quickAction a tile the quick actions widget can offer (besides the entries "….create": the pages to
     *     add a record) - a command of the application's own
     * @param array<string, scalar> $nameParameters the parameters of the translation of $name ("Orders ({count})")
     * @param bool $translate false when $name is a finished text, not a translation key (a label from the database)
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $name,
        public string $icon,
        public ?string $description = null,
        public ?string $keywords = null,
        public ?string $routeName = null,
        public array $routeParameters = [],
        public ?string $clientAction = null,
        public bool $command = false,
        public ?string $role = null,
        public ?string $scope = null,
        public ?Closure $offered = null,
        public bool $quickAction = false,
        public array $nameParameters = [],
        public bool $translate = true,
    ) {
    }
}
