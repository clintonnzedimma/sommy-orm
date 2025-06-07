<?php
// src/QueryInterface.php
namespace Sommy\ORM;

class QueryInterface
{
    protected ConnectionManager $conn;

    public function __construct(ConnectionManager $conn)
    {
        $this->conn = $conn;
    }

    public function createTable(string $name, array $attributes): bool
    {
        // Build and execute CREATE TABLE SQL
        return true;
    }

    // dropTable(), addColumn(), removeColumn(), etc.
}
