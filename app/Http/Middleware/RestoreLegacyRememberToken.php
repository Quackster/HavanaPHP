<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestoreLegacyRememberToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('authenticated')) {
            $this->restoreFromCookie($request);
        }

        if ($request->session()->get('authenticated') && $request->is('/', 'home', 'index')) {
            return redirect('/me');
        }

        return $next($request);
    }

    private function restoreFromCookie(Request $request): void
    {
        $token = (string) $request->cookies->get('remember_token', '');

        if (trim($token) === '') {
            return;
        }

        $user = User::query()
            ->where('remember_token', $token)
            ->first();

        if ($user instanceof User) {
            Auth::login($user);
            $request->session()->put('authenticated', true);
            $request->session()->put('captcha.invalid', false);
            $request->session()->put('user.id', $user->id);

            return;
        }

        Auth::logout();
        $request->session()->forget(['user.id', 'authenticated']);
        cookie()->queue(cookie()->forget('remember_token'));
    }
}
