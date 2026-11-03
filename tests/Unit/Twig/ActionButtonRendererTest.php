<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Twig;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\ActionPlace;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudResourceProvider;
use Byfareska\Crud\Twig\ActionButtonRenderer;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\UX\TwigComponent\ComponentRendererInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * An action is drawn by the theme, as fits the place - or by its own component; one action of one resource otherwise.
 */
final class ActionButtonRendererTest extends TestCase
{
    public function testTheThemeDrawsAButtonByThePlace(): void
    {
        $button = new ActionButton('stats', 'crud.product.action.stats', 'graph-up');

        self::assertSame('row:stats', $this->render($button, 'row'));
        self::assertSame('menu:stats', $this->render($button, 'menu'));
        self::assertSame('bar:stats', $this->render($button, 'bar'));
    }

    public function testAButtonWithAComponentIsDrawnByIt(): void
    {
        $button = new ActionButton('restock', 'crud.product.action.restock', 'box', component: 'App:Restock', props: [
            'step' => 10,
        ]);

        self::assertSame('App:Restock button,place,step menu', $this->render($button, 'menu'));
    }

    public function testAPlaceOfNoBlockIsAMistake(): void
    {
        // A place of a theme of yours (a card of a list) has its block in your actions.html.twig.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('action_footer');

        $this->render(new ActionButton('stats', 'crud.product.action.stats', 'graph-up'), 'footer');
    }

    public function testTheMostSpecificBlockThereIs(): void
    {
        $twig = new Environment(new ArrayLoader([
            ActionButtonRenderer::THEME => '{% block action_bar %}bar:{{ button.name }}{% endblock %}'
                . '{% block action_cancel_bar %}every cancel{% endblock %}',
            'order_theme.html.twig' => '{% block crud_order_action_cancel_bar %}the cancel of an order{% endblock %}',
        ]));
        $source = new DoctrineSource(stdClass::class);
        $orders = new AsCrud('order', '/orders', 'cart', source: $source, theme: 'order_theme.html.twig');
        $registry = new CrudRegistry(providers: [new readonly class ($orders) implements CrudResourceProvider {
            public function __construct(private AsCrud $crud)
            {
            }

            public function resources(): iterable
            {
                yield $this->crud;
            }
        }]);
        $renderer = new ActionButtonRenderer(self::createStub(ComponentRendererInterface::class), $registry);
        $cancel = new ActionButton('cancel', 'app.cancel', 'x');

        self::assertSame('the cancel of an order', $renderer->render($twig, $cancel->of('order'), 'bar'));
        self::assertSame('every cancel', $renderer->render($twig, $cancel, 'bar'));
        self::assertSame('bar:edit', $renderer->render($twig, new ActionButton('edit', 'edit', 'pencil'), 'bar'));
    }

    private function render(ActionButton $button, string $place): string
    {
        $twig = new Environment(new ArrayLoader([
            ActionButtonRenderer::THEME => '{% block action_row %}row:{{ button.name }}{% endblock %}'
                . '{% block action_menu %}menu:{{ button.name }}{% endblock %}'
                . '{% block action_bar %}bar:{{ button.name }}{% endblock %}',
        ]));
        $components = new class implements ComponentRendererInterface {
            /** @param array<string, mixed> $props */
            public function createAndRender(string $name, array $props = []): string
            {
                $place = $props['place'] ?? null;

                return sprintf(
                    '%s %s %s',
                    $name,
                    implode(',', array_keys($props)),
                    $place instanceof ActionPlace ? $place->value : '?',
                );
            }
        };

        return new ActionButtonRenderer($components)->render($twig, $button, $place);
    }
}
