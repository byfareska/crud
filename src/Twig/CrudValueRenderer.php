<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Twig\TemplateWrapper;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\Column;
use Byfareska\Crud\Resource\CrudCell;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\TypedValue;
use Byfareska\Crud\Value\ValueFormat;
use Byfareska\Crud\Value\ValueFormatter;
use Byfareska\Crud\Value\ValueView;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * Renders one value of a #[AsCrud] resource the way Symfony forms render a field: by the first Twig block that
 * exists, looked for from the most specific name to the most general one.
 *
 * For the field "createdAt" of the resource "user" holding a date, shown in the list:
 *
 *   crud_user_created_at_list, crud_user_created_at: this field of this resource
 *   field_created_at_list, field_created_at: a field with this name, in any resource
 *   type_date_list, type_date: the kind of value (and before it the column's own "type")
 *   value: anything
 *
 * The "_list" / "_detail" variants apply to one view only: "list" is the table, "detail" the preview drawer and the
 * details page. Each name is looked for in the resource's own theme (#[AsCrud(theme: ...)]) and then in
 * @CrudTheme/crud/values.html.twig. An empty value skips the field and resource blocks, so those never have to
 * handle "nothing": it is rendered by type_empty.
 *
 * A block gets: value (ready to print: a date is a DateTimeImmutable, a list holds scalars), raw (as the use case
 * returned it), item (the whole record), column, crud and view.
 *
 * The value is made ready by the ValueNormalizers first (CrudValues); one that says what it is (a TypedValue) is
 * printed by the blocks of its kind. A column with a format (`'stock' => new ComponentFormat('App:StockLevel')`), or
 * else a TypedValue with one, is printed by the ValueFormatter the format names, before any block - see ValueFormat.
 * An empty value is still type_empty.
 */
final readonly class CrudValueRenderer
{
    public const string DEFAULT_THEME = '@CrudTheme/crud/values.html.twig';

    /**
     * @param list<string> $themes templates of blocks of the application looked into for every resource, after the
     *     resource's own theme and before the bundle's (byfareska_crud.value_themes): a type_money for every resource
     */
    public function __construct(
        #[AutowireLocator(ValueFormatter::TAG)]
        private ContainerInterface $formatters,
        private CrudValues $values,
        #[Autowire(param: 'byfareska_crud.value_themes')]
        private array $themes = [],
    ) {
    }

    /**
     * An entry of a list value (type_list): one that says what it is (a TypedValue) by the blocks of its kind -
     * type_<kind>_entry, type_<kind>_<view>, type_<kind> - anything else by list_entry.
     *
     * @param array<string, mixed> $item
     * @param 'list'|'detail' $view
     */
    #[AsTwigFunction('crud_list_entry', needsEnvironment: true, isSafe: ['html'])]
    public function entry(
        Environment $twig,
        AsCrud $crud,
        Column $column,
        mixed $entry,
        array $item,
        string $view,
    ): string {
        $cell = $entry instanceof TypedValue ? CrudCell::of($entry) : ['kind' => null, 'value' => $entry];
        if ($entry instanceof TypedValue && $entry->format instanceof ValueFormat && $cell['kind'] !== 'empty') {
            return $this->formatter($entry->format)->format(
                $entry->format,
                new ValueView($crud, $column, $cell['value'], $entry, $item, $view),
            );
        }

        $context = [
            'value' => $cell['value'],
            'raw' => $entry,
            'item' => $item,
            'column' => $column,
            'crud' => $crud,
            'view' => $view,
        ];
        $blocks = $cell['kind'] === null
            ? ['list_entry']
            : [
                sprintf('type_%s_entry', $cell['kind']),
                sprintf('type_%s_%s', $cell['kind'], $view),
                'type_' . $cell['kind'],
                'list_entry',
            ];
        foreach ($blocks as $block) {
            foreach ($this->load($twig, $crud) as $theme) {
                if ($theme->hasBlock($block, $context)) {
                    return $theme->renderBlock($block, $context);
                }
            }
        }

        throw new LogicException('No theme has the "list_entry" block.');
    }

    /**
     * The templates the blocks are looked for in: the resource's theme, those of byfareska_crud.value_themes, the
     * bundle's.
     *
     * @return list<TemplateWrapper>
     */
    private function load(Environment $twig, AsCrud $crud): array
    {
        return array_map(
            $twig->load(...),
            array_values(array_filter([$crud->theme, ...$this->themes, self::DEFAULT_THEME])),
        );
    }

    /**
     * @param array<string, mixed> $item the record the value belongs to
     * @param 'list'|'detail' $view
     */
    #[AsTwigFunction('crud_value', needsEnvironment: true, isSafe: ['html'])]
    public function render(
        Environment $twig,
        AsCrud $crud,
        Column $column,
        mixed $value,
        array $item,
        string $view,
    ): string {
        $normalized = $this->values->normalize($value);
        $cell = CrudCell::of($normalized);
        $format = $column->format ?? ($normalized instanceof TypedValue ? $normalized->format : null);
        if ($format instanceof ValueFormat && $cell['kind'] !== 'empty') {
            return $this->formatter($format)->format(
                $format,
                new ValueView($crud, $column, $cell['value'], $value, $item, $view),
            );
        }

        $context = [
            'value' => $cell['value'],
            'raw' => $value,
            'item' => $item,
            'column' => $column,
            'crud' => $crud,
            'view' => $view,
        ];

        $themes = $this->load($twig, $crud);
        foreach ($this->blockNames($crud, $column, $cell['kind'], $view) as $block) {
            foreach ($themes as $theme) {
                if ($theme->hasBlock($block, $context)) {
                    return $theme->renderBlock($block, $context);
                }
            }
        }

        throw new LogicException(sprintf(
            'No theme has the "value" block, the last resort for the field "%s".',
            $column->field,
        ));
    }

    /**
     * @throws LogicException the format names no formatter there is
     */
    private function formatter(ValueFormat $format): ValueFormatter
    {
        $id = $format->formatter();
        if (!$this->formatters->has($id)) {
            throw new LogicException(sprintf(
                'The format %s is printed by %s, which is no service tagged "%s" - is it autoconfigured?',
                $format::class,
                $id,
                ValueFormatter::TAG,
            ));
        }

        $formatter = $this->formatters->get($id);
        assert($formatter instanceof ValueFormatter);

        return $formatter;
    }

    /**
     * @return list<string> most specific first
     */
    public function blockNames(AsCrud $crud, Column $column, string $kind, string $view): array
    {
        $prefixes = [];
        if ($kind !== 'empty') {
            $field = $this->snake($column->field);
            $prefixes[] = sprintf('crud_%s_%s', $this->snake($crud->name), $field);
            $prefixes[] = 'field_' . $field;
            if ($column->type !== null) {
                $prefixes[] = 'type_' . $this->snake($column->type);
            }
        }
        $prefixes[] = 'type_' . $kind;

        $names = [];
        foreach (array_unique($prefixes) as $prefix) {
            $names[] = $prefix . '_' . $view;
            $names[] = $prefix;
        }
        $names[] = 'value';

        return $names;
    }

    /**
     * "createdAt" -> "created_at".
     */
    private function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
