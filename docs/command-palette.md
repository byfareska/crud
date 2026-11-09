# Command palette

The search field in the top bar opens a palette offering:

- the pages of the panel (`CommandPaletteSource` → `PaletteEntry`);
- commands (typed after `>`);
- records found by typing (`CommandPaletteRecordSource`), searched through each resource's list search.

Only what the user may see in the current panel is offered. Recent items are kept in the browser under a key of the
signed-in user.

## A search of your own

How the palette searches is one service, `PaletteSearch`:

```php
interface PaletteSearch
{
    /** @return list<string> keys of the matching items, best first */
    public function search(string $query, array $items, string $locale, int $limit): array;
}
```

The default, `TextPaletteSearch`, matches letters and ignores accents (with `ext-intl`). To search by meaning
instead:

```php
final readonly class EmbeddingPaletteSearch implements IndexedPaletteSearch
{
    public function __construct(private Embeddings $embeddings, private VectorStore $store) {}

    public function index(array $items, string $locale): int
    {
        foreach ($items as $key => $item) {
            $this->store->put("$locale:$key", $this->embeddings->embed(
                "$item->name. $item->description " . implode(', ', $item->keywords),
            ));
        }

        return count($items);
    }

    public function search(string $query, array $items, string $locale, int $limit): array
    {
        $nearest = $this->store->nearest($this->embeddings->embed($query), prefix: "$locale:", limit: $limit);

        // $items holds only what this user may see in this panel
        return array_values(array_filter($nearest, static fn (string $key): bool => isset($items[$key])));
    }
}
```

```yaml
byfareska_crud:
    command_palette:
        search: App\Palette\EmbeddingPaletteSearch
```

Fill an index after every deploy, because the entries come from the code:

```sh
bin/console byfareska:crud:command-palette:index
```

## Ranking and limits

- `PaletteRanker` decides what is listed of what was found. The default `BalancedPaletteRanker` keeps at least half
  of the places for pages, so records don't push them out.
- `command_palette.limit` and `records_per_source` set how many items are shown.
- `command_palette.suggested` lists entries shown before anything is typed.
- A record source that implements `ScopedRecordSource` searches only the current panel.
- An entry with `quickAction: true` also becomes a tile of the quick actions widget.

Each resource has keywords in `crud.<name>.palette.keywords`.
