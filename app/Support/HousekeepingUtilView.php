<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class HousekeepingUtilView
{
    public function getRoomName(int $roomId): string
    {
        return (string) (DB::table('rooms')->where('id', $roomId)->value('name') ?? 'ERROR');
    }
}
