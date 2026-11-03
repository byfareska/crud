<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\AbstractCrudDriver;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\UseCase\ListPage;
use Byfareska\Crud\UseCase\Page;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validation;

/**
 * A driver of a list of an API: the list form made, the filters asked, what it does not do refused.
 */
final class AbstractCrudDriverTest extends TestCase
{
    public function testAReadOnlyDriverIsItsListAndItsRecords(): void
    {
        $driver = new class extends AbstractCrudDriver {
            /** @var array<string, mixed> */
            public array $asked = [];

            public function name(): string
            {
                return 'invoices_api';
            }

            public function listForm(AsCrud $crud): FormInterface
            {
                return $this->listFormBuilder($crud, sortable: ['number'], sort: ['-number'], search: 'query')
                    ->add('status', TextType::class, ['required' => false])
                    ->getForm();
            }

            public function list(AsCrud $crud, FormInterface $form): Page
            {
                $this->asked = $this->criteria($form) + ['sort' => $form->get('sort')->getData()];

                return new ListPage([['id' => 'FV/1']], 1, 1, 15);
            }
        };
        $driver->setFormFactory(Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory());
        $crud = new AsCrud('invoice', '/invoices', 'receipt', source: new DoctrineSource(stdClass::class));

        $form = $driver->listForm($crud);
        $form->submit(['page' => '1', 'perPage' => '15', 'status' => 'paid', 'query' => '']);
        self::assertTrue($form->isValid());
        $driver->list($crud, $form);
        self::assertSame(['status' => 'paid', 'sort' => ['-number']], $driver->asked, 'The order nobody chose.');

        $tooMany = $driver->listForm($crud);
        $tooMany->submit(['perPage' => '5000']);
        self::assertFalse($tooMany->isValid(), 'Not more a page than it allows.');

        $this->expectException(CrudFailure::class);
        $driver->delete($crud, 'FV/1');
    }
}
