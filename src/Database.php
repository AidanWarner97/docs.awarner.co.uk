<?php
declare(strict_types=1);

// Thin PDO/SQLite wrapper with a single shared connection.
final class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            if (!is_dir(DATA_DIR)) {
                mkdir(DATA_DIR, 0775, true);
            }

            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON');

            self::$pdo = $pdo;
        }

        return self::$pdo;
    }
}
