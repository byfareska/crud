<?php

declare(strict_types=1);

namespace Byfareska\Crud\Navigation;

use Byfareska\Crud\Attribute\AsCrud;
use Closure;

/**
 * A page put into the menu of a panel. Its texts are translation keys. Each panel - the route scope its pages are
 * in - has a menu of its own.
 */
final readonly class NavigationItem
{
    /** The section of the dashboard, the first one. */
    public const string SECTION_START = 'panel.nav.section.start';

    /** A section for what has none of its own. */
    public const string SECTION_MAIN = 'panel.nav.section.main';

    /** The section with what is one's own: the profile, the notifications. */
    public const string SECTION_ACCOUNT = 'panel.nav.section.account';

    /**
     * @param string                              $section         translation key of the heading the page is listed
     *                                                             under
     * @param string                              $label           translation key
     * @param string                              $icon            identifier without vendor prefix (e.g. "person-gear")
     * @param string|null                         $role            only somebody with this role sees the page
     * @param int                                 $priority        lower comes first; a section stands where its first
     *                                                             page does
     * @param string                              $scope           the panel whose menu has it: the route scope of its
     *                                                             page
     * @param array<string, scalar>               $routeParameters
     * @param string|null                         $resource        the #[AsCrud] resource the page is the list (or own
     *                                                             record) of: lit on every page of the resource, also
     *                                                             those of the application's (#[MapCrud]); null = lit
     *                                                             on its route only
     * @param string|null                         $url             an address in place of the route (another system, the
     *                                                             documentation): the route name is then only what
     *                                                             tells the item apart - and the palette has no entry
     *                                                             of it
     * @param (Closure(): (int|string|null))|int|string|null $badge
     *                                                             what the item shows next to its label: a number of
     *                                                             what waits ("3" orders to ship); a Closure is asked
     *                                                             only when the menu is drawn, null or 0 = none
     * @param string|null                         $target          where it opens: "_blank" for a new tab
     * @param array<string, string|int|bool|null> $attributes      more attributes of its link (data-* of a Stimulus
     *                                                             controller of yours)
     * @param string|null                         $parent          the route name of the item it is under ("Sales" >
     *                                                             "Orders", "Returns"): shown below it, in its section;
     *                                                             the parent is lit and open while one of them is
     * @param string|null                         $badgeVariant    how the badge looks: "danger" (5 overdue), "warning",
     *                                                             "success", "info", "secondary"; null = the theme's
     *                                                             plain one
     * @param array<string, scalar>               $labelParameters the parameters of the translation of the label
     * @param bool                                $translate       false for a label that is a text already (a view
     *                                                             saved by a user, a name from the database)
     */
    public function __construct(
        public string $section,
        public string $routeName,
        public string $label,
        public string $icon,
        public ?string $role = null,
        public int $priority = 0,
        public string $scope = AsCrud::DEFAULT_SCOPE,
        public array $routeParameters = [],
        public ?string $resource = null,
        public ?string $url = null,
        public Closure|int|string|null $badge = null,
        public ?string $target = null,
        public array $attributes = [],
        public ?string $parent = null,
        public ?string $badgeVariant = null,
        public array $labelParameters = [],
        public bool $translate = true,
    ) {
    }

    /**
     * What the item shows next to its label, asked now; null = nothing (0 too).
     */
    public function badgeText(): ?string
    {
        $badge = $this->badge instanceof Closure ? ($this->badge)() : $this->badge;

        return in_array($badge, [null, 0, ''], true) ? null : (string) $badge;
    }

    /**
     * The item as the extensions of the menu change it (NavigationExtension): the same with other values.
     */
    public function with(mixed ...$changes): self
    {
        // @phpstan-ignore argument.type (the constructor's own arguments, by name)
        return new self(...array_replace(get_object_vars($this), $changes));
    }
}
