<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Tests\Functional\App\Entity\Contract;
use Dom\Element;
use Dom\HTMLDocument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page opened before the record changed acts on the record as it is now: a contract signed meanwhile (the guard
 * SignedContracts) is not saved over by its edit form, nor deleted by the button of its details or a row of the list -
 * the guards go by the database at the time of the action, not by what the page was drawn with.
 */
final class StalePageTest extends PanelTestCase
{
    public function testTheFormOpenedBeforeTheRecordWasSignedDoesNotSaveIt(): void
    {
        $this->boot();
        $contract = $this->seed();
        $props = $this->props('/contracts/' . $contract->id . '/edit', 'Crud:Form');
        $this->sign($contract);

        $response = $this->live('/_crud/Crud:Form/save', $props);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('SIGNED-1', $this->numberOf($contract));
    }

    public function testTheDeleteButtonOfThePageOpenedBeforeTheRecordWasSignedDoesNotDeleteIt(): void
    {
        $this->boot();
        $contract = $this->seed();
        $props = $this->props('/contracts/' . $contract->id, 'Crud:Delete');
        $this->sign($contract);

        self::assertSame(200, $this->live('/_crud/Crud:Delete/delete', $props)->getStatusCode());
        self::assertSame('SIGNED-1', $this->numberOf($contract));
    }

    public function testTheListOpenedBeforeTheRecordWasSignedDoesNotDeleteIt(): void
    {
        $this->boot();
        $contract = $this->seed();
        $props = $this->props('/contracts', 'Crud:List');
        $this->sign($contract);

        self::assertSame(200, $this->live('/_crud/Crud:List/delete', $props, ['id' => (string) $contract->id])
            ->getStatusCode());
        self::assertSame('SIGNED-1', $this->numberOf($contract));
    }

    private function sign(Contract $contract): void
    {
        $this->entities()->getConnection()->executeStatement(
            'UPDATE contract SET number = ? WHERE id = ?',
            ['SIGNED-1', $contract->id],
        );
    }

    private function numberOf(Contract $contract): ?string
    {
        $this->entities()->clear();

        return $this->entities()->find(Contract::class, $contract->id)?->number;
    }

    private function props(string $page, string $component): string
    {
        $document = HTMLDocument::createFromString($this->request($page), LIBXML_NOERROR);
        $element = $document->querySelector(sprintf('[data-live-name-value="%s"]', $component));
        self::assertInstanceOf(Element::class, $element, $component . ' is not on ' . $page);

        return (string) $element->getAttribute('data-live-props-value');
    }

    /**
     * A live action as LiveComponent's script sends it.
     *
     * @param array<string, string> $args
     */
    private function live(string $uri, string $props, array $args = []): Response
    {
        $request = Request::create($uri, 'POST', ['data' => json_encode([
            'props' => json_decode($props, true),
            'args' => $args,
        ], JSON_THROW_ON_ERROR)]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/vnd.live-component+html');
        $request->headers->set('X-Live-Url', '/contracts');

        return $this->handle($request);
    }
}
