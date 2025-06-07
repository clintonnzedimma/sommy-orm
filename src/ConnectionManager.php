<?php
// src/ConnectionManager.php
namespace Sommy\ORM;

class ConnectionManager
{
    protected \PDO $connection;

    public function __construct(array $config)
    {
        // Initialize PDO based on $config['dialect']
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }
}
