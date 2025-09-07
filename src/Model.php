<?php
namespace Sommy\ORM;

use Sommy\ORM\SommyManager;

/**
 * ActiveRecord-style Model with simple CRUD methods.
 * Usage:
 *  $sommy = new SommyManager($config);
 *  $User = $sommy->define('User', [
 *      'id' => DataTypes::INTEGER(['primaryKey' => true, 'autoIncrement' => true, 'allowNull' => false]),
 *      'name' => DataTypes::STRING(255, ['allowNull' => false]),
 *  ]);
 *  $u = $User->create(['name' => 'Alice']);
 *  $all = $User->findAll();
 */
abstract class Model
{
    protected static SommyManager $manager;
    protected static string $tableName;
    protected static array $attributes = [];
    protected static ?string $primaryKey = null;

    protected array $data = [];

    // ----- Configuration & helpers -----
    public static function configure(SommyManager $manager, string $tableName, array $attributes, array $options = []): void
    {
        static::$manager = $manager;
        static::$tableName = $tableName;
        static::$attributes = $attributes;
        static::$primaryKey = $options['primaryKey'] ?? static::detectPrimaryKey($attributes);
    }

    protected static function detectPrimaryKey(array $attributes): ?string
    {
        foreach ($attributes as $name => $def) {
            if (!empty($def['primaryKey'])) {
                return $name;
            }
        }
        return 'id';
    }

    protected static function qi(): QueryInterface
    {
        return static::$manager->getQueryInterface();
    }

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function fill(array $data): static
    {
        foreach ($data as $k => $v) {
            $this->data[$k] = $v;
        }
        return $this;
    }

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    // ----- CRUD -----
    public function save(): bool
    {
        $pk = static::$primaryKey;
        $hasPk = $pk && array_key_exists($pk, $this->data) && $this->data[$pk] !== null;
        if ($hasPk) {
            $idVal = $this->data[$pk];
            $data = $this->data;
            unset($data[$pk]);
            $affected = static::update($data, [$pk => $idVal]);
            return $affected > 0;
        }
        $id = static::create($this->data);
        if ($pk && $id) {
            $this->data[$pk] = $id;
        }
        return (bool)$id;
    }

    public static function create(array $values): string|bool
    {
        return static::qi()->insert(static::$tableName, $values);
    }

    public static function findAll(array $where = [], array $options = []): array
    {
        $rows = static::qi()->select(static::$tableName, $options['attributes'] ?? ['*'], $where, $options);
        return array_map(fn($r) => new static($r), $rows);
    }

    public static function findOne(array $where = [], array $options = []): ?static
    {
        $options['limit'] = 1;
        $rows = static::qi()->select(static::$tableName, $options['attributes'] ?? ['*'], $where, $options);
        if (empty($rows)) return null;
        return new static($rows[0]);
    }

    public static function update(array $values, array $where): int
    {
        return static::qi()->update(static::$tableName, $values, $where);
    }

    public static function destroy(array $where): int
    {
        return static::qi()->delete(static::$tableName, $where);
    }

    public function delete(): int
    {
        $pk = static::$primaryKey;
        if (!$pk || !array_key_exists($pk, $this->data)) return 0;
        return static::destroy([$pk => $this->data[$pk]]);
    }

    // Convenience instance proxies for static methods
    public function find(array $where = [], array $options = []): array
    {
        return static::findAll($where, $options);
    }
}

