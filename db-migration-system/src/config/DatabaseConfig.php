<?php

namespace DbMigration\Config;

class DatabaseConfig {
    private static $config = [
        'host' => 'localhost',
        'dbname' => 'grocery_expenses_db',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ];

    public static function getConfig() {
        return self::$config;
    }
}
