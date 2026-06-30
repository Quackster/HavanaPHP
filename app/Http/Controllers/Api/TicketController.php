<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyPasswordHasher;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function get(Request $request, HavanaConfig $config, LegacyPasswordHasher $hasher): Response
    {
        $user = $this->authenticatedUser($request, $hasher);

        if (! $user) {
            return response('');
        }

        $ticket = $this->ticketFor($user, $config);

        $payload = [
            'host' => $config->string('loader.game.ip'),
            'site' => $config->string('site.path'),
            'musPort' => $config->string('loader.mus.port'),
            'shockwavePort' => $config->string('loader.game.port'),
            'shockwaveDcr' => $config->string('loader.dcr.http', $config->string('loader.dcr')),
            'shockwaveVariables' => str_replace('https://', 'http://', $config->string('loader.external.variables')),
            'shockwaveTexts' => str_replace('https://', 'http://', $config->string('loader.external.texts')),
            'flashPort' => $config->string('loader.flash.port'),
            'flashBase' => $config->string('loader.flash.base'),
            'flashSwf' => $config->string('loader.flash.swf'),
            'flashVariables' => $config->string('loader.flash.external.variables'),
            'flashTexts' => $config->string('loader.flash.external.texts'),
            'ssoTicket' => $ticket,
        ];

        return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
            ->header('Content-Type', 'text/json');
    }

    public function login(Request $request, HavanaConfig $config, LegacyPasswordHasher $hasher): Response
    {
        if (! $request->has(['username', 'password'])) {
            return response('ERROR No username or password supplied');
        }

        $user = $this->authenticatedUser($request, $hasher);

        if (! $user) {
            return response('ERROR Login invalid');
        }

        return response($this->ticketFor($user, $config));
    }

    private function authenticatedUser(Request $request, LegacyPasswordHasher $hasher): ?User
    {
        $username = (string) $request->query('username', '');
        $password = (string) $request->query('password', '');

        if ($username === '' || $password === '') {
            return null;
        }

        try {
            $user = User::query()->where('username', $username)->first();
        } catch (QueryException) {
            return null;
        }

        if (! $user || ! $hasher->check($password, (string) $user->password)) {
            return null;
        }

        return $user;
    }

    private function ticketFor(User $user, HavanaConfig $config): string
    {
        $ticket = (string) $user->sso_ticket;

        if ($config->boolean('reset.sso.after.login') || $ticket === '') {
            $ticket = (string) Str::uuid();
            $user->forceFill(['sso_ticket' => $ticket])->save();
        }

        return $ticket;
    }
}
