<?php

namespace Tests\Unit;

use Tests\TestCase;

class RuntimeConfigurationTest extends TestCase
{
    public function test_env_example_uses_havana_runtime_defaults(): void
    {
        $env = $this->envExample();

        $this->assertSame('HavanaPHP', $env['APP_NAME']);
        $this->assertSame('mariadb', $env['DB_CONNECTION']);
        $this->assertSame('127.0.0.1', $env['DB_HOST']);
        $this->assertSame('3306', $env['DB_PORT']);
        $this->assertSame('havana', $env['DB_DATABASE']);
        $this->assertSame('havana', $env['DB_USERNAME']);
        $this->assertSame('goldfish', $env['DB_PASSWORD']);

        $this->assertSame('file', $env['SESSION_DRIVER']);
        $this->assertSame('120', $env['SESSION_LIFETIME']);
        $this->assertSame('false', $env['SESSION_ENCRYPT']);
        $this->assertSame('file', $env['CACHE_STORE']);
        $this->assertSame('sync', $env['QUEUE_CONNECTION']);
        $this->assertSame('log', $env['MAIL_MAILER']);

        $this->assertSame('/opt/git/HavanaPHP/resources/legacy', $env['HAVANA_BASE_PATH']);
        $this->assertSame('"${HAVANA_BASE_PATH}/www-tpl"', $env['HAVANA_TEMPLATE_PATH']);
        $this->assertSame('default', $env['HAVANA_TEMPLATE_NAME']);
        $this->assertSame('locale-en.ini', $env['HAVANA_LOCALE_FILE']);
        $this->assertSame('/opt/git/HavanaPHP/public', $env['HAVANA_PUBLIC_PATH']);
        $this->assertSame('allseeingeye/hk', $env['HAVANA_HOUSEKEEPING_PATH']);
        $this->assertSame('127.0.0.1', $env['HAVANA_RCON_HOST']);
        $this->assertSame('12309', $env['HAVANA_RCON_PORT']);
    }

    public function test_runtime_config_resolves_havana_env_values(): void
    {
        config([
            'database.default' => 'mariadb',
            'session.driver' => 'file',
            'session.lifetime' => 120,
            'session.encrypt' => false,
            'cache.default' => 'file',
            'queue.default' => 'sync',
            'mail.default' => 'log',
            'havana.base_path' => '/opt/git/HavanaPHP/resources/legacy',
            'havana.template_path' => '/opt/git/HavanaPHP/resources/legacy/www-tpl',
            'havana.template_name' => 'default',
            'havana.locale_file' => 'locale-en.ini',
            'havana.public_path' => '/opt/git/HavanaPHP/public',
            'havana.housekeeping_path' => 'allseeingeye/hk',
            'havana.rcon.host' => '127.0.0.1',
            'havana.rcon.port' => 12309,
        ]);

        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('file', config('session.driver'));
        $this->assertSame(120, config('session.lifetime'));
        $this->assertFalse(config('session.encrypt'));
        $this->assertSame('file', config('cache.default'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('log', config('mail.default'));
        $this->assertSame('/opt/git/HavanaPHP/resources/legacy', config('havana.base_path'));
        $this->assertSame('/opt/git/HavanaPHP/resources/legacy/www-tpl', config('havana.template_path'));
        $this->assertSame('default', config('havana.template_name'));
        $this->assertSame('locale-en.ini', config('havana.locale_file'));
        $this->assertSame('/opt/git/HavanaPHP/public', config('havana.public_path'));
        $this->assertSame('allseeingeye/hk', config('havana.housekeeping_path'));
        $this->assertSame('127.0.0.1', config('havana.rcon.host'));
        $this->assertSame(12309, config('havana.rcon.port'));
    }

    public function test_readme_documents_supported_local_runtime_shape(): void
    {
        $readme = (string) file_get_contents(base_path('README.md'));

        $this->assertStringContainsString('MariaDB or MySQL containing the existing Havana schema/data.', $readme);
        $this->assertStringContainsString('Do not run Laravel migrations against the live Havana database.', $readme);
        $this->assertStringContainsString('DB_CONNECTION=mariadb', $readme);
        $this->assertStringContainsString('Legacy templates are committed under `resources/legacy/www-tpl`.', $readme);
        $this->assertStringContainsString('Minerva listens on `http://localhost:5000` by default.', $readme);
        $this->assertStringContainsString('site.imaging.endpoint', $readme);
        $this->assertStringContainsString('SESSION_DRIVER=file', (string) file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString('CACHE_STORE=file', (string) file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString('QUEUE_CONNECTION=sync', (string) file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString('MAIL_MAILER=log', (string) file_get_contents(base_path('.env.example')));
        $this->assertFileExists(base_path('resources/legacy/figuredata/figuredata.xml'));
    }

    /** @return array<string, string> */
    private function envExample(): array
    {
        $values = [];

        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[$key] = $value;
        }

        return $values;
    }
}
