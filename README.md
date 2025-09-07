# Sommy ORM

Sommy ORM is a tiny, Sequelize‑style ORM for PHP built on PDO. It focuses on being simple and explicit: you define models with a schema, create tables via a query interface, and do straightforward CRUD without magic.

Works with MySQL, MariaDB, SQLite, and PostgreSQL.

## Requirements
- PHP >= 8.0
- `ext-pdo` and the PDO driver for your database (e.g., `pdo_mysql`, `pdo_sqlite`, `pdo_pgsql`)

## Installation

Install via Composer (recommended):

```bash
composer require clintonnzedimma/sommy-orm
```

Or, if you are using this repository directly:

1) Install dependencies and dump autoload

```bash
composer install
composer dump-autoload
```

2) Require Composer autoloader from your app:

```php
require __DIR__ . '/vendor/autoload.php';
```

Composer autoload is configured for `Sommy\ORM\` → `src/`.

## Configuration
Create an array of connection settings and pass it to `Sommy\ORM\SommyManager`.

SQLite example:

```php
use Sommy\ORM\SommyManager;

$sommy = new SommyManager([
    'dialect'  => 'sqlite',
    'database' => __DIR__ . '/database.sqlite',
]);
```

MySQL/MariaDB example:

```php
$sommy = new SommyManager([
    'dialect'  => 'mysql', // or 'mariadb'
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'testdb',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
]);
```

### Connecting to MySQL

- Make sure the `pdo_mysql` extension is enabled (check with `php -m`).
- Ensure a MySQL server is running and you have database credentials.
- Create a database and user (example using the MySQL client):

```bash
mysql -u root -p
```

```sql
CREATE DATABASE testdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'appuser'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON testdb.* TO 'appuser'@'localhost';
FLUSH PRIVILEGES;
```

- Configure Sommy to use MySQL (see example above), e.g.:

```php
use Sommy\ORM\SommyManager;

$sommy = new SommyManager([
    'dialect'  => 'mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'testdb',
    'username' => 'appuser',
    'password' => 'secret',
    'charset'  => 'utf8mb4',
]);

// Optional: quick connectivity check
if (!$sommy->authenticate()) {
    throw new RuntimeException('MySQL connection failed');
}
```

PostgreSQL example:

```php
$sommy = new SommyManager([
    'dialect'  => 'pgsql',
    'host'     => '127.0.0.1',
    'port'     => 5432,
    'database' => 'testdb',
    'username' => 'postgres',
    'password' => 'secret',
]);
```

## Data Types
Use the factory in `Sommy\ORM\DataTypes` to declare columns:

- INTEGER: `DataTypes::INTEGER([...options])`
- BIGINT, SMALLINT: `DataTypes::BIGINT()`, `DataTypes::SMALLINT()`
- STRING/VARCHAR: `DataTypes::STRING($length = 255, [...])`
- TEXT: `DataTypes::TEXT([...])`
- BOOLEAN: `DataTypes::BOOLEAN([...])`
- DATE, DATETIME, TIME, TIMESTAMP
- FLOAT, DECIMAL($precision = 10, $scale = 0)
- JSON (falls back to TEXT where unsupported)
- UUID (native on PostgreSQL, `CHAR(36)` elsewhere)

Common column options:

- `allowNull` (bool): allow NULL values (default: true)
- `default` (mixed): default value
- `unique` (bool): unique constraint (column-level)
- `primaryKey` (bool): marks column as primary key
- `autoIncrement` (bool): auto-increment (dialect‑aware)

## Creating Tables
Use `QueryInterface` to create tables. Example `users` table:

```php
use Sommy\ORM\DataTypes;

$qi = $sommy->getQueryInterface();
$qi->createTable('users', [
    'id'   => DataTypes::INTEGER(['primaryKey' => true, 'autoIncrement' => true, 'allowNull' => false]),
    'name' => DataTypes::STRING(255, ['allowNull' => false]),
    'age'  => DataTypes::INTEGER(),
    'is_admin' => DataTypes::BOOLEAN(['default' => false]),
    'created_at' => DataTypes::DATETIME(['allowNull' => false]),
], ['ifNotExists' => true]);
```

Drop a table:

```php
$qi->dropTable('users', ['ifExists' => true]);
```

## Defining Models
Define a model with `SommyManager::define($name, $attributes, $options = [])`. This returns an instance of an anonymous subclass of `Model` that is bound to your table and schema.

```php
use Sommy\ORM\Model;
use Sommy\ORM\DataTypes;

