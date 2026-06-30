<?php

namespace App\Services;

class LegacyPasswordHasher
{
    private const MEMORY_COST = 65536;

    private const TIME_COST = 2;

    private const THREADS = 1;

    public function make(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => self::MEMORY_COST,
            'time_cost' => self::TIME_COST,
            'threads' => self::THREADS,
        ]);
    }

    public function check(string $plain, string $hash): bool
    {
        if ($plain === '' || $hash === '') {
            return false;
        }

        return password_verify($plain, $hash);
    }
}
