<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HomeController;
use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    public function test_index_returns_the_home_view_with_a_title(): void
    {
        $view = (new HomeController())->index(new Request('GET', '/'));

        self::assertSame('home', $view->template);
        self::assertSame('Home', $view->data['title']);
    }

    public function test_index_reports_the_running_php_version(): void
    {
        $view = (new HomeController())->index(new Request('GET', '/'));

        self::assertSame(PHP_VERSION, $view->data['phpVersion']);
    }
}
