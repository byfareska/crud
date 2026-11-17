<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Driver\Doctrine\DoctrineRecordLabels;
use Byfareska\Crud\Tests\Functional\App\Entity\AccountStatus;
use Byfareska\Crud\Tests\Functional\App\Entity\ContactChannel;
use Byfareska\Crud\Tests\Functional\App\Entity\Customer;
use Byfareska\Crud\Value\CrudValues;

/**
 * How the values of records are printed in the panel of an application: an enum that translates itself by its
 * translation, a related entity by the RelationChoiceLabel of the application - the same in a list, the details and
 * the choices of a filter.
 */
final class RecordValuesTest extends PanelTestCase
{
    public function testAnEnumThatTranslatesItselfIsPrintedTranslatedInTheList(): void
    {
        $this->boot();
        $this->seed();

        $html = $this->request('/customers');
        $cells = self::texts($html, 'tbody td');

        self::assertContains('aktywna', $cells);
        self::assertNotContains('active', $cells);
        // A list of them, entry by entry.
        self::assertSame(['telefon', 'list'], self::texts($html, 'tbody td .badge'));
        // An enum that does not translate itself is its value.
        self::assertContains('G12', $cells);
    }

    public function testAnEnumThatTranslatesItselfIsPrintedTranslatedInTheDetails(): void
    {
        $this->boot();
        $this->seed();

        $html = $this->request('/customers/1');

        self::assertSame(['Ewa Lisowska', 'aktywna', 'telefonlist', 'G12'], self::texts($html, 'main dd'));
        self::assertSame(['telefon', 'list'], self::texts($html, 'main dd .badge'));
        // The title of the record, of its name and status.
        self::assertSame(['Ewa Lisowska aktywna Klienci'], self::texts($html, 'h1'));
    }

    public function testAnEnumIsTranslatedInTheLocaleOfTheRequest(): void
    {
        $this->boot();
        $this->seed();

        $html = $this->request('/customers/1', 'en');

        self::assertContains('active account', self::texts($html, 'main dd'));
        self::assertSame(['by phone', 'by letter'], self::texts($html, 'main dd .badge'));
    }

    public function testInJsonAnEnumIsItsValue(): void
    {
        $values = $this->boot()->get(CrudValues::class);
        self::assertInstanceOf(CrudValues::class, $values);

        self::assertSame(
            '{"status":"active","channels":["phone","letter"]}',
            json_encode([
                'status' => $values->normalize(AccountStatus::Active),
                'channels' => $values->normalize([ContactChannel::Phone, ContactChannel::Letter]),
            ]),
        );
    }

    public function testARelationIsNamedByTheLabelOfTheApplicationInTheList(): void
    {
        $this->boot();
        $this->seed();

        $cells = self::texts($this->request('/contracts'), 'tbody td');

        self::assertContains('1187234 · label', $cells);
        self::assertNotContains('Ewa Lisowska (1187234)', $cells);
    }

    public function testARelationIsNamedByTheLabelOfTheApplicationInTheDetails(): void
    {
        $this->boot();
        $this->seed();

        self::assertSame(['1187234001', '1187234 · label'], self::texts($this->request('/contracts/1'), 'main dd'));
    }

    public function testARelationIsNamedByTheLabelOfTheApplicationInTheChoicesOfAFilter(): void
    {
        $this->boot();
        $this->seed();

        $choices = self::texts($this->request('/contracts'), '#filters_customer_value option');

        self::assertContains('1187234 · label', $choices);
    }

    public function testTheLabelOfTheApplicationIsAServiceByAutoconfigurationAlone(): void
    {
        $labels = $this->boot()->get(DoctrineRecordLabels::class);
        self::assertInstanceOf(DoctrineRecordLabels::class, $labels);
        $this->seed();
        $customer = $this->entities()->find(Customer::class, 1);
        self::assertInstanceOf(Customer::class, $customer);

        self::assertSame('1187234 · label', $labels->of($customer));
    }
}
