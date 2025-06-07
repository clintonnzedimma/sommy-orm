<?php
// Example Usage
require 'vendor/autoload.php';

use Sommy\ORM\SommyManager;
use Sommy\ORM\DataTypes;

$orm = new SommyManager([
    'dialect'  => 'mysql',
    'host'     => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'database' => 'testdb',
    'storage'  => __DIR__ . '/db.sqlite',
]);

$User = $orm->define('User', [
    'id'    => DataTypes::INTEGER(),
    'name'  => DataTypes::STRING(100),
    'email' => DataTypes::STRING(150),
], [
    'tableName' => 'users'
]);

$orm->getQueryInterface()->createTable('users', $User::$attributes);

$user = new $User;
$user->name  = 'Clinton';
$user->email = 'clinton@example.com';
$user->save();

$all = $User::findAll(['email' => 'clinton@example.com']);
