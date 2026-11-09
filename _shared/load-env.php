<?php

/**
 * Centralized .env loader for NHA FIMS.
 * Include this at the top of config.development.php.
 * Reads .env from the project root (one level above _shared/).
 */

if (!defined('FIMS_ENV_LOADED')) {
    define('FIMS_ENV_LOADED', true);

    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            // Strip surrounding quotes
            if (preg_match('/^(["\'])(.*)\\1$/s', $value, $m)) {
                $value = $m[2];
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => is_numeric($value) ? $value + 0 : $value,
        };
    }
}

if (!function_exists('dbConn')) {
    /**
     * Build a PHPMaker DB connection array using .env credentials.
     * Only the schema (and optionally the connection id) varies per module.
     */
    function dbConn(string $schema, string $id = null): array
    {
        return [
            'id'       => $id ?? $schema,
            'type'     => 'POSTGRESQL',
            'qs'       => '"',
            'qe'       => '"',
            'host'     => env('DB_HOST', 'localhost'),
            'port'     => env('DB_PORT', '5432'),
            'user'     => env('DB_USER', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'dbname'   => env('DB_NAME', 'fims'),
            'schema'   => $schema,
        ];
    }
}
