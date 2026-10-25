<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class LetterTemplateBootstrapTest extends TestCase
{
    #[DataProvider('unsafeDatabases')]
    public function test_runner_rejects_databases_outside_its_disposable_namespace(string $database): void
    {
        $process = new Process([PHP_BINARY, '-r', 'require ' . var_export(dirname(__DIR__) . '/letter-template-bootstrap.php', true) . ';'], null, ['LETTER_TEMPLATE_TEST_DATABASE' => $database]);
        $process->run();
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('disposable PostgreSQL database', $process->getErrorOutput() . $process->getOutput());
    }

    public static function unsafeDatabases(): array
    {
        return [[''], ['tensura'], ['tensura_test'], ['tensura_archive_test_stage7'], ['tensura_test_letters_../live']];
    }

    public function test_explicit_runner_settings_override_ambient_application_credentials(): void
    {
        $code = 'require ' . var_export(dirname(__DIR__) . '/letter-template-bootstrap.php', true) . '; echo json_encode([getenv("DB_CONNECTION"), getenv("DB_DATABASE"), getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_URL"), getenv("LETTER_TEMPLATE_DATABASE_TESTS")]);';
        $process = new Process([PHP_BINARY, '-r', $code], null, [
            'LETTER_TEMPLATE_TEST_DATABASE' => 'tensura_test_letters_fixture', 'LETTER_TEMPLATE_TEST_HOST' => 'isolated-host', 'LETTER_TEMPLATE_TEST_PORT' => '5544',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => 'live', 'DB_URL' => 'application-url',
        ]);
        $process->mustRun();
        $this->assertSame(['pgsql', 'tensura_test_letters_fixture', 'isolated-host', '5544', '', '1'], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
    }
}
