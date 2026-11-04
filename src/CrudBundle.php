<?php

declare(strict_types=1);

namespace Byfareska\Crud;

use Byfareska\Crud\Action\BulkActionHandler;
use Byfareska\Crud\Action\RecordActionHandler;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudDetailsSection;
use Byfareska\Crud\Attribute\AsCrudFormSection;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\Attribute\AsDashboardWidget;
use Byfareska\Crud\Attribute\AsTopbarWidget;
use Byfareska\Crud\Autocomplete\AutocompleteRelationFilterField;
use Byfareska\Crud\Autocomplete\RelationAutocompleter;
use Byfareska\Crud\Autocomplete\RelationChoiceLabels;
use Byfareska\Crud\Chart\DayBucket;
use Byfareska\Crud\Chart\NamedDayBucket;
use Byfareska\Crud\Chart\Sql\DoctrineDayBucket;
use Byfareska\Crud\Chart\Sql\PlatformDayBucket;
use Byfareska\Crud\CommandPalette\BalancedPaletteRanker;
use Byfareska\Crud\CommandPalette\PaletteRanker;
use Byfareska\Crud\CommandPalette\PaletteSearch;
use Byfareska\Crud\CommandPalette\TextPaletteSearch;
use Byfareska\Crud\Dashboard\DashboardKey;
use Byfareska\Crud\Dashboard\DashboardLayouts;
use Byfareska\Crud\Dashboard\DashboardPolicy;
use Byfareska\Crud\Dashboard\DashboardWidgets;
use Byfareska\Crud\Dashboard\ParametersDashboardKey;
use Byfareska\Crud\Dashboard\RoleDashboardPolicy;
use Byfareska\Crud\Dashboard\Storage\CacheDashboardLayouts;
use Byfareska\Crud\Dashboard\Storage\DoctrineDashboardLayouts;
use Byfareska\Crud\DependencyInjection\CollectCrudDeclarationsPass;
use Byfareska\Crud\DependencyInjection\CollectWidgetsPass;
use Byfareska\Crud\DependencyInjection\ComponentCollisionPass;
use Byfareska\Crud\DependencyInjection\PanelLiveUrlsPass;
use Byfareska\Crud\Driver\Doctrine\ChoiceRelationFilterField;
use Byfareska\Crud\Driver\Doctrine\ConstructorEntityFactory;
use Byfareska\Crud\Driver\Doctrine\DefaultDoctrineExceptionMapper;
use Byfareska\Crud\Driver\Doctrine\DoctrineDriver;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityFactory;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityNormalizer;
use Byfareska\Crud\Driver\Doctrine\DoctrineExceptionMapper;
use Byfareska\Crud\Driver\Doctrine\DoctrineFilterGuesser;
use Byfareska\Crud\Driver\Doctrine\DoctrineFilterHandler;
use Byfareska\Crud\Driver\Doctrine\DoctrineListExtension;
use Byfareska\Crud\Driver\Doctrine\DoctrinePersister;
use Byfareska\Crud\Driver\Doctrine\DefaultDoctrineIdCodec;
use Byfareska\Crud\UseCase\AttributeUseCaseResolver;
use Byfareska\Crud\UseCase\UseCaseResolver;
use Byfareska\Crud\Driver\Doctrine\DoctrineIdCodec;
use Byfareska\Crud\Driver\Doctrine\DoctrineTextMatch;
use Byfareska\Crud\Driver\Doctrine\LowerTextMatch;
use Byfareska\Crud\Driver\Doctrine\DoctrineRecordTransaction;
use Byfareska\Crud\Driver\Doctrine\DoctrineRelations;
use Byfareska\Crud\Driver\Doctrine\DoctrineScope;
use Byfareska\Crud\Driver\Doctrine\DoctrineScopes;
use Byfareska\Crud\Driver\Doctrine\DoctrineSearch;
use Byfareska\Crud\Driver\Doctrine\DoctrineSearches;
use Byfareska\Crud\Driver\Doctrine\DoctrineSortHandler;
use Byfareska\Crud\Driver\Doctrine\FieldFilterHandler;
use Byfareska\Crud\Driver\Doctrine\FieldSortHandler;
use Byfareska\Crud\Driver\Doctrine\FlushingPersister;
use Byfareska\Crud\Driver\Doctrine\MappingFilterGuesser;
use Byfareska\Crud\Driver\Doctrine\RelationFilterField;
use Byfareska\Crud\Driver\Doctrine\WordsSearch;
use Byfareska\Crud\Driver\Dto\ConstructorDtoFactory;
use Byfareska\Crud\Driver\Dto\DtoFactory;
use Byfareska\Crud\Page\CrudPages;
use Byfareska\Crud\Page\DefaultCrudPages;
use Byfareska\Crud\Resource\AfterSave;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\CrudReturn;
use Byfareska\Crud\Resource\CrudSaveButton;
use Byfareska\Crud\Resource\DefaultCrudAccess;
use Byfareska\Crud\Resource\DefaultCrudRecords;
use Byfareska\Crud\Resource\DefaultCrudReturn;
use Byfareska\Crud\Resource\DefaultFailureMessage;
use Byfareska\Crud\Resource\DefaultIdMapper;
use Byfareska\Crud\Resource\DefaultPageSizes;
use Byfareska\Crud\Resource\DefaultRecordDeletion;
use Byfareska\Crud\Resource\FailureMessage;
use Byfareska\Crud\Resource\IdMapper;
use Byfareska\Crud\Resource\NoRecordTransaction;
use Byfareska\Crud\Resource\PageSizes;
use Byfareska\Crud\Resource\RecordDeletion;
use Byfareska\Crud\Resource\RecordGuard;
use Byfareska\Crud\Resource\RecordRow;
use Byfareska\Crud\Resource\RecordTransaction;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Security\DefaultUserIdMapper;
use Byfareska\Crud\Security\NoPanelAccount;
use Byfareska\Crud\Security\PanelAccount;
use Byfareska\Crud\Security\SecurityActor;
use Byfareska\Crud\Security\SecurityPanelAccount;
use Byfareska\Crud\Security\UserIdMapper;
use Byfareska\Crud\Theme\ScopeThemeSelector;
use Byfareska\Crud\Theme\ThemeSelector;
use Byfareska\Crud\Theme\Themes;
use Byfareska\Crud\UseCase\Command;
use Byfareska\Crud\UseCase\Query;
use Byfareska\Crud\UseCase\UseCaseExecutor;
use Byfareska\Crud\Value\ValueFormatter;
use Byfareska\Crud\Value\ValueNormalizer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\UX\Autocomplete\OptionsAwareAutocompleterInterface;
use Byfareska\Crud\Routing\PanelLiveUrls;
use Symfony\Component\DependencyInjection\Reference;
use Byfareska\Crud\Chart\ChartTimezone;
use Byfareska\Crud\Chart\TwigChartTimezone;

