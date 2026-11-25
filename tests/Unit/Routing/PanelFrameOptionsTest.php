<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Routing;

use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Routing\PanelFrameOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A page of another site cannot show the panel in a frame of its own and have its buttons clicked unseen.
 */
final class PanelFrameOptionsTest extends TestCase
{
    public function testAnAnswerOfAPanelIsShownInFramesOfItsOwnOriginOnly(): void
    {
        $response = $this->answer(new PanelFrameOptions(), 'admin');

        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function testTheConfigurationSaysWhatTheHeaderIs(): void
    {
        $deny = $this->answer(new PanelFrameOptions('DENY'), 'admin');

        self::assertSame('DENY', $deny->headers->get('X-Frame-Options'));
        self::assertFalse($this->answer(new PanelFrameOptions(false), 'admin')->headers->has('X-Frame-Options'));
    }

    public function testAnAnswerOutsideThePanelsIsNotChanged(): void
    {
        self::assertFalse($this->answer(new PanelFrameOptions(), null)->headers->has('X-Frame-Options'));
    }

    public function testWhatTheApplicationSaysItselfIsKept(): void
    {
        $own = new Response();
        $own->headers->set('X-Frame-Options', 'DENY');
        $this->answer(new PanelFrameOptions(), 'admin', $own);
        self::assertSame('DENY', $own->headers->get('X-Frame-Options'));

        $policy = new Response();
        $policy->headers->set('Content-Security-Policy', "default-src 'self'; frame-ancestors https://cms.example");
        self::assertFalse($this->answer(new PanelFrameOptions(), 'admin', $policy)->headers->has('X-Frame-Options'));
    }

    public function testASubRequestIsLeftToTheMainOne(): void
    {
        $response = $this->answer(new PanelFrameOptions(), 'admin', type: HttpKernelInterface::SUB_REQUEST);

        self::assertFalse($response->headers->has('X-Frame-Options'));
    }

    private function answer(
        PanelFrameOptions $listener,
        ?string $scope,
        Response $response = new Response(),
        int $type = HttpKernelInterface::MAIN_REQUEST,
    ): Response {
        $request = Request::create('/admin/products');
        if ($scope !== null) {
            $request->attributes->set(Scope::ATTRIBUTE, $scope);
        }
        $listener(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $type, $response));

        return $response;
    }
}
