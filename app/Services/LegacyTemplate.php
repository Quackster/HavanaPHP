<?php

namespace App\Services;

use App\Models\User;
use Twig\Environment;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigTest;

class LegacyTemplate
{
    private Environment $twig;

    public function __construct(
        private readonly HavanaConfig $config,
        private readonly LegacyLocale $locale,
        private readonly HotelStatus $hotelStatus,
    ) {
        $templateRoot = rtrim((string) config('havana.template_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.config('havana.template_name', 'default');

        $loader = new LegacyFilesystemLoader($templateRoot, $this->locale->all());

        $this->twig = new Environment($loader, [
            'autoescape' => false,
            'strict_variables' => false,
            'cache' => false,
        ]);
        $this->twig->getRuntime(EscaperRuntime::class)->setEscaper(
            'legacy_js',
            fn ($value, string $charset): string => $this->escapeLegacyJavaScript((string) $value),
        );

        $this->twig->addTest(new TwigTest('present', fn ($value): bool => $value !== null));
    }

    /** @param array<string, mixed> $context */
    public function render(string $view, array $context = []): string
    {
        return $this->twig->render($view.'.tpl', array_replace_recursive($this->baseContext(), $context));
    }

    /** @return array<string, mixed> */
    private function baseContext(): array
    {
        $request = request();
        $authenticated = (bool) $request->session()->get('authenticated', false);
        $alertMessage = (string) $request->session()->get('alertMessage', '');
        $playerDetails = $this->playerDetails();
        $hotelStatus = $this->hotelStatus->snapshot();

        return [
            'playerDetails' => $playerDetails,
            'locale' => $this->locale->all(),
            'site' => [
                'siteName' => $this->config->string('site.name'),
                'sitePath' => $this->config->string('site.path'),
                'staticContentPath' => $this->config->string('static.content.path'),
                'housekeepingPath' => config('havana.housekeeping_path'),
                'usersOnline' => $hotelStatus['usersOnline'],
                'formattedUsersOnline' => $hotelStatus['formattedUsersOnline'],
                'visits' => $hotelStatus['visits'],
                'serverOnline' => $hotelStatus['serverOnline'],
                'loaderGameIp' => $this->config->string('loader.game.ip'),
                'loaderGamePort' => $this->config->string('loader.game.port'),
                'loaderMusIp' => $this->config->string('loader.mus.ip'),
                'loaderMusPort' => $this->config->string('loader.mus.port'),
                'loaderDcr' => $this->config->string('loader.dcr'),
                'loaderVariables' => $this->config->string('loader.external.variables'),
                'loaderTexts' => $this->config->string('loader.external.texts'),
                'loaderFlashPort' => $this->config->string('loader.flash.port'),
                'loaderFlashBase' => $this->config->string('loader.flash.base'),
                'loaderFlashSwf' => $this->config->string('loader.flash.swf'),
                'loaderFlashTexts' => $this->config->string('loader.flash.external.texts'),
                'loaderFlashVariables' => $this->config->string('loader.flash.external.variables'),
                'loaderFlashBetaBase' => $this->config->string('loader.flash.beta.base', $this->config->string('loader.flash.base')),
                'loaderFlashBetaSwf' => $this->config->string('loader.flash.beta.swf', $this->config->string('loader.flash.swf')),
                'loaderFlashBetaTexts' => $this->config->string('loader.flash.beta.external.texts', $this->config->string('loader.flash.external.texts')),
                'loaderFlashBetaVariables' => $this->config->string('loader.flash.beta.external.variables', $this->config->string('loader.flash.external.variables')),
            ],
            'session' => [
                'loggedIn' => $authenticated,
                'currentPage' => $request->session()->get('page'),
            ],
            'alert' => [
                'hasAlert' => $alertMessage !== '',
                'message' => $alertMessage,
                'colour' => (string) $request->session()->get('alertColour', ''),
            ],
            'gameConfig' => $this->config,
        ];
    }

    private function playerDetails(): ?User
    {
        $request = request();

        if (! $request->session()->get('authenticated') && ! $request->session()->get('authenticatedHousekeeping')) {
            return null;
        }

        $userId = (int) $request->session()->get('user.id', 0);

        if ($userId <= 0) {
            return null;
        }

        $user = User::query()->find($userId);

        if ($user instanceof User) {
            return $user;
        }

        $request->session()->forget(['authenticated', 'authenticatedHousekeeping']);

        return null;
    }

    private function escapeLegacyJavaScript(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '"' => '\"',
            "'" => "\'",
            "\n" => '\n',
            "\r" => '\r',
            "\t" => '\t',
            "\f" => '\f',
            "\b" => '\b',
            '</' => '<\/',
        ]);
    }
}
