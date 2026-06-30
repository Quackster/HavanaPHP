<?php

namespace Tests\Unit;

use App\Services\LegacyPasswordHasher;
use Tests\TestCase;

class LegacyPasswordHasherTest extends TestCase
{
    public function test_hashes_match_legacy_spring_argon2_parameters(): void
    {
        $hash = app(LegacyPasswordHasher::class)->make('secret123');

        $info = password_get_info($hash);

        $this->assertSame(PASSWORD_ARGON2ID, $info['algo']);
        $this->assertSame(65536, $info['options']['memory_cost']);
        $this->assertSame(2, $info['options']['time_cost']);
        $this->assertSame(1, $info['options']['threads']);
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('secret123', $hash));
        $this->assertFalse(app(LegacyPasswordHasher::class)->check('wrong', $hash));
    }

    public function test_existing_legacy_argon2id_hashes_verify(): void
    {
        $legacyHash = '$argon2id$v=19$m=65536,t=2,p=1$pYzv16AI/wp36NjNfIYHbg$rfbfHoRuM0dcpqPkFPJDuaunTPOP+aKESHz5aBCAZ8E';

        $this->assertTrue(app(LegacyPasswordHasher::class)->check('secret123', $legacyHash));
        $this->assertFalse(app(LegacyPasswordHasher::class)->check('', $legacyHash));
        $this->assertFalse(app(LegacyPasswordHasher::class)->check('secret123', ''));
    }
}
