<?php

declare(strict_types=1);

namespace Byfareska\Crud\Controller;

use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Page\CrudContext;
use Byfareska\Crud\Page\CrudPages;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Routing\CrudRoutes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The list page, the details page, the add/edit page and the pages of #[AsCrudPage] of every #[AsCrud] resource; the
 * routes come from {@see CrudRoutes}.
 * The pages are shells: the work is done by the Crud:List and Crud:Form live components.
 * One action per page, each with its template written here: no template name comes from the route.
 */
#[AsController]
final readonly class CrudPageController
{
    public function __construct(
        private Environment $twig,
        private CrudPages $pages,
        private CrudRegistry $registry,
        private CrudAccess $access,
        private RecordActions $actions,
        // The page the generic ones are put into, by the route scope of the resource.
        private Scopes $scopes,
        private RecordGuards $guards = new RecordGuards(),
    ) {
    }

    public function list(Request $request, string $crud): Response
    {
        return $this->render('list', $this->pages->open($request, $crud));
    }

    public function show(Request $request, string $crud, ?string $id = null): Response
    {
        return $this->render('show', $this->pages->open($request, $crud, $id));
    }

    public function form(Request $request, string $crud, ?string $id = null): Response
    {
        return $this->render(
            'form',
            $this->pages->open($request, $crud, $id, RecordScreen::Form),
        );
    }

    /**
     * A page of #[AsCrudPage]: its component in the frame of a page of a record. Only one of the pages declared for
     * the resource - a name that is none is a 404.
     */
    public function page(Request $request, string $crud, string $name, ?string $id = null): Response
    {
        $page = $this->registry->pagesOf($crud)[$name]
            ?? throw new NotFoundHttpException(sprintf('No page "%s" of "%s".', $name, $crud));
        // Before the record is read: somebody the page is not for learns nothing of it, not even whether it exists.
        $this->access->assertPageGranted($page, $this->registry->get($crud));
        // A page of the whole resource is for those who may see its list.
        $context = $this->pages->open($request, $crud, $page->collection ? null : $id);
        // A page is an action of the record: what its RecordActionGuards refuse is not to be opened by its address.
        if ($context->record instanceof RecordView && !$this->guards->allowsAction($name, $context->record)) {
            throw new AccessDeniedHttpException(sprintf('The page "%s" is not for this record of "%s".', $name, $crud));
        }

        return $this->render('page', $context, ['page' => $page]);
    }

    /**
     * @param string $page "list", "show", "form" or "page": the template of the resource (AsCrud::$pageTemplates),
     *     else the theme's
     * @param array<string, mixed> $variables what else the template is given
     */
    private function render(string $page, CrudContext $context, array $variables = []): Response
    {
        $definition = $context->crud;
        $template = $definition->pageTemplates[$page] ?? sprintf('@CrudTheme/crud/%s.html.twig', $page);

        return new Response($this->twig->render($template, $variables + [
            'layout' => $this->scopes->get($definition->scope)->layout,
            'crud' => $definition,
            'id' => $context->id,
            'details' => $context->details,
            // What can be done with the record (RecordAction), for its buttons (crud_action()).
            'actions' => $context->record instanceof RecordView ? $this->actions->buttons($context->record) : [],
            // What other classes add under the details of a record - see AsCrudDetailsSection.
            'sections' => $context->details === null ? [] : $this->registry->detailsSectionsOf($definition->name),
        ]));
    }
}
