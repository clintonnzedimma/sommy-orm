<?php
// src/Model.php
namespace Sommy\ORM;

use Sommy\ORM\SommyManager;

abstract class Model
{
    protected static SommyManager $manager;
    protected static string        $tableName;
    protected static array         $attributes;
    protected array                $data = [];

    public static function init(SommyManager $manager, string $name, array $attributes, array $options): static
    {
        static::$manager    = $manager;
        static::$tableName  = $options['tableName'] ?? strtolower($name);
        static::$attributes = $attributes;
        return new static;
    }

    public function save(): bool
    {
        // INSERT or UPDATE based on primary key presence
        return true;
    }

    public static function findAll(array $where = []): array
    {
        // SELECT * FROM table WHERE …
        return [];
    }

    public function __get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function __set($key, $val)
    {
        $this->data[$key] = $val;
    }
}
