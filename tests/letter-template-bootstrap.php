<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

$database = getenv('LETTER_TEMPLATE_TEST_DATABASE');
if (!is_string($database) || preg_match('/\Atensura_test_letters_[a-z0-9_]+\z/D', $database) !== 1) {
    throw new RuntimeException('Set LETTER_TEMPLATE_TEST_DATABASE=tensura_test_letters_* to a disposable PostgreSQL database.');
}
if (is_file(dirname(__DIR__) . '/bootstrap/cache/config.php') || (getenv('APP_CONFIG_CACHE') !== false && is_file(getenv('APP_CONFIG_CACHE')))) {
    throw new RuntimeException('Remove cached configuration before running letter template concurrency tests.');
}
$settings = [
    'APP_ENV' => 'testing', 'LETTER_TEMPLATE_DATABASE_TESTS' => '1',
    'APP_CONFIG_CACHE' => dirname(__DIR__) . '/bootstrap/cache/config.php',
    'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $database, 'DB_URL' => '',
    'DB_HOST' => getenv('LETTER_TEMPLATE_TEST_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('LETTER_TEMPLATE_TEST_PORT') ?: '5432',
    'DB_USERNAME' => getenv('LETTER_TEMPLATE_TEST_USERNAME') ?: 'postgres',
    'DB_PASSWORD' => getenv('LETTER_TEMPLATE_TEST_PASSWORD') ?: '', 'DB_CHARSET' => 'utf8',
];
foreach ($settings as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $_SERVER[$name] = $value;
}
