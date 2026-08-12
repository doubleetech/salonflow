<?php

/**
 * Database
 * Thin PDO singleton. Every model talks to the DB only through this class,
 * always via prepared statements — no raw string interpolation anywhere.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                ]);
            } catch (PDOException $e) {
                // Never leak DB credentials or raw exception details to the browser
                error_log('Database connection failed: ' . $e->getMessage());
                http_response_code(500);
                die('A system error occurred. Please try again later.');
            }
        }

        return self::$instance;
    }
}
