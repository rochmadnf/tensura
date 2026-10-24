<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class TestDatabaseBootstrapTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturePath = sys_get_temp_dir() . '/tensura-test-bootstrap-' . bin2hex(random_bytes(8));
        $files = new Filesystem();
        $files->ensureDirectoryExists($this->fixturePath . '/tests');
        $files->ensureDirectoryExists($this->fixturePath . '/vendor');
        $files->copy(dirname(__DIR__) . '/bootstrap.php', $this->fixturePath . '/tests/bootstrap.php');
        $files->put($this->fixturePath . '/vendor/autoload.php', '<?php require_once ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->fixturePath);

        parent::tearDown();
    }

    public function test_reads_testing_file_without_inheriting_application_connection(): void
    {
        file_put_contents($this->fixturePath . '/.env.testing', "DB_DATABASE=tensura_test_fixture\nDB_HOST=testing-host\nDB_PORT=5444\nDB_USERNAME=testing-user\nDB_PASSWORD=testing-password\n");

        $process = $this->runBootstrap();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame([
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'tensura_test_fixture',
            'DB_HOST' => 'testing-host',
            'DB_PORT' => '5444',
            'DB_USERNAME' => 'testing-user',
            'DB_PASSWORD' => 'testing-password',
            'DB_URL' => '',
        ], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_explicit_test_variables_override_file_including_empty_password(): void
    {
        file_put_contents($this->fixturePath . '/.env.testing', "DB_DATABASE=tensura_test_fixture\nDB_PORT=5444\nDB_PASSWORD=testing-password\n");

        $process = $this->runBootstrap([
            'TEST_DB_DATABASE' => 'tensura_test_override',
            'TEST_DB_PORT' => '5555',
            'TEST_DB_PASSWORD' => '',
        ]);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $settings = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('tensura_test_override', $settings['DB_DATABASE']);
        $this->assertSame('5555', $settings['DB_PORT']);
        $this->assertSame('', $settings['DB_PASSWORD']);
    }

    public function test_missing_testing_file_uses_isolated_defaults_instead_of_application_env(): void
    {
        file_put_contents($this->fixturePath . '/.env', "DB_DATABASE=application\nDB_PORT=9999\n");

        $process = $this->runBootstrap();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $settings = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('tensura_test', $settings['DB_DATABASE']);
        $this->assertSame('5432', $settings['DB_PORT']);
        $this->assertSame('127.0.0.1', $settings['DB_HOST']);
        $this->assertSame('', $settings['DB_URL']);
    }

    #[DataProvider('unsafeDatabaseSources')]
    public function test_rejects_application_database_before_boot(string $source): void
    {
        file_put_contents($this->fixturePath . '/.env.testing', 'DB_DATABASE=' . ($source === 'file' ? 'application' : 'tensura_test_fixture'));

        $process = $this->runBootstrap($source === 'environment' ? ['TEST_DB_DATABASE' => 'application'] : []);

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('must name a disposable', $process->getErrorOutput());
    }

    /** @return array<string, array{string}> */
    public static function unsafeDatabaseSources(): array
    {
        return ['testing file' => ['file'], 'explicit override' => ['environment']];
    }

    public function test_rejects_cached_application_configuration(): void
    {
        (new Filesystem())->ensureDirectoryExists($this->fixturePath . '/bootstrap/cache');
        file_put_contents($this->fixturePath . '/bootstrap/cache/config.php', '<?php return [];');

        $process = $this->runBootstrap();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Remove the cached application configuration', $process->getErrorOutput());
    }

    /** @param array<string, string> $overrides */
    private function runBootstrap(array $overrides = []): Process
    {
        $script = 'require ' . var_export($this->fixturePath . '/tests/bootstrap.php', true) . ';'
            . '$names = ["DB_CONNECTION", "DB_DATABASE", "DB_HOST", "DB_PORT", "DB_USERNAME", "DB_PASSWORD", "DB_URL"];'
            . 'echo json_encode(array_combine($names, array_map("getenv", $names)), JSON_THROW_ON_ERROR);';
        $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-r', $script], env: [
            'TEST_DB_DATABASE' => false,
            'TEST_DB_HOST' => false,
            'TEST_DB_PORT' => false,
            'TEST_DB_USERNAME' => false,
            'TEST_DB_PASSWORD' => false,
            'APP_CONFIG_CACHE' => false,
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => 'application',
            'DB_HOST' => 'application-host',
            'DB_PORT' => '9999',
            'DB_USERNAME' => 'application-user',
            'DB_PASSWORD' => 'application-password',
            'DB_URL' => 'application-url',
            ...$overrides,
        ]);
        $process->run();

        return $process;
    }
}
