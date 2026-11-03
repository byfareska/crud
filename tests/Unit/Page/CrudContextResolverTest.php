<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Page;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\MapCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Page\CrudContext;
use Byfareska\Crud\Page\CrudContextResolver;
use Byfareska\Crud\Page\CrudPages;
use Byfareska\Crud\Resource\RecordActionGuard;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A controller of the application that changes a record has its guards asked for what it does.
 */
final class CrudContextResolverTest extends TestCase
{
    public function testWhatTheControllerDoesIsAskedOfTheGuards(): void
    {
        $pages = new class implements CrudPages {
            public ?RecordScreen $screen = null;

            public function open(
                Request $request,
                string $resource,
                ?string $id = null,
                RecordScreen $screen = RecordScreen::Details,
            ): CrudContext {
                $this->screen = $screen;
                $crud = new AsCrud($resource, '/invoices', 'receipt', source: new DoctrineSource(stdClass::class));

                return new CrudContext($crud, $id, null, new RecordView($crud, (string) $id, [], $screen));
            }
        };
        $paid = new readonly class implements RecordActionGuard {
            public function resource(): string
            {
                return 'invoice';
            }

            public function fields(): array
            {
                return [];
            }

            public function allowsAction(string $action, RecordView $record): bool
            {
                return $record->id !== '8';
            }
        };
        $resolver = new CrudContextResolver($pages, new RecordGuards([], [$paid]));
        $argument = new ArgumentMetadata('invoice', CrudContext::class, false, false, null, attributes: [
            new MapCrud('invoice', update: true, action: 'approve'),
        ]);

        $request = new Request(attributes: ['id' => '7']);
        self::assertCount(1, iterator_to_array($resolver->resolve($request, $argument), false));
        self::assertSame(RecordScreen::Form, $pages->screen, 'Changing it: the guards of Update.');

        $this->expectException(HttpException::class);
        iterator_to_array($resolver->resolve(new Request(attributes: ['id' => '8']), $argument), false);
    }
}
