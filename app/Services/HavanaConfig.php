<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HavanaConfig
{
    /** @var array<string, string>|null */
    private ?array $settings = null;

    public function string(string $key, ?string $default = null): string
    {
        return $this->all()[$key] ?? $default ?? $key;
    }

    public function getString(string $key): string
    {
        return $this->string($key);
    }

    public function integer(string $key, ?int $default = null): int
    {
        return (int) ($this->all()[$key] ?? $default ?? 0);
    }

    public function getInteger(string $key, ?int $default = null): int
    {
        return $this->integer($key, $default);
    }

    public function boolean(string $key): bool
    {
        $value = strtolower((string) ($this->all()[$key] ?? 'false'));

        return in_array($value, ['true', '1', 'yes'], true);
    }

    public function getBoolean(string $key): bool
    {
        return $this->boolean($key);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $settings = config('havana.settings_defaults', []);

        try {
            if (Schema::hasTable('settings')) {
                $stored = DB::table('settings')->pluck('value', 'setting');

                foreach ($settings as $key => $value) {
                    if (! $stored->has($key)) {
                        DB::table('settings')->insert([
                            'setting' => $key,
                            'value' => (string) $value,
                        ]);
                    }
                }

                foreach ($stored as $key => $value) {
                    $settings[(string) $key] = (string) $value;
                }
            }
        } catch (QueryException) {
            // Database access can be unavailable during CLI/bootstrap checks.
        }

        return $this->settings = $settings;
    }

    public function reload(): void
    {
        $this->settings = null;
    }
}
