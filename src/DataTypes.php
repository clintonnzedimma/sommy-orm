<?php
namespace Sommy\ORM;

class DataTypes
{
    public static function INTEGER(array $options = []): array
    {
        return self::withOptions(['type' => 'INTEGER'], $options);
    }

    public static function BIGINT(array $options = []): array
    {
        return self::withOptions(['type' => 'BIGINT'], $options);
    }

    public static function SMALLINT(array $options = []): array
    {
        return self::withOptions(['type' => 'SMALLINT'], $options);
    }

    public static function STRING(int $len = 255, array $options = []): array
    {
        return self::withOptions(['type' => 'VARCHAR', 'length' => $len], $options);
    }

    public static function TEXT(array $options = []): array
    {
        return self::withOptions(['type' => 'TEXT'], $options);
    }

    public static function BOOLEAN(array $options = []): array
    {
        return self::withOptions(['type' => 'BOOLEAN'], $options);
    }

    public static function DATE(array $options = []): array
    {
        return self::withOptions(['type' => 'DATE'], $options);
    }

    public static function DATETIME(array $options = []): array
    {
        return self::withOptions(['type' => 'DATETIME'], $options);
    }

    public static function TIME(array $options = []): array
    {
        return self::withOptions(['type' => 'TIME'], $options);
    }

    public static function TIMESTAMP(array $options = []): array
    {
        return self::withOptions(['type' => 'TIMESTAMP'], $options);
    }

    public static function FLOAT(array $options = []): array
    {
        return self::withOptions(['type' => 'FLOAT'], $options);
    }

    public static function DECIMAL(int $precision = 10, int $scale = 0, array $options = []): array
    {
        return self::withOptions(['type' => 'DECIMAL', 'precision' => $precision, 'scale' => $scale], $options);
    }

    public static function JSON(array $options = []): array
    {
        return self::withOptions(['type' => 'JSON'], $options);
    }

    public static function UUID(array $options = []): array
    {
        return self::withOptions(['type' => 'UUID'], $options);
    }

    public static function withOptions(array $typeDef, array $options): array
    {
        return $typeDef + $options;
    }
}

