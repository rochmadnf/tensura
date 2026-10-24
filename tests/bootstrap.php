<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

$testingEnvironment = Dotenv\Dotenv::createArrayBacked(dirname(__DIR__), '.env.testing')->safeLoad();
$testSetting = static function (string $name, string $default) use ($testingEnvironment): string {
    $override = getenv("TEST_{$name}");

    return $override !== false
        ? $override
        : ($testingEnvironment["TEST_{$name}"] ?? $testingEnvironment[$name] ?? $default);
};

$database = $testSetting('DB_DATABASE', 'tensura_test');
if (preg_match('/\Atensura_(?:archive_)?test(?:_[a-z0-9_]+)?\z/D', $database) !== 1) {
    throw new RuntimeException('TEST_DB_DATABASE must name a disposable tensura_test_* or tensura_archive_test_* database.');
}
if (is_file(dirname(__DIR__) . '/bootstrap/cache/config.php')
    || (getenv('APP_CONFIG_CACHE') !== false && is_file(getenv('APP_CONFIG_CACHE')))) {
    throw new RuntimeException('Remove the cached application configuration before running database tests.');
}

$settings = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => dirname(__DIR__) . '/bootstrap/cache/config.php',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => $database,
    'DB_URL' => '',
    'DB_HOST' => $testSetting('DB_HOST', '127.0.0.1'),
    'DB_PORT' => $testSetting('DB_PORT', '5432'),
    'DB_USERNAME' => $testSetting('DB_USERNAME', 'postgres'),
    'DB_PASSWORD' => $testSetting('DB_PASSWORD', ''),
    'DB_CHARSET' => 'utf8',
];
foreach ($settings as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $_SERVER[$name] = $value;
}
