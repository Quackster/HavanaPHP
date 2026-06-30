<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HotelStatus
{
    private const CACHE_KEY = 'havana.hotel_status';

    public function __construct(private readonly HavanaConfig $config) {}

    /** @return array{serverOnline: bool, usersOnline: int, formattedUsersOnline: string, visits: int} */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(30), fn (): array => $this->freshSnapshot());
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{serverOnline: bool, usersOnline: int, formattedUsersOnline: string, visits: int} */
    private function freshSnapshot(): array
    {
        $serverOnline = $this->serverOnline();
        $usersOnline = $serverOnline ? $this->playersOnline() : 0;

        return [
            'serverOnline' => $serverOnline,
            'usersOnline' => $usersOnline,
            'formattedUsersOnline' => number_format($usersOnline),
            'visits' => $this->lastVisits(),
        ];
    }

    private function serverOnline(): bool
    {
        if (! $this->config->boolean('hotel.check.online')) {
            return true;
        }

        $host = (string) config('havana.rcon.host', '127.0.0.1');
        $port = (int) config('havana.rcon.port', 12309);

        if ($host === '' || $port <= 0) {
            return false;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private function playersOnline(): int
    {
        try {
            if (! Schema::hasTable('settings')) {
                return 0;
            }

            return max(0, (int) DB::table('settings')
                ->where('setting', 'players.online')
                ->value('value'));
        } catch (QueryException) {
            return 0;
        }
    }

    private function lastVisits(): int
    {
        try {
            if (! Schema::hasTable('users')) {
                return 0;
            }

            return (int) DB::table('users')
                ->where('last_online', '>', now()->subDays(30))
                ->count();
        } catch (QueryException) {
            return 0;
        }
    }
}
