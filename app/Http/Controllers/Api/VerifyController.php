<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VerifyController extends Controller
{
    public function get(string $code): Response
    {
        if ($code === '') {
            return response('error: INVALID');
        }

        $username = DB::table('users_statistics')
            ->join('users', 'users_statistics.user_id', '=', 'users.id')
            ->where('users_statistics.verify_code', $code)
            ->value('users.username');

        return is_string($username)
            ? response($username)
            : response('error: INVALID');
    }

    public function clear(string $code): Response
    {
        if ($code === '') {
            return response('error: INVALID');
        }

        DB::table('users_statistics')
            ->where('verify_code', $code)
            ->update(['verify_code' => null]);

        return response('SUCCESS');
    }
}