/**
 * Panels of CRUD pages, dashboards and a command palette, declared with #[AsCrud] and drawn with Twig and live
 * components. See README.md.
 */
final class CrudBundle extends AbstractBundle
{
    protected string $extensionAlias = 'byfareska_crud';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('name')
                    ->info('The name of the panel: the brand of the menu, the title of the pages.')
                    ->defaultValue('Panel')
                ->end()
                ->scalarNode('theme')
                    ->info(
                        'The theme of the panels that name none: "daisyui", "bootstrap5", "tailwind" or one of '
                        . '"themes". An env var will do: it is read when a page is drawn.',
                    )
                    ->defaultValue(Themes::DAISYUI)
                ->end()
                ->arrayNode('themes')
                    ->info(
                        'The application\'s own themes, by name: a directory of templates with only what differs from '
                        . 'its parent.',
                    )
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('parent')
                                ->info('The theme it falls back to.')
                                ->defaultValue(Themes::BOOTSTRAP5)
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('color_themes')
                    ->info(
                        'The colour themes the picker of the "daisyui" theme offers (data-theme on <html>), in order; '
                        . 'the stylesheet of the application builds them (@plugin "daisyui" { themes: ... }).',
                    )
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(Themes::DAISYUI_COLOR_THEMES)
                ->end()
                ->arrayNode('dark_color_themes')
                    ->info('Which of "color_themes" are dark: the charts and the native controls follow them.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(Themes::DAISYUI_DARK_COLOR_THEMES)
                ->end()
                ->enumNode('assets')
                    ->info(
                        'What builds the assets the panel loads (@CrudTheme/panel/_assets.html.twig): "reprise" (Vite '
                        . 'through Symfony Reprise, recommended), "encore" (Webpack Encore), "importmap" (AssetMapper) '
                        . 'or "none" (the layout loads them itself).',
                    )
                    ->values(['reprise', 'encore', 'importmap', 'none'])
                    ->defaultValue('reprise')
                ->end()
                ->arrayNode('entries')
                    ->info(
                        'The entries every page of the panels loads, in order '
                        . '(@CrudTheme/panel/_assets.html.twig). "{theme}" stands for the theme the page is drawn '
                        . 'with: "panel_{theme}" is the entry built for each theme.',
                    )
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(['app'])
                ->end()
                ->scalarNode('layout')
                    ->info(
                        'The template the pages extend (blocks "page_title" and "content"), '
                        . 'for a scope that names none.',
                    )
                    ->defaultValue('@CrudTheme/panel/layout.html.twig')
                ->end()
                ->arrayNode('paths')
                    ->info(
                        'The words of the addresses of the pages of a resource, after its path: "new" (/products/new), '
                        . '"edit" (/products/7/edit), "page" (/products/page/3) - in the language of the panel.',
                    )
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('new')->defaultValue('new')->cannotBeEmpty()->end()
                        ->scalarNode('edit')->defaultValue('edit')->cannotBeEmpty()->end()
                        ->scalarNode('page')->defaultValue('page')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->booleanNode('preview')
                    ->info(
                        'Whether a click on a row of a list opens the record in the preview drawer, for a resource '
                        . 'that does not say (AsCrud::$preview).',
                    )
                    ->defaultTrue()
                ->end()
                ->arrayNode('per_page')
                    ->info(
                        'How many records a page of a list can have, to choose from below it, for a resource that '
                        . 'does not say (AsCrud::$perPage); one alone = no choice.',
                    )
                    ->integerPrototype()->min(1)->end()
                    ->requiresAtLeastOneElement()
                    ->defaultValue([15, 30, 50, 100])
                ->end()
                ->integerNode('default_per_page')
                    ->info(
                        'Which of them a list shows at first, for a resource that does not say '
                        . '(AsCrud::$defaultPerPage); null or one a resource does not offer = the first.',
                    )
                    ->min(1)
                    ->defaultNull()
                ->end()
                ->arrayNode('form')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('save')
                            ->info(
                                'Where "save" of the add and edit forms leads, for a resource that does not say '
                                . '(AsCrud::$save): edit (the edit form of the record, of a new one too), return '
                                . '(the list), show (its details), new (the add form again), or the name of a '
                                . 'CrudSaveButton.',
                            )
                            ->defaultValue(AfterSave::Edit->value)
                            ->cannotBeEmpty()
                            ->validate()
                                ->ifTrue(static fn (mixed $save): bool => !is_string($save)
                                    || preg_match(CrudSaveButton::NAME, $save) !== 1)
                                ->thenInvalid('%s is not a case of AfterSave nor the name of a CrudSaveButton.')
                            ->end()
                        ->end()
                        ->arrayNode('save_buttons')
                            ->info(
                                'The buttons of the forms besides "save", for a resource that does not say '
                                . '(AsCrud::$afterSave): return ("save and back to the list"), new ("save and add '
                                . 'another"), edit ("save and keep editing"), show ("save and view").',
                            )
                            ->enumPrototype()
                                ->values(array_column(AfterSave::cases(), 'value'))
                            ->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('scopes')
                    ->info('The panels (route scopes), each imported with `type: byfareska_crud, resource: <name>`.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')
                                ->info('Translation key of its name; default panel.scope.<name>.')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('icon')
                                ->info('Bootstrap Icons name.')
                                ->defaultValue('window')
                            ->end()
                            ->scalarNode('layout')
                                ->info('The template of its pages; default "layout".')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('paths')
                                ->info(
                                    'The words of its addresses in place of "paths" - a panel in another language: '
                                    . '{new: nowy, edit: edycja, page: strona}.',
                                )
                                ->children()
                                    ->scalarNode('new')->cannotBeEmpty()->end()
                                    ->scalarNode('edit')->cannotBeEmpty()->end()
                                    ->scalarNode('page')->cannotBeEmpty()->end()
                                ->end()
                            ->end()
                            ->scalarNode('theme')
                                ->info(
                                    'The theme of its pages and of its live components; default "theme". '
                                    . 'An env var will do.',
                                )
                                ->defaultNull()
                            ->end()
                            ->arrayNode('entries')
                                ->info('More Vite entries its pages load, after "entries"; "{theme}" as there.')
                                ->scalarPrototype()->cannotBeEmpty()->end()
                            ->end()
                            ->scalarNode('role')
                                ->info(
                                    'Who may go into it: its menu, palette and dashboard, and its resources (their '
                                    . 'pages, lists, forms, palette records) - checked by the bundle on every way to '
                                    . 'them; access_control may guard its addresses as well.',
                                )
                                ->defaultNull()
                            ->end()
                            ->booleanNode('dashboard')
                                ->info('Whether "/" of the scope is a dashboard of widgets.')
                                ->defaultTrue()
                            ->end()
                            ->arrayNode('boards')
                                ->info(
                                    'More dashboards of the panel, by name ("sales", "warehouse"): each with its own '
                                    . 'path, layouts and widgets (AsDashboardWidget::$boards), in its menu. A name '
                                    . 'is that of no panel nor of another board.',
                                )
                                ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('label')
                                            ->info(
                                                'Translation key of its name in the menu; default panel.board.<name>.',
                                            )
                                            ->defaultNull()
                                        ->end()
                                        ->scalarNode('icon')->defaultValue('grid-1x2')->end()
                                        ->scalarNode('path')
                                            ->info('Where it is, under the prefix of the panel; default "/<name>".')
                                            ->defaultNull()
                                        ->end()
                                        ->scalarNode('role')
                                            ->info('Who may have it, besides the role of the panel.')
                                            ->defaultNull()
                                        ->end()
                                        ->scalarNode('template')
                                            ->info(
                                                'The template of its page in place of the theme\'s; it may extend '
                                                . '"@CrudTheme/panel/dashboard.html.twig" and change a block of it.',
                                            )
                                            ->defaultNull()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('home')
                                ->info('The route the way into it leads to; default its dashboard.')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('brand')
                                ->info(
                                    'The name its pages go by (the menu, the title of the window); default "name". '
                                    . 'An env var will do.',
                                )
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('value_themes')
                    ->info(
                        'Templates of blocks the values and the layouts of every resource are printed with, looked '
                        . 'into after its own "theme" and before the bundle\'s: a type_money for every resource.',
                    )
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('form_themes')
                    ->info(
                        'Form themes of the application the forms and the filters of the panel are drawn with, after '
                        . 'the theme\'s own (@CrudTheme/crud/form_layout.html.twig), so their blocks win: the '
                        . 'choice_widget of a tom-select, a money_widget of your own. The global twig.form_themes come '
                        . 'only after the theme\'s; name them here to have them in the panel.',
                    )
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('command_palette')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('search')
                            ->info('The service that finds the entries for what was typed (a PaletteSearch).')
                            ->defaultValue(TextPaletteSearch::class)
                        ->end()
                        ->arrayNode('suggested')
                            ->info(
                                'Keys of the entries offered before anything is typed; empty = the pages of the menu.',
                            )
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('locales')
                            ->info(
                                'The languages byfareska:crud:command-palette:index indexes; empty = '
                                . 'framework.enabled_locales.',
                            )
                            ->scalarPrototype()->end()
                        ->end()
                        ->integerNode('limit')
                            ->info('How many items the palette lists at most.')
                            ->min(1)
                            ->defaultValue(14)
                        ->end()
                        ->integerNode('records_per_source')
                            ->info('How many records a source of records gives at most for what was typed.')
                            ->min(0)
                            ->defaultValue(5)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('dashboard')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('storage')
                            ->info(
                                'Where the layouts are kept: "doctrine" (a table), "cache" (cache.app) or the id of a '
                                . 'DashboardLayouts service; default doctrine when there is Doctrine ORM.',
                            )
                            ->defaultNull()
                        ->end()
                        ->scalarNode('share_role')
                            ->info('Who may store a layout for everybody; null = nobody.')
                            ->defaultValue('ROLE_ADMIN')
                        ->end()
                        ->arrayNode('disabled_widgets')
                            ->info(
                                'Keys of the widgets left out, e.g. those of the bundle: "panel.clock", "panel.note", '
                                . '"panel.quick_actions".',
                            )
                            ->scalarPrototype()->end()
                        ->end()
                        ->integerNode('columns')
                            ->info(
                                'The columns of the grid of the dashboards; the sizes widgets declare are cells of '
                                . 'it, the presets (s, m, l, xl) are made as wide as on 12.',
                            )
                            ->min(1)->max(48)
                            ->defaultValue(12)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('chart')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('stored_timezone')
                            ->info(
                                'The zone the date columns of the charts hold their times in (TimeSeries): Doctrine '
                                . 'writes a datetime in the zone of PHP. Null for columns with a zone of their own '
                                . '(PostgreSQL timestamptz).',
                            )
                            ->defaultValue('UTC')
                        ->end()
                        ->scalarNode('day_bucket')
                            ->info(
                                'The SQL of counts by day (DailySeries::bucket()): the index of a DayBucket service '
                                . '("postgresql", "mysql", "sqlite") or its id; default the PlatformDayBucket of the '
                                . 'platform of Doctrine\'s connection when there is Doctrine.',
                            )
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // The classes of #[AsCrud] & co., including entities in directories excluded from the services.
        $container->addCompilerPass(new CollectCrudDeclarationsPass());
        // The components of #[AsDashboardWidget] and #[AsTopbarWidget], by the names of their #[AsTwigComponent].
        $container->addCompilerPass(new CollectWidgetsPass());
        // A component of the application named as one of the bundle's would never be drawn: said at once.
        $container->addCompilerPass(new ComponentCollisionPass());
        // The live requests of a panel under its prefix, in its firewall.
        $container->addCompilerPass(new PanelLiveUrlsPass());

        $tags = [
            AsCrud::class => AsCrud::TAG,
            AsCrudFormSection::class => AsCrudFormSection::TAG,
            AsCrudDetailsSection::class => AsCrudDetailsSection::TAG,
            AsCrudPage::class => AsCrudPage::TAG,
            AsDashboardWidget::class => AsDashboardWidget::TAG,
            AsTopbarWidget::class => AsTopbarWidget::TAG,
        ];
        foreach ($tags as $attribute => $tag) {
            $container->registerAttributeForAutoconfiguration(
                $attribute,
                static function (ChildDefinition $definition) use ($tag): void {
                    $definition->addTag($tag);
                },
            );
        }
        $container->registerForAutoconfiguration(Query::class)->addTag(UseCaseExecutor::TAG);
        $container->registerForAutoconfiguration(Command::class)->addTag(UseCaseExecutor::TAG);
        $container->registerForAutoconfiguration(DayBucket::class)->addTag(DayBucket::TAG);
        $container->registerForAutoconfiguration(PlatformDayBucket::class)->addTag(PlatformDayBucket::TAG);
        $container->registerForAutoconfiguration(RecordActionHandler::class)->addTag(RecordActionHandler::TAG);
        $container->registerForAutoconfiguration(BulkActionHandler::class)->addTag(BulkActionHandler::TAG);
        $container->registerForAutoconfiguration(ValueFormatter::class)->addTag(ValueFormatter::TAG);
        $container->registerForAutoconfiguration(RecordGuard::class)->addTag(RecordGuard::TAG);
        $container->registerForAutoconfiguration(RecordRow::class)->addTag(RecordRow::TAG);
        $container->registerForAutoconfiguration(ValueNormalizer::class)->addTag(ValueNormalizer::TAG);
        $container->registerForAutoconfiguration(DoctrineFilterHandler::class)->addTag(DoctrineFilterHandler::TAG);
        $container->registerForAutoconfiguration(DoctrineListExtension::class)->addTag(DoctrineListExtension::TAG);
        $container->registerForAutoconfiguration(DoctrineScope::class)->addTag(DoctrineScope::TAG);
        $container->registerForAutoconfiguration(DoctrineSortHandler::class)->addTag(DoctrineSortHandler::TAG);
    }

