<?php
// src/DataTypes.php
namespace Sommy\ORM;

class DataTypes
{
    public static function INTEGER(): array
    {
        return ['type' => 'INTEGER', 'length' => null];
    }

    public static function STRING(int $len = 255): array
    {
        return ['type' => 'VARCHAR', 'length' => $len];
    }

    public static function DATE(): array
    {
        return ['type' => 'DATE', 'length' => null];
    }

    // … BOOLEAN, TEXT, FLOAT, DECIMAL, etc.
}
