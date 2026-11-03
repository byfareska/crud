<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Dashboard;

use Byfareska\Crud\Dashboard\DashboardContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What the whole dashboard is looked at for, known to every widget - also to one loaded after the page.
 */
final class DashboardContextTest extends TestCase
{
    public function testThePageAndALiveRequestOfItTellTheSame(): void
    {
        $page = new RequestStack();
        $page->push(Request::create('/admin?context[period]=q3&context[branch]=7&other=x'));
        self::assertSame(['period' => 'q3', 'branch' => '7'], new DashboardContext($page)->all());

        // A widget loaded after the page: by the address of the page its request comes from.
        $live = Request::create('/admin/_crud/App:Sales/__invoke');
        $live->headers->set('X-Live-Url', 'https://shop.test/admin?context[period]=q3');
        $lazy = new RequestStack();
        $lazy->push($live);
        self::assertSame('q3', new DashboardContext($lazy)->get('period'));
        self::assertSame('all', new DashboardContext($lazy)->get('branch', 'all'));
    }
}
