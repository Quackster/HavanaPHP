<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyPasswordHasher;
use App\Services\LegacyTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RecoveryController extends Controller
{
    public function forgot(Request $request, HavanaConfig $config, LegacyTemplate $template): RedirectResponse|Response
    {
        if (! $config->boolean('email.smtp.enable')) {
            return redirect('/');
        }

        if ($request->isMethod('post')) {
            if ($request->has('actionList')) {
                return $this->forgottenName($request, $template);
            }

            if ($request->has('actionForgot')) {
                return $this->forgottenPassword($request, $template);
            }
        }

        $request->session()->put('page', 'recover');

        return response($template->render('account/email/account_forgot'));
    }

    public function recovery(
        Request $request,
        HavanaConfig $config,
        LegacyTemplate $template,
        LegacyPasswordHasher $hasher,
    ): RedirectResponse|Response {
        if (! $config->boolean('email.smtp.enable')) {
            return redirect('/');
        }

        $userId = (int) $request->input('user_id', $request->query('id', 0));
        $recoveryCode = (string) $request->input('recovery_code', $request->query('code', ''));

        if ($userId <= 0 || $recoveryCode === '' || ! $this->recoveryExists($userId, $recoveryCode)) {
            $request->session()->put('alertMessage', 'The recovery code was invalid');
            $request->session()->put('alertColour', 'red');

            return $this->renderRecovery($request, $template);
        }

        if ($request->isMethod('post') && $request->has(['password', 'confirmpassword'])) {
            $password = (string) $request->input('password', '');
            $confirm = (string) $request->input('confirmpassword', '');

            if ($password !== $confirm) {
                $request->session()->put('alertMessage', "The passwords don't match");
                $request->session()->put('alertColour', 'red');
            } elseif (strlen($confirm) < 6) {
                $request->session()->put('alertMessage', 'Password is too short, 6 characters minimum');
                $request->session()->put('alertColour', 'red');
            } else {
                User::query()->whereKey($userId)->update(['password' => $hasher->make($confirm)]);
                DB::table('users_statistics')->where('user_id', $userId)->update([
                    'forgot_password_code' => null,
                    'forgot_recovery_requested_time' => null,
                ]);

                $request->session()->put('alertMessage', 'Your password has been changed successfully.');
                $request->session()->put('alertColour', 'green');
            }
        }

        return $this->renderRecovery($request, $template, [
            'recoveryCode' => $recoveryCode,
            'userId' => $userId,
        ]);
    }

    public function activate(Request $request, HavanaConfig $config, LegacyTemplate $template): RedirectResponse|Response
    {
        if (! $config->boolean('email.smtp.enable')) {
            return redirect('/');
        }

        $userId = (int) $request->query('id', 0);
        $activationCode = (string) $request->query('code', '');
        $success = false;

        if ($userId > 0 && $activationCode !== '') {
            $updated = DB::table('users_statistics')
                ->where('user_id', $userId)
                ->where('activation_code', $activationCode)
                ->update(['activation_code' => null]);

            $success = $updated > 0;
        }

        return response($template->render('account/email/account_activated', [
            'verifySuccess' => $success,
        ]));
    }

    private function forgottenName(Request $request, LegacyTemplate $template): Response
    {
        $email = (string) $request->input('ownerEmailAddress', '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! User::query()->where('email', $email)->exists()) {
            return response($template->render('account/email/account_forgot', [
                'invalidForgetName' => true,
            ]));
        }

        return response($template->render('account/email/sent'));
    }

    private function forgottenPassword(Request $request, LegacyTemplate $template): Response
    {
        $username = (string) $request->input('forgottenpw-username', '');
        $email = (string) $request->input('forgottenpw-email', '');

        if ($username === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response($template->render('account/email/account_forgot', [
                'invalidForgetPassword' => true,
            ]));
        }

        $user = User::query()->where('username', $username)->where('email', $email)->first();

        if (! $user) {
            return response($template->render('account/email/account_forgot', [
                'invalidForgetPassword' => true,
            ]));
        }

        $recoveryCode = (string) Str::uuid();

        DB::table('users_statistics')->where('user_id', $user->id)->update([
            'forgot_password_code' => $recoveryCode,
            'forgot_recovery_requested_time' => now()->timestamp,
        ]);

        try {
            Mail::html($template->render('account/email/email_recovery', [
                'playerId' => $user->id,
                'playerName' => $user->username,
                'recoveryCode' => $recoveryCode,
            ]), function ($message) use ($email): void {
                $message->to($email)->subject('Password recovery at Classic Habbo');
            });
        } catch (\Throwable) {
            // Preserve the original UX: recovery state is recorded even if mail delivery fails.
        }

        return response($template->render('account/email/sent'));
    }

    private function recoveryExists(int $userId, string $recoveryCode): bool
    {
        try {
            return DB::table('users_statistics')
                ->where('user_id', $userId)
                ->where('forgot_password_code', $recoveryCode)
                ->exists();
        } catch (QueryException) {
            return false;
        }
    }

    /** @param array<string, mixed> $context */
    private function renderRecovery(Request $request, LegacyTemplate $template, array $context = []): Response
    {
        $response = response($template->render('account/email/account_recovery', $context));

        $request->session()->forget(['alertMessage', 'alertColour']);

        return $response;
    }
}
