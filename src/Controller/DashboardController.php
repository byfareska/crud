<?php

declare(strict_types=1);

namespace Byfareska\Crud\Controller;

use Byfareska\Crud\Dashboard\Dashboards;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Routing\CrudRouteLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Environment;

/**
 * The dashboard of a panel ("/" of its scope) and what its grid saves through (the dashboard-grid controller): the
 * positions, a widget added or removed, the layout reset or stored for everybody. The routes come from
 * {@see CrudRouteLoader}, one set per scope with a dashboard ("board" = the name of the scope).
 *
 * The endpoints answer 204 and take JSON only from a script of the page: they ask for the header X-Requested-With,
 * which a page of another site cannot send without the browser asking first.
 */
#[AsController]
final readonly class DashboardController
{
    public function __construct(
        private Environment $twig,
        private Dashboards $dashboards,
        private Scopes $scopes,
    ) {
    }

    public function show(string $board): Response
    {
        $dashboard = $this->dashboards->view($board);
        // The panel of a board of its own is the one that has it.
        $scope = $this->scopes->ofBoard($board) ?? $this->scopes->get($board);

        // A board of its own: its name for a heading, and the template it may have (byfareska_crud.scopes.*.boards).
        $own = $scope->boards[$board] ?? null;

        return new Response($this->twig->render($own['template'] ?? '@CrudTheme/panel/dashboard.html.twig', [
            'layout' => $scope->layout,
            'scope' => $scope,
            'dashboard' => $dashboard,
            'title' => $own['label'] ?? 'panel.nav.dashboard',
        ]));
    }

    public function save(string $board, Request $request): Response
    {
        $positions = [];
        foreach ($this->list($this->json($request)['placements'] ?? null) as $position) {
            if (is_array($position) && is_string($position['id'] ?? null)) {
                $positions[$position['id']] = [
                    'x' => $this->int($position['x'] ?? 0),
                    'y' => $this->int($position['y'] ?? 0),
                    'w' => $this->int($position['w'] ?? 1),
                    'h' => $this->int($position['h'] ?? 1),
                ];
            }
        }
        $this->dashboards->move($board, $positions);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function add(string $board, Request $request): Response
    {
        $body = $this->json($request);
        $widget = $body['widget'] ?? null;
        if (!is_string($widget)) {
            throw new BadRequestHttpException('Name the widget.');
        }
        $this->dashboards->add(
            $board,
            $widget,
            isset($body['w']) ? $this->int($body['w']) : null,
            isset($body['h']) ? $this->int($body['h']) : null,
        );

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function remove(string $board, string $id, Request $request): Response
    {
        $this->json($request);
        $this->dashboards->remove($board, $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function reset(string $board, Request $request): Response
    {
        $this->json($request);
        $this->dashboards->reset($board);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function share(string $board, Request $request): Response
    {
        $this->json($request);
        $this->dashboards->share($board);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * The body of a request of the grid.
     *
     * @return array<array-key, mixed>
     *
     * @throws BadRequestHttpException not from a script of the page, or no JSON
     */
    private function json(Request $request): array
    {
        if ($request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
            throw new BadRequestHttpException('Only the dashboard of the page may change it.');
        }
        if ($request->getContent() === '') {
            return [];
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            throw new BadRequestHttpException('The body is not JSON.');
        }

        return $body;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
