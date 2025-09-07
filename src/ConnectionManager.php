<?php
namespace Sommy\ORM;

use PDO;
use PDOException;

class ConnectionManager
{
    protected PDO $connection;
    protected array $config;

    /**
     * Expected $config keys:
     * - dialect: mysql|mariadb|sqlite|pgsql
     * - host, port, database, username, password, charset (for mysql)
     * - path (for sqlite) or database as path
     * - options: PDO options array
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connection = $this->createPdo($config);
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    public function getDialect(): string
    {
        return strtolower((string)($this->config['dialect'] ?? 'mysql'));
    }

    protected function createPdo(array $config): PDO
    {
        $dialect = strtolower((string)($config['dialect'] ?? 'mysql'));
        $options = $config['options'] ?? [];
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        // Reasonable default options
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $options = $options + $defaultOptions;

        try {
            switch ($dialect) {
                case 'mysql':
                case 'mariadb':
                    $host = $config['host'] ?? '127.0.0.1';
                    $port = $config['port'] ?? 3306;
                    $db   = $config['database'] ?? ($config['dbname'] ?? '');
                    $charset = $config['charset'] ?? 'utf8mb4';
                    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
                    return new PDO($dsn, $username, $password, $options);
                case 'pgsql':
                case 'postgres':
                case 'postgresql':
                    $host = $config['host'] ?? '127.0.0.1';
                    $port = $config['port'] ?? 5432;
                    $db   = $config['database'] ?? ($config['dbname'] ?? '');
                    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
                    return new PDO($dsn, $username, $password, $options);
                case 'sqlite':
                    $path = $config['path'] ?? ($config['database'] ?? ($config['dbname'] ?? ':memory:'));
                    $dsn = "sqlite:{$path}";
                    return new PDO($dsn, null, null, $options);
                default:
                    throw new \InvalidArgumentException("Unsupported dialect: {$dialect}");
            }
        } catch (PDOException $e) {
            throw new \RuntimeException('Failed to connect via PDO: ' . $e->getMessage(), 0, $e);
        }
    }
}
