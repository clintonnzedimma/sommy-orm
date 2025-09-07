<?php
namespace Sommy\ORM;

use PDO;

class SommyManager
{
    protected ConnectionManager $connManager;
    protected QueryInterface $queryInterface;

    public function __construct(array $config)
    {
        $this->connManager = new ConnectionManager($config);
        $this->queryInterface = new QueryInterface($this->connManager);
    }

    public function getConnection(): PDO
    {
        return $this->connManager->getConnection();
    }

    public function getQueryInterface(): QueryInterface
    {
        return $this->queryInterface;
    }

    public function authenticate(): bool
    {
        try {
            $stmt = $this->getConnection()->query('SELECT 1');
            $stmt->fetch();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Define a model (Sequelize-style). Returns an instance of an anonymous subclass
     * of Model which holds its own static configuration. You can call both instance
     * and static methods on the returned object/class.
     */
    public function define(string $name, array $attributes, array $options = []): Model
    {
        $table = $options['tableName'] ?? strtolower($name);
        $manager = $this;
        $class = new class extends Model {
            // No body; base Model provides functionality
        };
        // Bind configuration for this anonymous subclass
        $class::configure($manager, $table, $attributes, $options);
        return $class;
    }
}
