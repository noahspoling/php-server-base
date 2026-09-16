<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{id: int|string, name: string, email: string, created_at: string}>
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT id, name, email, created_at FROM users ORDER BY id');

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $name, string $email): void
    {
        $this->pdo
            ->prepare('INSERT INTO users (name, email) VALUES (:name, :email)')
            ->execute(['name' => $name, 'email' => $email]);
    }
}
