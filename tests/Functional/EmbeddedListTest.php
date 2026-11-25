<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Tests\Functional\App\Entity\AccountStatus;
use Byfareska\Crud\Tests\Functional\App\Entity\Contract;
use Byfareska\Crud\Tests\Functional\App\Entity\Customer;
use Byfareska\Crud\Tests\Functional\App\Entity\Tariff;
use Dom\Element;
use Dom\HTMLDocument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A list embedded with fixedFilters (the contracts of one customer) acts only on its own records: the id of a live
 * action of a row comes from the browser, which can name a contract of another customer.
 */
final class EmbeddedListTest extends PanelTestCase
{
    public function testARowActionDeletesOnlyARecordOfTheList(): void
    {
        $this->boot();
        [$ours, $theirs] = $this->contracts();
        $props = $this->props($ours->customer);

        $this->live('/_crud/Crud:List/delete', $props, ['id' => (string) $theirs->id]);
        self::assertNotNull($this->contract($theirs), 'The contract of another customer was deleted.');

        $this->live('/_crud/Crud:List/delete', $props, ['id' => (string) $ours->id]);
        self::assertNull($this->contract($ours));
    }

    public function testThePreviewShowsOnlyARecordOfTheList(): void
    {
        $this->boot();
        [$ours, $theirs] = $this->contracts();
        $props = $this->props($ours->customer);

        $theirPreview = (string) $this->live('/_crud/Crud:List/preview', $props, ['id' => (string) $theirs->id])
            ->getContent();
        self::assertStringNotContainsString('2222', $theirPreview);

        $ourPreview = (string) $this->live('/_crud/Crud:List/preview', $props, ['id' => (string) $ours->id])
            ->getContent();
        self::assertStringContainsString('1111', $ourPreview);
    }

    /**
     * A contract of a customer the list is of, and one of another customer.
     *
     * @return array{Contract, Contract}
     */
    private function contracts(): array
    {
        $this->seed();
        $entities = $this->entities();
        $ours = new Contract('1111', new Customer('Ewa', '1', AccountStatus::Active, [], Tariff::G12));
        $theirs = new Contract('2222', new Customer('Jan', '2', AccountStatus::Active, [], Tariff::G12));
        foreach ([$ours, $theirs] as $contract) {
            $entities->persist($contract->customer);
            $entities->persist($contract);
        }
        $entities->flush();
        $entities->clear();

        return [$ours, $theirs];
    }

    private function contract(Contract $contract): ?Contract
    {
        $this->entities()->clear();

        return $this->entities()->find(Contract::class, $contract->id);
    }

    /** The signed props of the list of the contracts of a customer, as a page drawing it has them. */
    private function props(Customer $customer): string
    {
        $twig = $this->container()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate(
            "{{ component('Crud:List', {resource: 'contract', fixedFilters: {customer: customer}, inUrl: false}) }}",
        )->render(['customer' => (string) $customer->id]);

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $element = $document->querySelector('[data-live-name-value="Crud:List"]');
        self::assertInstanceOf(Element::class, $element);
        self::assertStringContainsString('1111', $html);
        self::assertStringNotContainsString('2222', $html);

        return (string) $element->getAttribute('data-live-props-value');
    }

    /**
     * A live action as LiveComponent's script sends it.
     *
     * @param array<string, string> $args
     */
    private function live(string $uri, string $props, array $args): Response
    {
        $request = Request::create($uri, 'POST', ['data' => json_encode([
            'props' => json_decode($props, true),
            'args' => $args,
        ], JSON_THROW_ON_ERROR)]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/vnd.live-component+html');
        $request->headers->set('X-Live-Url', '/customers');
        $response = $this->handle($request);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $response;
    }
}
