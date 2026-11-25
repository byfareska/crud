<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Component\Crud\CrudList;
use Dom\Element;
use Dom\HTMLDocument;
use Symfony\Component\HttpFoundation\Request;

/**
 * The records chosen in a list are ids the browser sends: as many as it likes, each read by itself before a bulk
 * action. A list keeps no more than CrudList::MAX_SELECTED of them.
 */
final class ListSelectionTest extends PanelTestCase
{
    public function testAListKeepsAtMostSoManyRecordsChosenByHand(): void
    {
        $this->boot();
        $this->seed();
        $props = $this->props($this->request('/contracts'));
        $ids = array_map(strval(...), range(1, CrudList::MAX_SELECTED + 500));

        $request = Request::create('/_crud/Crud:List', 'POST', ['data' => json_encode([
            'props' => $props,
            'updated' => ['selected' => [...$ids, '7', '7']],
            'args' => [],
        ], JSON_THROW_ON_ERROR)]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/vnd.live-component+html');
        $request->headers->set('X-Live-Url', '/contracts');
        $response = $this->handle($request);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $selected = $this->props((string) $response->getContent())['selected'] ?? null;
        self::assertSame(array_slice($ids, 0, CrudList::MAX_SELECTED), $selected, 'The first of them, each once.');
    }

    /**
     * The props of the list of a page, or of a live response.
     *
     * @return array<mixed>
     */
    private function props(string $html): array
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $element = $document->querySelector('[data-live-name-value="Crud:List"]');
        self::assertInstanceOf(Element::class, $element, 'The list is not on the page.');
        $props = json_decode((string) $element->getAttribute('data-live-props-value'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($props);

        return $props;
    }
}
