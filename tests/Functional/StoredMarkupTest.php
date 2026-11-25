<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Tests\Functional\App\Entity\AccountStatus;
use Byfareska\Crud\Tests\Functional\App\Entity\Contract;
use Byfareska\Crud\Tests\Functional\App\Entity\Customer;
use Byfareska\Crud\Theme\Themes;
use Doctrine\ORM\Tools\SchemaTool;
use Dom\HTMLDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

/**
 * Markup someone saved in a record is printed as text wherever the panel names or shows the record, in every theme:
 * the cells of a list, the details, the title of the page (<title>, the heading), a related record named by a
 * RelationChoiceLabel in a cell and in the choices of a filter and of a form. The cases of the advisories of other
 * admin panels: page titles made of the name of a record, values of fields printed raw, relation choices as HTML.
 */
final class StoredMarkupTest extends PanelTestCase
{
    private string $theme = 'daisyui';

    private const string NAME = '<img src=x onerror=alert(1)>';

    /** What CustomerLabel names the customer by, in the choices of the contracts. */
    private const string NUMBER = '"><svg onload=alert(2)>';

    private const string CONTRACT = '<script>alert(3)</script>';

    /**
     * @return iterable<string, array{string}>
     */
    public static function themes(): iterable
    {
        yield 'daisyui' => ['daisyui'];
        yield 'bootstrap5' => ['bootstrap5'];
        yield 'tailwind' => ['tailwind'];
    }

    #[DataProvider('themes')]
    public function testTheNameOfARecordIsTextInItsListAndDetails(string $theme): void
    {
        $this->seedMarkup($theme);

        $list = $this->page('/customers');
        $this->assertNoMarkup($list);
        self::assertContains(self::NAME, self::texts($list, 'tbody td'));

        $details = $this->page('/customers/1');
        $this->assertNoMarkup($details);
        self::assertContains(self::NAME, self::texts($details, 'main dd'));
        self::assertStringStartsWith(self::NAME, self::texts($details, 'h1')[0] ?? '');
        self::assertStringContainsString(self::NAME, self::texts($details, 'title')[0] ?? '');
    }

    #[DataProvider('themes')]
    public function testARelatedRecordIsTextInCellsDetailsAndChoices(string $theme): void
    {
        $this->seedMarkup($theme);
        $label = self::NUMBER . ' · label';

        $list = $this->page('/contracts');
        $this->assertNoMarkup($list);
        self::assertContains($label, self::texts($list, 'tbody td'));
        self::assertContains($label, self::texts($list, '#filters_customer_value option'));

        $details = $this->page('/contracts/1');
        $this->assertNoMarkup($details);
        self::assertSame([self::CONTRACT, $label], self::texts($details, 'main dd'));
        self::assertStringStartsWith(self::CONTRACT, self::texts($details, 'h1')[0] ?? '');

        $form = $this->page('/contracts/1/edit');
        $this->assertNoMarkup($form);
        self::assertContains($label, self::texts($form, 'select option'));
        // In an attribute: the value of its field.
        self::assertContains(self::CONTRACT, $this->values($form, 'input'));
    }

    /**
     * Choices drawn as radio buttons or checkboxes (expanded), whose labels are made of records, in the form theme of
     * the panel: their labels are text, as in a <select>.
     */
    #[DataProvider('themes')]
    public function testTheLabelsOfExpandedChoicesAreText(string $theme): void
    {
        $this->seedMarkup($theme);
        $themes = $this->useTheme();
        $forms = $this->container()->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $twig = $this->container()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $choices = [self::NAME => 'a', self::NUMBER => 'b'];

        foreach ([false, true] as $multiple) {
            $form = $forms->createNamed('customer', ChoiceType::class, null, [
                'choices' => $choices,
                'expanded' => true,
                'multiple' => $multiple,
                'csrf_protection' => false,
            ])->createView();
            $html = $twig->createTemplate(
                '{% form_theme form with crud_form_themes() %}{{ form_row(form) }}',
            )->render(['form' => $form]);

            $this->assertNoMarkup($html);
            // A label of each choice; daisyUI's has its text in a <span> inside.
            $labels = array_values(array_unique(self::texts($html, '#customer label, #customer span')));
            self::assertSame([self::NAME, self::NUMBER], $labels);
        }
        self::assertSame($theme, $themes->current());
    }

    /**
     * The values of the fields of a page a CSS selector finds.
     *
     * @return list<string>
     */
    private function values(string $html, string $selector): array
    {
        $values = [];
        foreach (HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelectorAll($selector) as $field) {
            $values[] = (string) $field->getAttribute('value');
        }

        return $values;
    }

    /**
     * No element of the markup of the records is one of the page, also none after a quote of theirs ("><svg).
     */
    private function assertNoMarkup(string $html): void
    {
        foreach (['<img src=x', '<svg onload', '<script>alert'] as $markup) {
            self::assertStringNotContainsString($markup, $html);
        }
    }

    /** A page drawn with the theme of the test. */
    private function page(string $uri): string
    {
        $themes = $this->useTheme();
        $html = $this->request($uri);
        self::assertSame($this->theme, $themes->current());

        return $html;
    }

    /**
     * The theme of the test, chosen once the kernel has reset its services after the last request (which forgets the
     * theme chosen before) - until the next request is over.
     */
    private function useTheme(): Themes
    {
        $kernel = $this->container()->get('kernel');
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $kernel->boot();
        $themes = $this->container()->get(Themes::class);
        self::assertInstanceOf(Themes::class, $themes);
        $themes->use($this->theme);

        return $themes;
    }

    private function seedMarkup(string $theme): void
    {
        $this->boot();
        $this->theme = $theme;

        $entities = $this->entities();
        $schema = new SchemaTool($entities);
        $metadata = $entities->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $customer = new Customer(self::NAME, self::NUMBER, AccountStatus::Active);
        $entities->persist($customer);
        $entities->persist(new Contract(self::CONTRACT, $customer));
        $entities->flush();
        $entities->clear();
    }
}
