<?php

namespace Tests\Unit;

use App\Services\HavanaConfig;
use App\Services\HotelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HotelStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('settings', function ($table): void {
            $table->string('setting', 50)->primary();
            $table->longText('value')->default('');
        });

        Schema::create('users', function ($table): void {
            $table->increments('id');
            $table->timestamp('last_online')->nullable();
        });

        app(HavanaConfig::class)->reload();
        app(HotelStatus::class)->clearCache();
    }

    public function test_disabled_online_check_reports_online_and_reads_counts(): void
    {
        DB::table('settings')->insert([
            ['setting' => 'hotel.check.online', 'value' => 'false'],
            ['setting' => 'players.online', 'value' => '1234'],
        ]);
        DB::table('users')->insert([
            ['last_online' => now()->subDays(2)],
            ['last_online' => now()->subDays(31)],
        ]);
        app(HavanaConfig::class)->reload();
        app(HotelStatus::class)->clearCache();

        $status = app(HotelStatus::class)->snapshot();

        $this->assertTrue($status['serverOnline']);
        $this->assertSame(1234, $status['usersOnline']);
        $this->assertSame('1,234', $status['formattedUsersOnline']);
        $this->assertSame(1, $status['visits']);
    }

    public function test_unreachable_checked_server_reports_offline_and_zero_users(): void
    {
        config([
            'havana.rcon.host' => '127.0.0.1',
            'havana.rcon.port' => 1,
        ]);
        DB::table('settings')->insert([
            ['setting' => 'hotel.check.online', 'value' => 'true'],
            ['setting' => 'players.online', 'value' => '99'],
        ]);
        app(HavanaConfig::class)->reload();
        app(HotelStatus::class)->clearCache();

        $status = app(HotelStatus::class)->snapshot();

        $this->assertFalse($status['serverOnline']);
        $this->assertSame(0, $status['usersOnline']);
        $this->assertSame('0', $status['formattedUsersOnline']);
    }
}
