<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CaptchaGenerator;
use App\Services\HavanaConfig;
use App\Services\LegacyFigureValidator;
use App\Services\LegacyPasswordHasher;
use App\Services\LegacyTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    private const DEFAULT_FEMALE_FIGURE = 'hd-600-1.ch-630-66.lg-695-82.sh-730-68.hr-515-45';

    private const DEFAULT_MALE_FIGURE = 'hd-180-1.hr-100-61.ch-210-66.lg-270-82.sh-290-80';

    public function register(
        Request $request,
        HavanaConfig $config,
        LegacyTemplate $template,
        LegacyPasswordHasher $hasher,
    ): RedirectResponse|Response {
        if (Auth::check()) {
            return redirect('/me');
        }

        if ($this->hasReachedConnectionLimit($request, $config)) {
            $request->session()->put('alertMessage', 'You already have enough accounts registered');

            return redirect('/');
        }

        if ($config->boolean('registration.disabled')) {
            return response($template->render('register_disabled'));
        }

        if ($request->query('referral') !== null && (int) $request->query('referral') > 0) {
            $request->session()->put('referral', (int) $request->query('referral'));
        }

        if ($request->isMethod('post') && count($request->request->all()) > 3) {
            return $this->handlePost($request, $template, $hasher);
        }

        return response($template->render('register', $this->registerContext($request)));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['referral', 'captcha.invalid']);

        return redirect('/');
    }

    public function captcha(Request $request, CaptchaGenerator $captcha): Response
    {
        $text = $captcha->text(7);
        $request->session()->put('captcha-text', $text);

        return response($captcha->png($text), 200)->header('Content-Type', 'image/png');
    }

    /** @return array<string, mixed> */
    private function registerContext(Request $request): array
    {
        $context = [
            'randomNum' => random_int(0, 9999),
            'randomFemaleFigure1' => self::DEFAULT_FEMALE_FIGURE,
            'randomFemaleFigure2' => self::DEFAULT_FEMALE_FIGURE,
            'randomFemaleFigure3' => self::DEFAULT_FEMALE_FIGURE,
            'randomMaleFigure1' => self::DEFAULT_MALE_FIGURE,
            'randomMaleFigure2' => self::DEFAULT_MALE_FIGURE,
            'randomMaleFigure3' => self::DEFAULT_MALE_FIGURE,
            'referral' => (int) $request->session()->get('referral', 0),
        ];

        if ($request->session()->get('captcha.invalid')) {
            $context['registerCaptchaInvalid'] = true;
        }

        if ($request->session()->get('email.invalid')) {
            $context['registerEmailInvalid'] = true;
        }

        foreach (['registerUsername', 'registerFigure', 'registerGender', 'registerEmail', 'registerDay', 'registerMonth', 'registerYear'] as $key) {
            if ($request->session()->has($key)) {
                $context[$key] = $request->session()->get($key);
            }
        }

        if ($request->session()->has('registerPassword')) {
            $context['registerShowPassword'] = str_repeat('*', strlen((string) $request->session()->get('registerPassword')));
        }

        return $context;
    }

    private function handlePost(
        Request $request,
        LegacyTemplate $template,
        LegacyPasswordHasher $hasher,
        ?LegacyFigureValidator $figureValidator = null,
    ): RedirectResponse|Response {
        foreach (['bean.avatarName', 'bean.captchaResponse', 'retypedPassword', 'bean.email'] as $field) {
            if ($this->hasLegacyInput($request, $field) && trim((string) $this->legacyInput($request, $field)) === '') {
                $request->session()->put('captcha.invalid', false);

                return redirect('/register?errorCode=blank_fields');
            }
        }

        $username = strip_tags((string) ($this->legacyInput($request, 'bean.avatarName') ?? $request->session()->get('registerUsername', '')));
        $email = strip_tags((string) ($this->legacyInput($request, 'bean.email') ?? $request->session()->get('registerEmail', '')));
        $password = strip_tags((string) $request->input('retypedPassword', $request->session()->get('registerPassword', '')));
        $day = strip_tags((string) $request->input('bean.day', ''));
        $month = strip_tags((string) $request->input('bean.month', ''));
        $year = strip_tags((string) $request->input('bean.year', ''));
        [$figure, $gender] = $this->figureAndGender($request);

        $request->session()->put([
            'registerUsername' => $username,
            'registerPassword' => $password,
            'registerFigure' => $figure,
            'registerGender' => $gender,
            'registerEmail' => $email,
            'registerDay' => $day,
            'registerMonth' => $month,
            'registerYear' => $year,
        ]);

        if (count($request->request->all()) > 10) {
            $figureValidator ??= app(LegacyFigureValidator::class);

            if (! $figureValidator->validate($figure, $gender, false)) {
                return redirect('/register?error=bad_look');
            }

            if (! $this->isValidName($username)) {
                return redirect('/register?error=bad_username');
            }

            if (! $this->isValidEmail($email)) {
                $request->session()->put('email.invalid', true);

                return redirect('/register?error=bad_email');
            }
        }

        $captchaResponse = strip_tags((string) ($this->legacyInput($request, 'bean.captchaResponse') ?? ''));

        if ($captchaResponse !== (string) $request->session()->get('captcha-text')) {
            $request->session()->put('captcha.invalid', true);

            return redirect('/register?error=bad_captcha');
        }

        if ($this->hasLegacyInput($request, 'bean.email')) {
            $email = strip_tags((string) $this->legacyInput($request, 'bean.email'));
            $request->session()->put('registerEmail', $email);
        }

        if (! $this->isValidEmail((string) $request->session()->get('registerEmail', $email))) {
            $request->session()->put('email.invalid', true);

            return redirect('/register?error=bad_email');
        }

        $email = (string) $request->session()->get('registerEmail', $email);

        try {
            $user = DB::transaction(function () use ($username, $password, $figure, $gender, $email, $hasher) {
                $user = User::query()->create([
                    'username' => $username,
                    'password' => $hasher->make($password),
                    'figure' => $figure,
                    'sex' => $gender,
                    'pool_figure' => '',
                    'sso_ticket' => '',
                    'email' => $email,
                ]);

                DB::table('users_statistics')->insert([
                    'user_id' => $user->id,
                    'activation_code' => (string) Str::uuid(),
                ]);

                return $user;
            });
        } catch (QueryException) {
            return response($template->render('register', array_merge(
                $this->registerContext($request),
                ['registerCaptchaInvalid' => false, 'registerEmailInvalid' => false]
            )), 500);
        }

        if ((int) $request->session()->get('referral', 0) > 0) {
            try {
                DB::table('users_referred')->insert([
                    'user_id' => (int) $request->session()->get('referral'),
                    'referred_id' => $user->id,
                ]);
            } catch (QueryException) {
                // Referral persistence should not block account creation.
            }
        }

        $request->session()->forget(['referral', 'captcha.invalid']);
        $request->session()->put('user.id', $user->id);
        $request->session()->put('authenticated', true);
        Auth::login($user);
        $this->logIpAddress($user->id, $request->ip() ?? '');

        return redirect('/welcome');
    }

    private function hasReachedConnectionLimit(Request $request, HavanaConfig $config): bool
    {
        $maxConnections = $config->integer('max.connections.per.ip');

        if ($maxConnections <= 0) {
            return false;
        }

        $ipAddress = $request->ip();

        if ($ipAddress !== null && $this->countIpAddress($ipAddress) >= $maxConnections) {
            return true;
        }

        $machineId = (string) $request->cookies->get('SECURITY_KEY', '');

        return $machineId !== '' && $this->countMachineId('#'.$machineId) >= $maxConnections;
    }

    private function countIpAddress(string $ipAddress): int
    {
        try {
            return (int) DB::table('users_ip_logs')
                ->where('ip_address', $ipAddress)
                ->distinct('user_id')
                ->count('user_id');
        } catch (QueryException) {
            return 0;
        }
    }

    private function countMachineId(string $machineId): int
    {
        try {
            return User::query()->where('machine_id', $machineId)->count();
        } catch (QueryException) {
            return 0;
        }
    }

    private function logIpAddress(int $userId, string $ipAddress): void
    {
        if ($ipAddress === '') {
            return;
        }

        try {
            $latestIp = DB::table('users_ip_logs')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->value('ip_address');

            if ($latestIp !== $ipAddress) {
                DB::table('users_ip_logs')->insert([
                    'user_id' => $userId,
                    'ip_address' => $ipAddress,
                    'created_at' => now(),
                ]);
            }
        } catch (QueryException) {
            // Legacy compatibility should not make registration fail if IP logging is unavailable.
        }
    }

    /** @return array{0: string, 1: string} */
    private function figureAndGender(Request $request): array
    {
        if ($request->filled('randomFigure')) {
            $value = strip_tags((string) $request->input('randomFigure'));
            $gender = substr($value, 0, 1) ?: 'M';
            $figure = substr($value, 2) ?: self::DEFAULT_MALE_FIGURE;

            return [$figure, strtoupper($gender)];
        }

        $gender = strtoupper(strip_tags((string) ($this->legacyInput($request, 'bean.gender') ?? 'M')));
        $figure = strip_tags((string) ($this->legacyInput($request, 'bean.figure') ?? ($gender === 'F' ? self::DEFAULT_FEMALE_FIGURE : self::DEFAULT_MALE_FIGURE)));

        return [$figure, $gender === 'F' ? 'F' : 'M'];
    }

    private function hasLegacyInput(Request $request, string $key): bool
    {
        $input = $request->request->all();
        $underscoreKey = str_replace('.', '_', $key);

        return array_key_exists($key, $input)
            || array_key_exists($underscoreKey, $input)
            || $request->has($key)
            || $request->has($underscoreKey);
    }

    private function legacyInput(Request $request, string $key): mixed
    {
        $input = $request->request->all();
        $underscoreKey = str_replace('.', '_', $key);

        if (array_key_exists($key, $input)) {
            return $input[$key];
        }

        if (array_key_exists($underscoreKey, $input)) {
            return $input[$underscoreKey];
        }

        return $request->input($key, $request->input($underscoreKey));
    }

    private function isValidName(string $username): bool
    {
        if ($username === '' || strlen($username) > 24) {
            return false;
        }

        if (! preg_match('/^[a-z0-9\-+=?!@:.,$]+$/i', $username)) {
            return false;
        }

        $lower = strtolower($username);
        foreach (['admin', 'mod', 'staff', 'moderator', 'vip'] as $reserved) {
            if ($lower === $reserved) {
                return false;
            }
        }

        foreach (['admin-', 'admin=', 'mod-', 'mod=', 'bot-', 'bot=', 'vip-', 'vip='] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return false;
            }
        }

        try {
            return ! User::query()->where('username', $username)->exists();
        } catch (QueryException) {
            return false;
        }
    }

    private function isValidEmail(string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            return ! User::query()->where('email', $email)->exists();
        } catch (QueryException) {
            return false;
        }
    }
}
