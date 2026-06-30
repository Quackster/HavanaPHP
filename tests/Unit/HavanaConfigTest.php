<?php

namespace Tests\Unit;

use App\Services\HavanaConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HavanaConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('settings', function ($table): void {
            $table->string('setting', 50)->primary();
            $table->longText('value')->default('');
        });

        app(HavanaConfig::class)->reload();
    }

    public function test_defaults_are_available_and_seeded_into_settings_table(): void
    {
        $config = app(HavanaConfig::class);

        $this->assertSame('Habbo', $config->string('site.name'));
        $this->assertSame('12321', $config->string('loader.game.port'));
        $this->assertSame(12321, $config->integer('loader.game.port'));
        $this->assertSame('http://localhost:5000', $config->string('site.imaging.endpoint'));
        $this->assertSame(5000, $config->integer('site.imaging.endpoint.timeout'));
        $this->assertTrue($config->boolean('hotel.check.online'));

        $this->assertDatabaseHas('settings', [
            'setting' => 'site.name',
            'value' => 'Habbo',
        ]);
        $this->assertDatabaseHas('settings', [
            'setting' => 'loader.game.port',
            'value' => '12321',
        ]);
        $this->assertDatabaseHas('settings', [
            'setting' => 'site.imaging.endpoint',
            'value' => 'http://localhost:5000',
        ]);
        $this->assertDatabaseHas('settings', [
            'setting' => 'site.imaging.endpoint.timeout',
            'value' => '5000',
        ]);
    }

    public function test_database_settings_override_defaults_after_reload(): void
    {
        $config = app(HavanaConfig::class);

        $this->assertSame('Habbo', $config->string('site.name'));

        DB::table('settings')->where('setting', 'site.name')->update(['value' => 'Havana']);

        $this->assertSame('Habbo', $config->string('site.name'));

        $config->reload();

        $this->assertSame('Havana', $config->string('site.name'));
    }

    public function test_missing_keys_match_legacy_defaults_and_explicit_fallbacks(): void
    {
        $config = app(HavanaConfig::class);

        $this->assertSame('missing.key', $config->string('missing.key'));
        $this->assertSame('fallback', $config->string('missing.key', 'fallback'));
        $this->assertSame(0, $config->integer('missing.integer'));
        $this->assertSame(42, $config->integer('missing.integer', 42));
        $this->assertFalse($config->boolean('missing.boolean'));
    }

    public function test_boolean_values_match_legacy_true_values(): void
    {
        DB::table('settings')->insert([
            ['setting' => 'flag.true', 'value' => 'true'],
            ['setting' => 'flag.one', 'value' => '1'],
            ['setting' => 'flag.yes', 'value' => 'yes'],
            ['setting' => 'flag.false', 'value' => 'false'],
            ['setting' => 'flag.no', 'value' => 'no'],
        ]);

        $config = app(HavanaConfig::class);
        $config->reload();

        $this->assertTrue($config->boolean('flag.true'));
        $this->assertTrue($config->boolean('flag.one'));
        $this->assertTrue($config->boolean('flag.yes'));
        $this->assertFalse($config->boolean('flag.false'));
        $this->assertFalse($config->boolean('flag.no'));
    }
}
