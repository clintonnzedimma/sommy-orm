<?php
// src/SommyManager.php
namespace Sommy\ORM;

use Sommy\ORM\ConnectionManager;
use Sommy\ORM\QueryInterface;

class SommyManager
{
    protected ConnectionManager $connManager;
    protected QueryInterface    $queryInterface;

    public function __construct(array $config)
    {
        $this->connManager    = new ConnectionManager($config);
        $this->queryInterface = new QueryInterface($this->connManager);
    }

    public function authenticate(): bool
    {
        return $this->connManager->getConnection()->ping();
    }

    public function define(string $name, array $attributes, array $options = []): Model
    {
        return Model::init($this, $name, $attributes, $options);
    }

    public function getQueryInterface(): QueryInterface
    {
        return $this->queryInterface;
    }
}
