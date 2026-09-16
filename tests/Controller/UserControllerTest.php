<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Http\Request;
use App\Repository\UserRepository;
use App\View\View;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserControllerTest extends TestCase
{
    private UserRepository $users;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );

        $this->users = new UserRepository($pdo);
    }

    public function test_index_returns_the_page_view_with_the_users(): void
    {
        $this->users->create('Ada Lovelace', 'ada@example.com');

        $view = (new UserController($this->users))->index(new Request('GET', '/users'));

        self::assertInstanceOf(View::class, $view);
        self::assertSame('users/index', $view->template);
        self::assertCount(1, $view->data['users']);
    }

    public function test_rows_returns_only_the_fragment_view(): void
    {
        $view = (new UserController($this->users))->rows(new Request('GET', '/users/rows'));

        self::assertSame('users/rows', $view->template);
    }

    public function test_store_creates_the_user_and_answers_with_the_rows_fragment(): void
    {
        $controller = new UserController($this->users);

        $view = $controller->store(new Request(
            'POST',
            '/users',
            post: ['name' => 'Grace Hopper', 'email' => 'grace@example.com'],
        ));

        self::assertSame('users/rows', $view->template);
        self::assertSame(['Grace Hopper'], array_column($this->users->all(), 'name'));
        self::assertCount(1, $view->data['users']);
    }

    public function test_store_ignores_a_submission_with_a_blank_name(): void
    {
        $controller = new UserController($this->users);

        $controller->store(new Request('POST', '/users', post: ['name' => '   ', 'email' => 'x@example.com']));

        self::assertSame([], $this->users->all());
    }

    public function test_store_ignores_a_submission_with_a_blank_email(): void
    {
        $controller = new UserController($this->users);

        $controller->store(new Request('POST', '/users', post: ['name' => 'Grace', 'email' => '']));

        self::assertSame([], $this->users->all());
    }

    public function test_store_ignores_a_submission_with_missing_fields_entirely(): void
    {
        $controller = new UserController($this->users);

        $controller->store(new Request('POST', '/users'));

        self::assertSame([], $this->users->all());
    }
}
