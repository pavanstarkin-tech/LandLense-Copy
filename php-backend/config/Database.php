<?php
namespace LandLens\Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $connection = null;

    public static function getConnection(): ?PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = Config::getEnv('DB_HOST', Config::DB_HOST);
        $user = Config::getEnv('DB_USER', Config::DB_USER);
        $pass = Config::getEnv('DB_PASSWORD', Config::DB_PASS);
        $dbname = Config::getEnv('DB_NAME', Config::DB_NAME);
        $port = Config::getEnv('DB_PORT', Config::DB_PORT);

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$connection = new PDO($dsn, $user, $pass, $options);
            return self::$connection;
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            return null;
        }
    }
}
