<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Byfareska\Crud\Event\DashboardLayoutSaved;
use Byfareska\Crud\Event\DashboardLayoutShared;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\Actor;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The dashboard of a panel (its "board": the name of the scope) as the one acting has it: their own layout, else those
 * of the DashboardPolicy's fallback owners (the one stored for everybody), else the one made of the widgets marked
 * "default" - always normalized against what they may have now. And what can be done to it: move, add, remove, set
 * up, reset, share - as the policy allows; DashboardLayoutSaved and DashboardLayoutShared tell the rest of the
 * application.
 */
final readonly class Dashboards
{
    public function __construct(
        private DashboardLayouts $layouts,
        private DashboardWidgets $widgets,
        private LayoutNormalizer $normalizer,
        private LayoutPacker $packer,
        private Actor $actor,
        private Scopes $scopes,
        private DashboardPolicy $policy,
        #[AutowireLocator(WidgetSettingValidator::TAG)]
        private ContainerInterface $validators,
        private ?EventDispatcherInterface $events = null,
        private ?DashboardKey $keys = null,
    ) {
    }

    /**
     * What the layouts of the dashboard are kept under, in this request (DashboardKey).
     */
    private function key(string $board): string
    {
        return $this->keys?->of($board) ?? $board;
    }

    /**
     * Refuses a dashboard there is not, or one the one acting may not have.
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     */
    public function assertBoard(string $board): void
    {
        // The dashboard of a panel, or one of its boards (byfareska_crud.scopes.<name>.boards).
        $scope = $this->scopes->ofBoard($board)
            ?? throw new NotFoundHttpException(sprintf('There is no dashboard "%s".', $board));
        $role = $scope->boards[$board]['role'] ?? null;
        if (!$this->scopes->isGrantedIn($scope->name, $role, $board)) {
            throw new AccessDeniedHttpException(sprintf(
                'The dashboard "%s" needs the role "%s".',
                $board,
                (string) ($role ?? $scope->role),
            ));
        }
    }

    /**
     * @return array{list<WidgetPlacement>, LayoutSource} the placements and where they come from
     */
    public function current(string $board): array
    {
        $this->assertBoard($board);
        $widgets = $this->widgets->available($board);
        [$stored, $source] = $this->stored($board);

        return [$this->normalizer->normalize($stored ?? $this->packer->pack($widgets), $widgets), $source];
    }

    /**
     * The layout kept for the one acting, as it was stored - with the widgets they may not have now - and where it
     * comes from; null when there is none (the layout of the code).
     *
     * @return array{list<WidgetPlacement>|null, LayoutSource}
     */
    private function stored(string $board): array
    {
        $owner = $this->actor->id();
        $own = $owner === null ? null : $this->layouts->find($owner, $this->key($board));
        if ($own !== null) {
            return [$own, LayoutSource::Own];
        }

        foreach ($this->policy->fallbackOwners($board) as $fallback) {
            $shared = $this->layouts->find($fallback, $this->key($board));
            if ($shared !== null) {
                return [$shared, LayoutSource::Shared];
            }
        }

        return [null, LayoutSource::Code];
    }

    public function view(string $board): DashboardView
    {
        [$placements, $source] = $this->current($board);
        $widgets = $this->widgets->available($board);

        $placed = [];
        $views = [];
        foreach ($placements as $placement) {
            $placed[$placement->widget] = ($placed[$placement->widget] ?? 0) + 1;
            $views[] = new PlacementView(
                $placement->id,
                $widgets[$placement->widget],
                $placement->x,
                $placement->y,
                $placement->w,
                $placement->h,
                $placement->settings,
            );
        }

        $gallery = [];
        foreach ($widgets as $widget) {
            $sizes = [];
            $default = null;
            foreach ($widget->presets($this->packer->columns) as $name => $size) {
                $sizes[$name] = ['w' => $size->w, 'h' => $size->h];
                if ($size->equals($widget->size)) {
                    $default = $name;
                }
            }
            if ($default === null) {
                $sizes = ['default' => ['w' => $widget->size->w, 'h' => $widget->size->h]] + $sizes;
                $default = 'default';
            }
            $gallery[] = new WidgetView($widget, $sizes, $default, $placed[$widget->key] ?? 0);
        }

        return new DashboardView(
            $board,
            $views,
            $gallery,
            $source,
            $this->policy->mayShare($board),
            $this->policy->mayEdit($board),
            $this->packer->columns,
        );
    }

    /**
     * The placement of a widget on the dashboard of the one acting; null = there is none (removed in another tab).
     */
    public function placement(string $board, string $id): ?PlacementView
    {
        return array_find(
            $this->view($board)->placements,
            static fn (PlacementView $placement): bool => $placement->id === $id,
        );
    }

    /**
     * Moves and resizes what the grid says; a placement it does not name stays where it is.
     *
     * @param array<string, array{x: int, y: int, w: int, h: int}> $positions by the id of the placement
     */
    public function move(string $board, array $positions): void
    {
        $this->assertEditable($board);
        [$placements] = $this->current($board);
        $moved = [];
        foreach ($placements as $placement) {
            $position = $positions[$placement->id] ?? null;
            $moved[] = $position === null
                ? $placement
                : $placement->movedTo($position['x'], $position['y'], $position['w'], $position['h']);
        }
        $this->store($board, $moved);
    }

    /**
     * Puts a widget onto the first free place, in the size asked for (kept between its smallest and largest).
     *
     * @throws NotFoundHttpException a widget the one acting may not have
     */
    public function add(string $board, string $widgetKey, ?int $w = null, ?int $h = null): void
    {
        $this->assertEditable($board);
        $widget = $this->widgets->find($widgetKey, $board)
            ?? throw new NotFoundHttpException(sprintf('There is no widget "%s".', $widgetKey));
        $size = $widget->size;
        if ($w !== null && $h !== null) {
            $size = new WidgetSize(max(1, min($this->packer->columns, $w)), max(1, $h));
            $size = $size->clamp($widget->minSize, $widget->maxSize);
        }

        [$placements] = $this->current($board);
        $placements[] = $this->packer->place($placements, $widget->key, $size, $widget->settingsOf([]));
        $this->store($board, $placements);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function remove(string $board, string $id): void
    {
        $this->assertEditable($board);
        [$placements] = $this->current($board);
        $kept = array_values(array_filter(
            $placements,
            static fn (WidgetPlacement $placement): bool => $placement->id !== $id,
        ));
        if (count($kept) === count($placements)) {
            throw new NotFoundHttpException(sprintf('There is no widget "%s" on the dashboard.', $id));
        }
        $this->store($board, $kept);
    }

    /**
     * Changes the settings of a placed widget; what the widget does not have is left out when stored.
     *
     * @param array<array-key, mixed> $settings
     *
     * @throws NotFoundHttpException
     * @throws WidgetSettingRefused a value a validator of the widget refuses: nothing is saved
     */
    public function configure(string $board, string $id, array $settings): void
    {
        $this->assertEditable($board);
        [$placements] = $this->current($board);
        $widgets = $this->widgets->available($board);
        $found = false;
        foreach ($placements as $index => $placement) {
            if ($placement->id === $id) {
                $values = $placement->settings;
                foreach ($settings as $name => $value) {
                    if (is_string($value)) {
                        $values[(string) $name] = $value;
                    } elseif ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                        // A box unticked (null, false), a number as the browser sends it: the setting makes it text.
                        $values[(string) $name] = is_bool($value) ? ($value ? '1' : '') : (string) $value;
                    } elseif (is_array($value)) {
                        $values[(string) $name] = array_values(array_filter($value, is_string(...)));
                    }
                }
                $widget = $widgets[$placement->widget] ?? null;
                if ($widget instanceof DashboardWidget) {
                    $values = $this->validated($widget, $values, $settings, $board);
                }
                $placements[$index] = $placement->withSettings($values);
                $found = true;
            }
        }
        if (!$found) {
            throw new NotFoundHttpException(sprintf('There is no widget "%s" on the dashboard.', $id));
        }
        $this->store($board, $placements);
    }

    /**
     * Forgets the layout of the one acting: they get the shared one, or the default, again.
     */
    public function reset(string $board): void
    {
        $this->assertEditable($board);
        $this->layouts->delete($this->owner(), $this->key($board));
    }

    /**
     * Stores the layout the one acting sees as that of others (DashboardPolicy::shareOwner(): everybody who has none of
     * their own, by default) - without what is personal in it (WidgetSetting::isPersonal(): the text of a note), which
     * the others get as its default.
     *
     * @throws AccessDeniedHttpException
     */
    public function share(string $board): void
    {
        $this->assertBoard($board);
        if (!$this->policy->mayShare($board)) {
            throw new AccessDeniedHttpException(sprintf('The one acting may not share the dashboard "%s".', $board));
        }

        [$placements] = $this->current($board);
        $widgets = $this->widgets->available($board);
        $shared = [];
        foreach ($placements as $placement) {
            $widget = $widgets[$placement->widget] ?? null;
            $shared[] = $widget instanceof DashboardWidget
                ? $placement->withSettings($this->withoutPersonal($widget, $placement->settings))
                : $placement;
        }
        $owner = $this->policy->shareOwner($board);
        $this->layouts->save($owner, $this->key($board), $shared);
        $this->events?->dispatch(new DashboardLayoutShared($board, $owner, $this->owner(), $shared));
    }

    public function mayShare(string $board): bool
    {
        return $this->policy->mayShare($board);
    }

    /**
     * @throws AccessDeniedHttpException the policy keeps the one acting from changing it
     */
    private function assertEditable(string $board): void
    {
        $this->assertBoard($board);
        if (!$this->policy->mayEdit($board)) {
            throw new AccessDeniedHttpException(sprintf('The dashboard "%s" is not to be changed.', $board));
        }
    }

    /**
     * The settings with what is personal set to its default.
     *
     * @param array<string, string|list<string>> $settings
     *
     * @return array<string, string|list<string>>
     */
    private function withoutPersonal(DashboardWidget $widget, array $settings): array
    {
        foreach ($widget->settings as $setting) {
            if ($setting->isPersonal() && array_key_exists($setting->name, $settings)) {
                $settings[$setting->name] = $setting->default;
            }
        }

        return $settings;
    }

    /**
     * The values with those just given checked by the validators their settings name.
     *
     * @param array<string, string|list<string>> $values
     * @param array<array-key, mixed> $given
     *
     * @return array<string, string|list<string>>
     *
     * @throws LogicException a validator a setting names is no service
     * @throws WidgetSettingRefused
     */
    private function validated(DashboardWidget $widget, array $values, array $given, string $board): array
    {
        foreach ($widget->settings as $setting) {
            if ($setting->validator === null || !array_key_exists($setting->name, $given)) {
                continue;
            }
            if (!$this->validators->has($setting->validator)) {
                throw new LogicException(sprintf(
                    'The setting "%s" of the widget "%s" is checked by %s, which is no service tagged "%s".',
                    $setting->name,
                    $widget->key,
                    $setting->validator,
                    WidgetSettingValidator::TAG,
                ));
            }
            $validator = $this->validators->get($setting->validator);
            assert($validator instanceof WidgetSettingValidator);
            try {
                $values[$setting->name] = $validator->validate(
                    $setting,
                    $setting->accept($values[$setting->name] ?? null),
                    $board,
                );
            } catch (WidgetSettingRefused $refused) {
                // Told which setting it is about, for the dialog.
                $refused->setting ??= $setting->name;

                throw $refused;
            }
        }

        return $values;
    }

    /**
     * Keeps the placements as the layout of the one acting (normalized first) - and, as they were, those of the
     * layout it was made of whose widgets they may not have now (a filter, a feature flag, a role taken for a while):
     * the layout gets them back, settings and all, once they are there again. Only the view leaves them out.
     *
     * @param list<WidgetPlacement> $placements
     */
    private function store(string $board, array $placements): void
    {
        $owner = $this->owner();
        $widgets = $this->widgets->available($board);
        $placements = $this->normalizer->normalize($placements, $widgets);
        [$stored] = $this->stored($board);
        foreach ($stored ?? [] as $placement) {
            if (!isset($widgets[$placement->widget])) {
                $placements[] = $placement;
            }
        }
        $this->layouts->save($owner, $this->key($board), $placements);
        $this->events?->dispatch(new DashboardLayoutSaved($board, $owner, $placements));
    }

    /**
     * @throws AccessDeniedHttpException nobody is signed in: there is nobody to keep a layout for
     */
    private function owner(): string
    {
        return $this->actor->id() ?? throw new AccessDeniedHttpException(
            'Only somebody signed in has a dashboard of their own.',
        );
    }
}
