<?php

declare(strict_types=1);

namespace App\SharedKernel\Persistence;

use Doctrine\DBAL\Connection;

final class Database
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->connection->fetchAssociative($sql, $params);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->connection->fetchAllAssociative($sql, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function fetchOne(string $sql, array $params = []): mixed
    {
        return $this->connection->fetchOne($sql, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->connection->executeStatement($sql, $params);
    }

    public function tx(string $organizationId, callable $fn): mixed
    {
        return $this->connection->transactional(function () use ($organizationId, $fn): mixed {
            $this->connection->executeStatement(
                "SELECT set_config('app.organization_id', :org, true)",
                ['org' => $organizationId],
            );

            return $fn();
        });
    }
}
