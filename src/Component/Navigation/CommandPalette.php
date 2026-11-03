<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Navigation;

use Byfareska\Crud\CommandPalette\CommandPalette as Palette;
use Byfareska\Crud\CommandPalette\PaletteItem;
use Byfareska\Crud\Panel\ScopedComponent;
use Byfareska\Crud\Security\Actor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The command palette: pages, commands and records, found as they are typed.
 */
#[AsLiveComponent('Panel:CommandPalette', template: 'components/Panel/CommandPalette.html.twig')]
final class CommandPalette implements ScopedComponent
{
    use DefaultActionTrait;

    private const string COMMANDS_PREFIX = '>';

    /** Where the browser keeps the recent items without somebody signed in. */
    private const string STORAGE_KEY = 'crud.palette.recent';

    #[LiveProp(writable: true)]
    public string $query = '';

    /**
     * Keys of recently chosen items; kept in the browser and sent when the palette opens.
     *
     * @var list<string>
     */
    #[LiveProp(writable: true)]
    public array $recent = [];

    /**
     * The panel the palette is in (the route scope of the page, e.g. "admin"): it offers what is for that panel. Set
     * by the page and again by the browser when the palette opens - the modal is kept across Turbo visits, also
     * from one panel to the other. Not a matter of access: what needs a role still asks for it.
     */
    #[LiveProp(writable: true)]
    public string $scope = '';

    public function __construct(
        private readonly Palette $palette,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly Actor $actor,
        #[Autowire(param: 'byfareska_crud.command_palette.limit')]
        private readonly int $limit = 14,
    ) {
    }

    /**
     * Where the browser keeps the recent items of the one signed in: of their own - two people of one browser do not
     * see what the other chose (the id is not in it, only a hash of it).
     */
    public function getStorageKey(): string
    {
        $id = $this->actor->id();

        return $id === null ? self::STORAGE_KEY : self::STORAGE_KEY . '.' . substr(hash('sha256', $id), 0, 16);
    }

    public function panelScope(): ?string
    {
        return $this->scope === '' ? null : $this->scope;
    }

    /**
     * @return array<string, list<PaletteItem>> items by group (a translation key), in display order
     */
    public function getGroups(): array
    {
        $items = $this->palette->search(
            query: $this->getTerm(),
            locale: $this->localeSwitcher->getLocale(),
            limit: $this->limit,
            commandsOnly: $this->isCommandsOnly(),
            recent: array_values(array_filter($this->recent, is_string(...))),
            scope: $this->scope === '' ? null : $this->scope,
        );

        $groups = [];
        foreach ($items as $item) {
            $groups[$item->group][] = $item;
        }

        return $groups;
    }

    /**
     * The typed text without the ">" commands prefix.
     */
    public function getTerm(): string
    {
        $query = trim($this->query);

        return $this->isCommandsOnly() ? trim(substr($query, \strlen(self::COMMANDS_PREFIX))) : $query;
    }

    /**
     * Splits a name around the first occurrence of the typed term, for highlighting.
     *
     * @return array{before: string, match: string, after: string}
     */
    public function highlight(string $name): array
    {
        $term = $this->getTerm();
        $position = '' === $term ? false : mb_stripos($name, $term);

        if (false === $position) {
            return ['before' => $name, 'match' => '', 'after' => ''];
        }

        return [
            'before' => mb_substr($name, 0, $position),
            'match' => mb_substr($name, $position, mb_strlen($term)),
            'after' => mb_substr($name, $position + mb_strlen($term)),
        ];
    }

    private function isCommandsOnly(): bool
    {
        return str_starts_with(trim($this->query), self::COMMANDS_PREFIX);
    }
}
