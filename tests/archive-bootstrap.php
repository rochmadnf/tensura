<?php

/**
 * Opt-in integration runner. Only disposable databases named tensura_archive_test_*
 * are accepted; connection settings never fall back to the application's .env.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

$driver = getenv('ARCHIVE_TEST_DRIVER');
$database = getenv('ARCHIVE_TEST_DATABASE');
if (!in_array($driver, ['mysql', 'pgsql'], true)
    || !is_string($database)
    || preg_match('/\Atensura_archive_test_[a-z0-9_]+\z/D', $database) !== 1) {
    throw new RuntimeException('Set ARCHIVE_TEST_DRIVER=mysql|pgsql and ARCHIVE_TEST_DATABASE=tensura_archive_test_* to a disposable database.');
}
if (is_file(dirname(__DIR__) . '/bootstrap/cache/config.php')
    || (getenv('APP_CONFIG_CACHE') !== false && is_file(getenv('APP_CONFIG_CACHE')))) {
    throw new RuntimeException('Remove the cached application configuration before running archive integration tests.');
}

$settings = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => dirname(__DIR__) . '/bootstrap/cache/config.php',
    'ARCHIVE_DATABASE_TESTS' => '1',
    'DB_CONNECTION' => $driver,
    'DB_DATABASE' => $database,
    'DB_URL' => '',
    'DB_HOST' => getenv('ARCHIVE_TEST_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('ARCHIVE_TEST_PORT') ?: ($driver === 'mysql' ? '3306' : '5432'),
    'DB_USERNAME' => getenv('ARCHIVE_TEST_USERNAME') ?: 'root',
    'DB_PASSWORD' => getenv('ARCHIVE_TEST_PASSWORD') ?: '',
    'DB_SOCKET' => getenv('ARCHIVE_TEST_SOCKET') ?: '',
    'DB_CHARSET' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
    'DB_COLLATION' => 'utf8mb4_unicode_ci',
];
foreach ($settings as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $_SERVER[$name] = $value;
}
