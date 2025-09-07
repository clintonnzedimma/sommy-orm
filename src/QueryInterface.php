<?php
namespace Sommy\ORM;

use PDO;

class QueryInterface
{
    protected ConnectionManager $connManager;

    public function __construct(ConnectionManager $conn)
    {
        $this->connManager = $conn;
    }

    public function pdo(): PDO
    {
        return $this->connManager->getConnection();
    }

    public function select(string $table, array $columns = ['*'], array $where = [], array $options = []): array
    {
        [$whereSql, $params] = $this->buildWhere($where);
        $cols = $columns ? implode(', ', array_map(fn($c) => $this->formatSelectColumn((string)$c), $columns)) : '*';
        $sql = "SELECT {$cols} FROM " . $this->quoteIdent($table) . ($whereSql ? " WHERE {$whereSql}" : '');
        if (!empty($options['order'])) {
            $sql .= ' ORDER BY ' . $this->buildOrder($options['order']);
        }
        if (!empty($options['limit'])) {
            $sql .= ' LIMIT ' . (int)$options['limit'];
        }
        if (!empty($options['offset'])) {
            $sql .= ' OFFSET ' . (int)$options['offset'];
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Format a select column ensuring '*' and table.* are not quoted, and simple aliases work.
     */
    protected function formatSelectColumn(string $col): string
    {
        $c = trim($col);
        // Plain star
        if ($c === '*') return '*';
        // table.* form
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.\*$/', $c, $m)) {
            return $this->quoteIdent($m[1]) . '.*';
        }
        // Handle aliases: "expr AS alias" (case-insensitive)
        if (preg_match('/^(.+?)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $c, $m)) {
            $expr = $this->formatSelectColumn($m[1]);
            $alias = $this->quoteIdent($m[2]);
            return $expr . ' AS ' . $alias;
        }
        // If looks like a function call or contains spaces, return as-is
        if (str_contains($c, '(') || str_contains($c, ')') || str_contains($c, ' ')) {
            return $c;
        }
        // Normal identifier (may include table.col)
        if (str_contains($c, '.')) {
            [$t, $f] = explode('.', $c, 2);
            return $this->quoteIdent($t) . '.' . $this->quoteIdent($f);
        }
        return $this->quoteIdent($c);
    }

    public function insert(string $table, array $data): string|bool
    {
        if (empty($data)) {
            return false;
        }
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO ' . $this->quoteIdent($table) . ' (' . implode(', ', array_map(fn($c) => $this->quoteIdent($c), $cols)) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($data);
        return $this->pdo()->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        if (empty($data)) {
            return 0;
        }
        [$whereSql, $whereParams] = $this->buildWhere($where);
        $setSql = implode(', ', array_map(fn($c) => $this->quoteIdent($c) . ' = :' . $c, array_keys($data)));
        $sql = 'UPDATE ' . $this->quoteIdent($table) . ' SET ' . $setSql . ($whereSql ? ' WHERE ' . $whereSql : '');
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($data + $whereParams);
        return $stmt->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        [$whereSql, $params] = $this->buildWhere($where);
        $sql = 'DELETE FROM ' . $this->quoteIdent($table) . ($whereSql ? ' WHERE ' . $whereSql : '');
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function createTable(string $name, array $attributes, array $options = []): bool
    {
        $dialect = $this->connManager->getDialect();
        $cols = [];
        $pk = [];
        foreach ($attributes as $col => $def) {
            [$colSql, $isPk] = $this->columnSql($dialect, $col, $def);
            $cols[] = $colSql;
            if ($isPk) { $pk[] = $col; }
        }
        if ($pk && !$this->hasInlinePk($dialect, $attributes)) {
            $cols[] = 'PRIMARY KEY (' . implode(', ', array_map(fn($c) => $this->quoteIdent($c), $pk)) . ')';
        }
        $tableSql = 'CREATE TABLE ' . $this->quoteIdent($name) . ' (' . implode(', ', $cols) . ')';
        if (!empty($options['ifNotExists'])) {
            $tableSql = 'CREATE TABLE IF NOT EXISTS ' . $this->quoteIdent($name) . ' (' . implode(', ', $cols) . ')';
        }
        return $this->pdo()->exec($tableSql) !== false;
    }

    public function dropTable(string $name, array $options = []): bool
    {
        $sql = 'DROP TABLE ' . ($options['ifExists'] ?? true ? 'IF EXISTS ' : '') . $this->quoteIdent($name);
        return $this->pdo()->exec($sql) !== false;
    }

    protected function buildOrder(array|string $order): string
    {
        if (is_string($order)) return $order;
        $parts = [];
        foreach ($order as $col => $dir) {
            if (is_int($col)) { // ["col ASC", "other DESC"]
                $parts[] = (string)$dir;
            } else {
                $parts[] = $this->quoteIdent($col) . ' ' . (strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC');
            }
        }
        return implode(', ', $parts);
    }

    protected function buildWhere(array $where): array
    {
        $parts = [];
        $params = [];
        foreach ($where as $col => $val) {
            $param = 'w_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $col) . '_' . count($params);
            if (is_null($val)) {
                $parts[] = $this->quoteIdent($col) . ' IS NULL';
            } elseif (is_array($val)) {
                if (empty($val)) {
                    $parts[] = '1=0';
                } else {
                    $inParams = [];
                    foreach ($val as $i => $v) {
                        $p = $param . '_' . $i;
                        $inParams[] = ':' . $p;
                        $params[$p] = $v;
                    }
                    $parts[] = $this->quoteIdent($col) . ' IN (' . implode(', ', $inParams) . ')';
                }
            } else {
                $parts[] = $this->quoteIdent($col) . ' = :' . $param;
                $params[$param] = $val;
            }
        }
        return [implode(' AND ', $parts), $params];
    }

    protected function columnSql(string $dialect, string $name, array $def): array
    {
        $type = strtoupper((string)($def['type'] ?? 'VARCHAR'));
        $length = $def['length'] ?? null;
        $precision = $def['precision'] ?? null;
        $scale = $def['scale'] ?? null;
        $nullable = array_key_exists('allowNull', $def) ? (bool)$def['allowNull'] : true;
        $autoInc = (bool)($def['autoIncrement'] ?? false);
        $unique = (bool)($def['unique'] ?? false);
        $primary = (bool)($def['primaryKey'] ?? false);
        $default = $def['default'] ?? null;

        $sqlType = $this->mapType($dialect, $type, $length, $precision, $scale, $autoInc, $primary);
        $parts = [$this->quoteIdent($name), $sqlType];
        if (!$nullable && !$primary) { $parts[] = 'NOT NULL'; }
        if ($unique) { $parts[] = 'UNIQUE'; }
        if (!$autoInc && $default !== null) { $parts[] = 'DEFAULT ' . $this->literal($default); }
        return [implode(' ', $parts), $primary];
    }

    protected function hasInlinePk(string $dialect, array $attributes): bool
    {
        // SQLite and MySQL can do inline PRIMARY KEY AUTOINCREMENT definitions
        foreach ($attributes as $def) {
            if (!empty($def['primaryKey'])) return true;
        }
        return false;
    }

    protected function mapType(string $dialect, string $type, $length, $precision, $scale, bool $autoInc, bool $primary): string
    {
        $dialect = strtolower($dialect);
        switch ($type) {
            case 'INTEGER':
            case 'INT':
            case 'BIGINT':
            case 'SMALLINT':
                $t = $type === 'INT' ? 'INTEGER' : $type;
                if ($dialect === 'mysql' || $dialect === 'mariadb') {
                    $t = $type === 'BIGINT' ? 'BIGINT' : ($type === 'SMALLINT' ? 'SMALLINT' : 'INT');
                    $sql = $t;
                    if ($autoInc) { $sql .= ' AUTO_INCREMENT'; }
                    if ($primary) { $sql .= ' PRIMARY KEY'; }
                    return $sql;
                }
                if ($dialect === 'pgsql' || $dialect === 'postgres' || $dialect === 'postgresql') {
                    if ($autoInc && $t !== 'BIGINT') return 'SERIAL' . ($primary ? ' PRIMARY KEY' : '');
                    if ($autoInc && $t === 'BIGINT') return 'BIGSERIAL' . ($primary ? ' PRIMARY KEY' : '');
                }
                // sqlite: INTEGER PRIMARY KEY is auto-increment rowid
                if ($dialect === 'sqlite' && $primary) {
                    return 'INTEGER PRIMARY KEY' . ($autoInc ? ' AUTOINCREMENT' : '');
                }
                return 'INTEGER';
            case 'BOOLEAN':
                return $dialect === 'pgsql' ? 'BOOLEAN' : 'TINYINT(1)';
            case 'STRING':
            case 'VARCHAR':
                return 'VARCHAR(' . (int)($length ?? 255) . ')';
            case 'TEXT':
                return 'TEXT';
            case 'DATE':
                return 'DATE';
            case 'DATETIME':
                return $dialect === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
            case 'TIMESTAMP':
                return 'TIMESTAMP';
            case 'TIME':
                return 'TIME';
            case 'FLOAT':
                return 'FLOAT';
            case 'DECIMAL':
                $p = (int)($precision ?? 10); $s = (int)($scale ?? 0);
                return "DECIMAL({$p},{$s})";
            case 'JSON':
                return $dialect === 'mysql' || $dialect === 'mariadb' || $dialect === 'pgsql' ? 'JSON' : 'TEXT';
            case 'UUID':
                return $dialect === 'pgsql' ? 'UUID' : 'CHAR(36)';
            default:
                return $type;
        }
    }

    protected function quoteIdent(string $ident): string
    {
        // Basic identifier quoting using double quotes for pgsql/sqlite and backticks for mysql
        $dialect = $this->connManager->getDialect();
        if (in_array($dialect, ['mysql', 'mariadb'], true)) {
            return '`' . str_replace('`', '``', $ident) . '`';
        }
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    protected function literal(mixed $value): string
    {
        if ($value === null) return 'NULL';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_int($value) || is_float($value)) return (string)$value;
        return $this->pdo()->quote((string)$value);
    }
}
