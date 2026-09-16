<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
    }

    public function test_all_returns_an_empty_list_when_there_are_no_users(): void
    {
        self::assertSame([], $this->repository()->all());
    }

    public function test_a_created_user_comes_back_from_all(): void
    {
        $repository = $this->repository();

        $repository->create('Ada Lovelace', 'ada@example.com');

        $users = $repository->all();

        self::assertCount(1, $users);
        self::assertSame('Ada Lovelace', $users[0]['name']);
        self::assertSame('ada@example.com', $users[0]['email']);
    }

    public function test_all_is_ordered_by_id(): void
    {
        $repository = $this->repository();

        $repository->create('First', 'first@example.com');
        $repository->create('Second', 'second@example.com');

        self::assertSame(['First', 'Second'], array_column($repository->all(), 'name'));
    }

    public function test_a_name_containing_sql_is_stored_literally(): void
    {
        $repository = $this->repository();
        $hostile = "Rob'); DROP TABLE users;--";

        $repository->create($hostile, 'rob@example.com');

        $users = $repository->all();

        self::assertCount(1, $users, 'The users table should still exist and hold one row.');
        self::assertSame($hostile, $users[0]['name']);
    }

    private function repository(): UserRepository
    {
        return new UserRepository($this->pdo);
    }
}