    /**
     * @param array{
     *     name: string,
     *     layout: string,
     *     preview: bool,
     *     paths: array{new: string, edit: string, page: string},
     *     per_page: list<int>,
     *     default_per_page: int|null,
     *     form: array{save: string, save_buttons: list<string>},
     *     entries: list<string>,
     *     assets: string,
     *     theme: string,
     *     themes: array<string, array{path: string, parent: string}>,
     *     color_themes: list<string>,
     *     dark_color_themes: list<string>,
     *     scopes: array<string, array<string, mixed>>,
     *     value_themes: list<string>,
     *     form_themes: list<string>,
     *     command_palette: array{
     *         search: string,
     *         suggested: list<string>,
     *         locales: list<string>,
     *         limit: int,
     *         records_per_source: int,
     *     },
     *     dashboard: array{
     *         storage: string|null,
     *         share_role: string|null,
     *         disabled_widgets: list<string>,
     *         columns: int,
     *     },
     *     chart: array{day_bucket: string|null, stored_timezone: string|null},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $parameters = $container->parameters();
        $parameters->set('byfareska_crud.name', $config['name']);
        $parameters->set('byfareska_crud.layout', $config['layout']);
        $parameters->set('byfareska_crud.preview', $config['preview']);
        $parameters->set('byfareska_crud.per_page', array_values($config['per_page']));
        $parameters->set('byfareska_crud.default_per_page', $config['default_per_page']);
        $parameters->set('byfareska_crud.form.save', $config['form']['save']);
        $parameters->set(
            'byfareska_crud.form.save_buttons',
            array_values(array_unique($config['form']['save_buttons'])),
        );
        $parameters->set('byfareska_crud.entries', $config['entries']);
        $parameters->set('byfareska_crud.assets', $config['assets']);
        $parameters->set('byfareska_crud.paths', $config['paths']);
        $parameters->set('byfareska_crud.theme', $config['theme']);
        $themes = [
            Themes::BOOTSTRAP5 => ['path' => $this->getPath() . '/templates/bootstrap5', 'parent' => null],
            Themes::TAILWIND => ['path' => $this->getPath() . '/templates/tailwind', 'parent' => null],
            Themes::DAISYUI => ['path' => $this->getPath() . '/templates/daisyui', 'parent' => null],
        ];
        foreach ($config['themes'] as $name => $theme) {
            $themes[$name] = ['path' => rtrim($theme['path'], '/'), 'parent' => $theme['parent']];
        }
        $parameters->set('byfareska_crud.themes', $themes);
        $parameters->set('byfareska_crud.color_themes', array_values($config['color_themes']));
        $parameters->set('byfareska_crud.dark_color_themes', array_values($config['dark_color_themes']));
        $parameters->set('byfareska_crud.scopes', $config['scopes']);
        $parameters->set('byfareska_crud.command_palette.suggested', $config['command_palette']['suggested']);
        $parameters->set('byfareska_crud.command_palette.locales', $config['command_palette']['locales']);
        $parameters->set('byfareska_crud.command_palette.limit', $config['command_palette']['limit']);
        $parameters->set(
            'byfareska_crud.command_palette.records_per_source',
            $config['command_palette']['records_per_source'],
        );
        $parameters->set('byfareska_crud.dashboard.share_role', $config['dashboard']['share_role']);
        $parameters->set('byfareska_crud.dashboard.columns', $config['dashboard']['columns']);
        $parameters->set('byfareska_crud.value_themes', array_values($config['value_themes']));
        $parameters->set('byfareska_crud.form_themes', array_values($config['form_themes']));
        $parameters->set('byfareska_crud.chart.stored_timezone', $config['chart']['stored_timezone']);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $src = $this->getPath() . '/src';
        $services->load('Byfareska\\Crud\\', $src . '/')
            ->exclude([
                $src . '/CrudBundle.php',
                $src . '/DependencyInjection/',
                // Registered below when symfony/ux-autocomplete is there.
                $src . '/Autocomplete/',
                $src . '/Filter/{Comparison,Compared}.php',
                $src . '/{Attribute,Event,Layout,Navigation/NavigationItem.php,Navigation/TopbarWidget.php}',
                $src . '/CommandPalette/{PaletteEntry,PaletteRecord,PaletteItem}.php',
                $src . '/Dashboard/{DashboardWidget,WidgetSetting,WidgetSize,WidgetPlacement}.php',
                $src . '/Dashboard/{DashboardView,PlacementView,WidgetView}.php',
                $src . '/Dashboard/Storage/DashboardLayoutRecord.php',
                $src . '/Driver/*/*Source.php',
                $src . '/Driver/Doctrine/{DoctrineForm,DoctrineQuery,DoctrineListQuery,FieldSort,LikePattern}.php',
                $src . '/Panel/Scope.php',
                $src . '/Page/CrudContext.php',
                $src . '/Action/{ActionButton,ComponentAction,DeleteAction,DetailsAction,EditAction}.php',
                $src . '/Action/{LiveListAction,NoAction,RouteAction,DuplicateAction,ConfirmInput}.php',
                $src . '/Action/{BulkDeleteAction,BulkResult,BulkSelection,RunAction}.php',
                $src . '/Resource/{AfterSave,Column,CrudCell,CrudFailure,CrudFill,CrudListedIn,RecordView}.php',
                $src . '/Resource/CrudToast.php',
                $src . '/Value/{ComponentFormat,TypedValue,ValueView}.php',
                $src . '/UseCase/{AccessDeniedException,InvalidIdException,ListPage,Sort,UseCaseFailed,Failure}.php',
                $src . '/Component/Widget/CountryFlag.php',
                $src . '/Chart/Sql/ZoneOffsets.php',
            ]);

        $services->alias(Actor::class, SecurityActor::class);
        $services->alias(UserIdMapper::class, DefaultUserIdMapper::class);
        $services->alias(IdMapper::class, DefaultIdMapper::class);
        $services->alias(DoctrineIdCodec::class, DefaultDoctrineIdCodec::class);
        $services->alias(UseCaseResolver::class, AttributeUseCaseResolver::class);
        $services->alias(DoctrineTextMatch::class, LowerTextMatch::class);
        // What an application may replace or decorate (#[AsDecorator]).
        $services->alias(CrudAccess::class, DefaultCrudAccess::class);
        $services->alias(CrudReturn::class, DefaultCrudReturn::class);
        $services->alias(FailureMessage::class, DefaultFailureMessage::class);
        $services->alias(PageSizes::class, DefaultPageSizes::class);
        $services->alias(RecordDeletion::class, DefaultRecordDeletion::class);
        $services->alias(DashboardPolicy::class, RoleDashboardPolicy::class);
        $services->alias(CrudRecords::class, DefaultCrudRecords::class);
        $services->alias(CrudPages::class, DefaultCrudPages::class);
        $services->alias(ThemeSelector::class, ScopeThemeSelector::class);
        $services->alias(PaletteSearch::class, $config['command_palette']['search']);
        $services->alias(PaletteRanker::class, BalancedPaletteRanker::class);
        $services->alias(DtoFactory::class, ConstructorDtoFactory::class);
        $services->alias(DashboardKey::class, ParametersDashboardKey::class);
        $services->alias(ChartTimezone::class, TwigChartTimezone::class);
        $services->get(DashboardWidgets::class)->arg('$disabled', $config['dashboard']['disabled_widgets']);
        $services->get(PanelLiveUrls::class)->arg('$router', new Reference('router'));

        // Only this bundle's extension is known here: whether DoctrineBundle is there is told by the kernel.
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
        $hasDoctrineBundle = is_array($bundles) && isset($bundles['DoctrineBundle']);
        // The one signed in, in the menu: Symfony Security's user, or nobody without it.
        if (is_array($bundles) && isset($bundles['SecurityBundle'])) {
            $services->alias(PanelAccount::class, SecurityPanelAccount::class);
        } else {
            $services->remove(SecurityPanelAccount::class);
            $services->alias(PanelAccount::class, NoPanelAccount::class);
        }
        $hasOrm = $hasDoctrineBundle && interface_exists(EntityManagerInterface::class);
        $storage = $config['dashboard']['storage'] ?? ($hasOrm ? 'doctrine' : 'cache');
        $services->alias(DashboardLayouts::class, match ($storage) {
            'doctrine' => DoctrineDashboardLayouts::class,
            'cache' => CacheDashboardLayouts::class,
            default => $storage,
        });
        $parameters->set('byfareska_crud.dashboard.storage', $storage);

        // A save and a delete all or nothing: in a transaction of the connection, when Doctrine ORM is there.
        $transaction = $hasOrm ? DoctrineRecordTransaction::class : NoRecordTransaction::class;
        $services->alias(RecordTransaction::class, $transaction);
        if (!$hasOrm) {
            $services->remove(DoctrineRecordTransaction::class);
            $services->remove(DoctrineDashboardLayouts::class);
            $services->remove(DoctrineDriver::class);
            $services->remove(DoctrineEntityNormalizer::class);
            $services->remove(FieldFilterHandler::class);
            $services->remove(FieldSortHandler::class);
            $services->remove(DoctrineScopes::class);
            $services->remove(DoctrineRelations::class);
            $services->remove(ConstructorEntityFactory::class);
            $services->remove(FlushingPersister::class);
            $services->remove(DefaultDoctrineExceptionMapper::class);
            $services->remove(DoctrineSearches::class);
            $services->remove(WordsSearch::class);
            $services->remove(MappingFilterGuesser::class);
        }

        // How a typed text finds entities: the application aliases DoctrineSearch to change it for every resource.
        $services->alias(DoctrineSearch::class, WordsSearch::class);
        // How the doctrine driver makes and writes entities, and what its errors mean: the application's to change.
        if ($hasOrm) {
            $services->alias(DoctrineEntityFactory::class, ConstructorEntityFactory::class);
            $services->alias(DoctrinePersister::class, FlushingPersister::class);
            $services->alias(DoctrineExceptionMapper::class, DefaultDoctrineExceptionMapper::class);
        }

        // Relation filters to search in, when symfony/ux-autocomplete is there; else a choice of every entity.
        $services->alias(DoctrineFilterGuesser::class, MappingFilterGuesser::class);
        $hasAutocomplete = $hasOrm
            && is_array($bundles)
            && isset($bundles['AutocompleteBundle'])
            && interface_exists(OptionsAwareAutocompleterInterface::class);
        if ($hasAutocomplete) {
            $services->set(RelationAutocompleter::class)
                ->tag('ux.autocompleter', ['alias' => RelationAutocompleter::ALIAS]);
            $services->set(RelationChoiceLabels::class);
            $services->set(AutocompleteRelationFilterField::class);
            $services->alias(RelationFilterField::class, AutocompleteRelationFilterField::class);
        } else {
            $services->alias(RelationFilterField::class, ChoiceRelationFilterField::class);
        }

        $hasDbal = $hasDoctrineBundle && class_exists(Connection::class);
        // The two that choose one of the tagged buckets are no bucket to choose themselves.
        $services->get(DoctrineDayBucket::class)->autoconfigure(false);
        $services->get(NamedDayBucket::class)->autoconfigure(false);
        $dayBucket = $config['chart']['day_bucket'];
        if ($dayBucket !== null) {
            $services->get(NamedDayBucket::class)->arg('$name', $dayBucket);
            $services->alias(DayBucket::class, NamedDayBucket::class);
        } else {
            $services->remove(NamedDayBucket::class);
            if ($hasDbal) {
                $services->alias(DayBucket::class, DoctrineDayBucket::class);
            }
        }
        if (!$hasDbal) {
            $services->remove(DoctrineDayBucket::class);
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The table of the dashboard layouts, when they are kept by Doctrine.
        $storage = null;
        foreach ($builder->getExtensionConfig($this->extensionAlias) as $config) {
            $dashboard = $config['dashboard'] ?? null;
            if (is_array($dashboard)) {
                $storage = $dashboard['storage'] ?? $storage;
            }
        }
        if ($builder->hasExtension('doctrine') && in_array($storage, [null, 'doctrine'], true)) {
            $mappings = ['mappings' => ['ByfareskaCrud' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => $this->getPath() . '/src/Dashboard/Storage',
                'prefix' => 'Byfareska\\Crud\\Dashboard\\Storage',
                'alias' => 'ByfareskaCrud',
            ]]];
            // Into the default entity manager of the application: a configuration of named managers would otherwise
            // get one more, named "default", for the table alone.
            $manager = $this->defaultEntityManager($builder->getExtensionConfig('doctrine'));
            $builder->prependExtensionConfig('doctrine', ['orm' => $manager === null
                ? $mappings
                : ['entity_managers' => [$manager => $mappings]]]);
        }

        // The controllers and styles of the panel for AssetMapper (byfareska_crud.assets: importmap), under the name
        // npm gives them for Vite and Encore: @byfareska/crud.
        if ($this->isAssetMapperAvailable($builder)) {
            $builder->prependExtensionConfig('framework', ['asset_mapper' => ['paths' => [
                $this->getPath() . '/assets' => '@byfareska/crud',
            ]]]);
        }
    }

    /**
     * Whether the application has AssetMapper: its own bundle since Symfony 8.2, a part of FrameworkBundle before. As
     * Symfony UX decides it; the configuration stays under "framework" either way.
     */
    private function isAssetMapperAvailable(ContainerBuilder $builder): bool
    {
        if (!interface_exists('Symfony\Component\AssetMapper\AssetMapperInterface')) {
            return false;
        }
        $bundles = $builder->hasParameter('kernel.bundles_metadata')
            ? $builder->getParameter('kernel.bundles_metadata')
            : [];
        $framework = is_array($bundles) ? ($bundles['FrameworkBundle'] ?? null) : null;
        if (!is_array($framework) || !is_string($framework['path'] ?? null)) {
            return false;
        }

        return isset($bundles['AssetMapperBundle'])
            || is_file($framework['path'] . '/Resources/config/asset_mapper.php');
    }

    /**
     * The name of the default entity manager of a configuration of named ones ("entity_managers"); null for the
     * configuration of one manager.
     *
     * @param array<array<array-key, mixed>> $configs
     */
    private function defaultEntityManager(array $configs): ?string
    {
        $default = null;
        $first = null;
        foreach ($configs as $config) {
            $orm = $config['orm'] ?? null;
            if (!is_array($orm)) {
                continue;
            }
            if (isset($orm['default_entity_manager']) && is_string($orm['default_entity_manager'])) {
                $default = $orm['default_entity_manager'];
            }
            if (isset($orm['entity_managers']) && is_array($orm['entity_managers']) && $orm['entity_managers'] !== []) {
                $first ??= (string) array_key_first($orm['entity_managers']);
            }
        }

        return $first === null ? null : $default ?? $first;
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