$User = $sommy->define('User', [
    'id'   => DataTypes::INTEGER(['primaryKey' => true, 'autoIncrement' => true, 'allowNull' => false]),
    'name' => DataTypes::STRING(255, ['allowNull' => false]),
    'age'  => DataTypes::INTEGER(),
], [
    'tableName' => 'users', // optional; defaults to strtolower('User')
]);
```

Important: Many `Model` methods are static (Sequelize‑like). You can:

- Call via the class name string of the returned instance:

```php
$UserClass = $User::class; // get anonymous class name
$id = $UserClass::create(['name' => 'Alice', 'age' => 30]);
```

- Or create an instance and use instance helpers like `save()`:

```php
$u = new $UserClass(['name' => 'Bob', 'age' => 25]);
$u->save(); // INSERT, sets primary key if available
```

For convenience, calling static methods on the instance (e.g., `$User->findAll()`) also works in practice, but using the class name string as shown above is clearer.

## CRUD Examples

Create (static):

```php
$UserClass = $User::class;
$id = $UserClass::create(['name' => 'Alice', 'age' => 30]);
```

Create + save (instance):

```php
$u = new $UserClass(['name' => 'Bob']);
$u->age = 25;
$u->save(); // INSERT
```

Find:

```php
$all = $UserClass::findAll();
$admins = $UserClass::findAll(['is_admin' => 1], ['order' => ['id' => 'DESC']]);
$first = $UserClass::findOne(['id' => 1]);
```

Update:

```php
// Bulk update
$affected = $UserClass::update(['age' => 31], ['name' => 'Alice']);

// Instance update via save()
$alice = $UserClass::findOne(['name' => 'Alice']);
if ($alice) {
    $alice->age = 32;
    $alice->save();
}
```

Delete:

```php
// Bulk delete
$deleted = $UserClass::destroy(['id' => [3,4,5]]); // IN (...) support

// Instance delete
$u = $UserClass::findOne(['id' => 2]);
if ($u) { $u->delete(); }
```

## QueryInterface Reference
Available on `$sommy->getQueryInterface()`.

- select: `select(string $table, array $columns = ['*'], array $where = [], array $options = []) : array`
  - Where supports: `['col' => value, 'status' => ['a','b'], 'deleted_at' => null]`
  - Options: `order` (string or array), `limit` (int), `offset` (int)
- insert: `insert(string $table, array $data) : string|bool` (returns last insert id if available)
- update: `update(string $table, array $data, array $where) : int`
- delete: `delete(string $table, array $where) : int`
- createTable: `createTable(string $name, array $attributes, array $options = []) : bool`
- dropTable: `dropTable(string $name, array $options = []) : bool`

Identifier quoting and SQL types are dialect‑aware where it matters (e.g., MySQL backticks vs. PostgreSQL double quotes, SERIAL/BIGSERIAL, SQLite `INTEGER PRIMARY KEY`).

## CLI: Migrations
Sommy includes a tiny CLI in `bin/sommy` for simple time‑stamped migrations.

1) Initialize config (creates `sommy.config.php`):

```bash
php bin/sommy init:config
```

2) Create a migration file:

```bash
php bin/sommy migrate:create create_users
```

This generates `database/migrations/YYYMMDDHHMMSS_create_users.php` with a class like `Migration_YYYMMDDHHMMSS_create_users` containing `up()` and `down()`.

3) Edit your migration, e.g.:

```php
use Sommy\ORM\QueryInterface;

class Migration_20250101010101_create_users
{
    public function up(QueryInterface $qi): void
    {
        $qi->createTable('users', [
            'id'   => ['type' => 'INTEGER', 'primaryKey' => true, 'autoIncrement' => true, 'allowNull' => false],
            'name' => ['type' => 'VARCHAR', 'length' => 255, 'allowNull' => false],
        ], ['ifNotExists' => true]);
    }

    public function down(QueryInterface $qi): void
    {
        $qi->dropTable('users', ['ifExists' => true]);
    }
}
```

4) Apply pending migrations:

```bash
php bin/sommy migrate:up
```

5) Revert last migration:

```bash
php bin/sommy migrate:down
```

The CLI records applied migrations in a table `sommy_migrations`.

## Quick Start (End‑to‑End)

```php
require __DIR__ . '/vendor/autoload.php';

use Sommy\ORM\SommyManager;
use Sommy\ORM\DataTypes;

$sommy = new SommyManager([
    'dialect'  => 'sqlite',
    'database' => __DIR__ . '/database.sqlite',
]);

// Create a table (usually via migration)
$qi = $sommy->getQueryInterface();
$qi->createTable('users', [
    'id'   => DataTypes::INTEGER(['primaryKey' => true, 'autoIncrement' => true, 'allowNull' => false]),
    'name' => DataTypes::STRING(255, ['allowNull' => false]),
], ['ifNotExists' => true]);

// Define a model
$User = $sommy->define('User', [
    'id'   => DataTypes::INTEGER(['primaryKey' => true, 'autoIncrement' => true, 'allowNull' => false]),
    'name' => DataTypes::STRING(255, ['allowNull' => false]),
], ['tableName' => 'users']);

// Use the class name for static methods
$UserClass = $User::class;
$id = $UserClass::create(['name' => 'Alice']);

$all = $UserClass::findAll();
foreach ($all as $u) {
    echo $u->name . "\n";
}
```

## Design Notes
- Minimal abstraction: generated SQL is straightforward and debug‑friendly.
- Dialect‑aware DDL: common types and primary/auto‑increment behaviors map correctly for MySQL/MariaDB, PostgreSQL, SQLite.
- Anonymous model classes: `define()` returns an instance; use `$Model::class` to access static methods cleanly.

## Troubleshooting
- Ensure the PDO driver for your DB is installed and enabled.
- For MySQL/MariaDB, set a valid `charset` (defaults to `utf8mb4`).
- If `migrate:up` fails, check your migration for typos in column definitions (e.g., `allowNull`, `primaryKey`).
