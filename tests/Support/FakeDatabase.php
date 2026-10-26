<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Support;

use LogicException;
use WilyCode\Database\Driver\DatabaseInterface;

final class FakeDatabase implements DatabaseInterface
{
    public string $lastSql = '';
    /** @var array<int, mixed> */
    public array $lastParams = [];
    public int $lastColumnIndex = 0;
    /** @var array<int|string, mixed> */
    public array $lastTypes = [];

    public int|string|null $returnValue = 0;

    public function fetchColumn(
        string $query,
        array $params = [],
        int $columnIndex = 0,
        array $types = []
    ): mixed {
        $this->lastSql = $query;
        $this->lastParams = $params;
        $this->lastColumnIndex = $columnIndex;
        $this->lastTypes = $types;

        return $this->returnValue;
    }

    public function execute(string $query, array $params = [], array $types = []): int
    {
        throw new LogicException('FakeDatabase::execute() not implemented for this test.');
    }

    public function fetch(string $query, array $params = [], array $types = []): array
    {
        throw new LogicException('FakeDatabase::fetch() not implemented for this test.');
    }

    public function fetchAll(string $query, array $params = [], array $types = []): array
    {
        throw new LogicException('FakeDatabase::fetchAll() not implemented for this test.');
    }

    public function getLastInsertId(): string
    {
        throw new LogicException('FakeDatabase::getLastInsertId() not implemented for this test.');
    }


    public function transaction(callable $fn): mixed
    {
        return $fn($this);
    }

    public function inTransaction(): bool
    {
        return false;
    }
}
