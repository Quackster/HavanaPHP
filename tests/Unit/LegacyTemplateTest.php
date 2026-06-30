<?php

namespace Tests\Unit;

use App\Services\LegacyLocale;
use App\Services\LegacyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyTemplateTest extends TestCase
{
    use RefreshDatabase;

    private string $templateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateRoot = storage_path('framework/testing/legacy-template-'.uniqid());

        File::makeDirectory($this->templateRoot.'/default/account/email/base', 0755, true);
        File::makeDirectory($this->templateRoot.'/default/base', 0755, true);

        Schema::create('users', function ($table): void {
            $table->increments('id');
            $table->string('username');
            $table->text('password')->default('');
            $table->string('figure')->default('');
            $table->char('sex', 1)->default('M');
            $table->string('email')->default('');
            $table->integer('rank')->default(1);
            $table->bigInteger('club_expiration')->default(0);
            $table->boolean('has_flash_warning')->default(true);
            $table->timestamps();
        });

        File::put($this->templateRoot.'/locale-test.ini', "welcome=Welcome back\nsite.key=Site text\n");

        config([
            'havana.template_path' => $this->templateRoot,
            'havana.template_name' => 'default',
            'havana.locale_file' => 'locale-test.ini',
            'havana.housekeeping_path' => 'allseeingeye/hk',
            'havana.settings_defaults' => [
                'site.name' => 'Habbo Test',
                'site.path' => 'http://havana.test',
                'static.content.path' => 'http://static.test',
                'loader.game.ip' => '127.0.0.1',
                'loader.game.port' => '30000',
                'loader.mus.ip' => '127.0.0.1',
                'loader.mus.port' => '30001',
                'loader.dcr' => 'http://static.test/habbo.dcr?',
                'loader.external.variables' => 'http://static.test/external_variables.txt?',
                'loader.external.texts' => 'http://static.test/external_texts.txt?',
                'loader.flash.port' => '30002',
                'loader.flash.base' => 'http://static.test/gordon/',
                'loader.flash.swf' => 'http://static.test/gordon/Habbo.swf',
                'loader.flash.external.texts' => 'http://static.test/flash_texts.txt',
                'loader.flash.external.variables' => 'http://static.test/flash_variables.txt',
            ],
        ]);

        $session = app('session.store');
        $session->start();
        request()->setLaravelSession($session);
        $this->forgetTemplateServices();
    }

    protected function tearDown(): void
    {
        if (isset($this->templateRoot) && is_dir($this->templateRoot)) {
            File::deleteDirectory($this->templateRoot);
        }

        parent::tearDown();
    }

    public function test_template_renderer_injects_legacy_base_context_and_locale(): void
    {
        File::put($this->templateRoot.'/default/context.tpl', implode("\n", [
            '{{ site.siteName }}',
            '{{ site.sitePath }}',
            '{{ site.staticContentPath }}',
            '{{ site.housekeepingPath }}',
            '{{ session.loggedIn ? "logged-in" : "guest" }}',
            '{{ session.currentPage }}',
            '{{ alert.hasAlert ? alert.message ~ ":" ~ alert.colour : "no-alert" }}',
            '{{ locale.welcome }}',
            '{{ gameConfig.getString("site.name") }}',
            '{{ playerDetails.getName() }}',
        ]));

        $userId = $this->createUser('TemplateUser');

        request()->session()->put('authenticated', true);
        request()->session()->put('user.id', $userId);
        request()->session()->put('page', 'me');
        request()->session()->put('alertMessage', 'Saved');
        request()->session()->put('alertColour', 'green');

        $rendered = app(LegacyTemplate::class)->render('context');

        $this->assertStringContainsString('Habbo Test', $rendered);
        $this->assertStringContainsString('http://havana.test', $rendered);
        $this->assertStringContainsString('http://static.test', $rendered);
        $this->assertStringContainsString('allseeingeye/hk', $rendered);
        $this->assertStringContainsString('logged-in', $rendered);
        $this->assertStringContainsString('me', $rendered);
        $this->assertStringContainsString('Saved:green', $rendered);
        $this->assertStringContainsString('Welcome back', $rendered);
        $this->assertStringContainsString('TemplateUser', $rendered);
    }

    public function test_template_renderer_binds_housekeeping_player_details_from_legacy_session(): void
    {
        File::put($this->templateRoot.'/default/housekeeping-user.tpl', '{{ playerDetails.getName() }}:{{ playerDetails.rank }}');

        $userId = $this->createUser('Housekeeper', 7);

        request()->session()->put('authenticatedHousekeeping', true);
        request()->session()->put('user.id', $userId);

        $rendered = app(LegacyTemplate::class)->render('housekeeping-user');

        $this->assertSame('Housekeeper:7', $rendered);
    }

    public function test_template_renderer_exposes_legacy_rank_object_for_header_permissions(): void
    {
        File::put($this->templateRoot.'/default/housekeeping-link.tpl', '{% if playerDetails.getRank().getRankId() >= 6 %}housekeeping{% else %}hidden{% endif %}');

        $userId = $this->createUser('ManagerUser', 6);

        request()->session()->put('authenticated', true);
        request()->session()->put('user.id', $userId);

        $rendered = app(LegacyTemplate::class)->render('housekeeping-link');

        $this->assertSame('housekeeping', $rendered);
    }

    public function test_template_renderer_clears_stale_legacy_authenticated_session(): void
    {
        File::put($this->templateRoot.'/default/stale-user.tpl', '{{ playerDetails is empty ? "missing" : "present" }}');

        request()->session()->put('authenticated', true);
        request()->session()->put('authenticatedHousekeeping', true);
        request()->session()->put('user.id', 404);

        $rendered = app(LegacyTemplate::class)->render('stale-user');

        $this->assertSame('missing', $rendered);
        $this->assertFalse(request()->session()->has('authenticated'));
        $this->assertFalse(request()->session()->has('authenticatedHousekeeping'));
    }

    public function test_legacy_loader_normalizes_pebble_expressions(): void
    {
        File::put($this->templateRoot.'/default/compat.tpl', implode("\n", [
            '{% if page equals "home" %}equals-ok{% endif %}',
            '{{ items.size() }}',
            "{% set id = (prefix) + ('_') + (suffix) %}{{ id }}",
            "{% set id = (badgeData.getKey()) + ('_') +  (badge) %}{{ id }}",
            '{% if ("missingFlag" is not present) %}missing-ok{% endif %}',
            '{% if ("presentFlag" is present) %}present-ok{% endif %}',
        ]));

        $rendered = app(LegacyTemplate::class)->render('compat', [
            'page' => 'home',
            'items' => ['a', 'b', 'c'],
            'presentFlag' => true,
            'prefix' => 'left',
            'suffix' => 'right',
            'badgeData' => new class
            {
                public function getKey(): string
                {
                    return 'badge';
                }
            },
            'badge' => 'ACH',
        ]);

        $this->assertStringContainsString('equals-ok', $rendered);
        $this->assertStringContainsString('3', $rendered);
        $this->assertStringContainsString('left_right', $rendered);
        $this->assertStringContainsString('badge_ACH', $rendered);
        $this->assertStringContainsString('missing-ok', $rendered);
        $this->assertStringContainsString('present-ok', $rendered);
    }

    public function test_legacy_loader_resolves_parent_and_email_base_includes(): void
    {
        File::put($this->templateRoot.'/default/base/header.tpl', 'header:{{ title }}');
        File::put($this->templateRoot.'/default/account/email/base/email_header.tpl', 'email-header');
        File::put($this->templateRoot.'/default/account/email/message.tpl', implode("\n", [
            '{% include "../../base/header.tpl" %}',
            '{% include "base/email_header.tpl" %}',
        ]));

        $rendered = app(LegacyTemplate::class)->render('account/email/message', [
            'title' => 'Hello',
        ]);

        $this->assertStringContainsString('header:Hello', $rendered);
        $this->assertStringContainsString('email-header', $rendered);
    }

    public function test_missing_locale_file_returns_empty_locale_context(): void
    {
        config(['havana.locale_file' => 'missing.ini']);
        $this->forgetTemplateServices();

        File::put($this->templateRoot.'/default/missing-locale.tpl', '{{ locale.missing|default("empty") }}');

        $this->assertSame('empty', app(LegacyTemplate::class)->render('missing-locale'));
        $this->assertSame([], app(LegacyLocale::class)->all());
    }

    public function test_legacy_locale_decodes_java_properties_escapes(): void
    {
        File::put($this->templateRoot.'/locale-test.ini', implode("\n", [
            'colon=Email\:',
            'equals=Characters -\=?!@\:.',
            'newline=First\nSecond',
            'unicode=Habbo \u2605',
            'quote=class\=\\"new-button\\"',
            'html=<div\\>Don\\\'t</div\\>',
        ]));
        $this->forgetTemplateServices();

        $locale = app(LegacyLocale::class)->all();

        $this->assertSame('Email:', $locale['colon']);
        $this->assertSame('Characters -=?!@:.', $locale['equals']);
        $this->assertSame("First\nSecond", $locale['newline']);
        $this->assertSame('Habbo ★', $locale['unicode']);
        $this->assertSame('class="new-button"', $locale['quote']);
        $this->assertSame("<div>Don't</div>", $locale['html']);
    }

    public function test_legacy_loader_inlines_locale_template_fragments_before_compiling(): void
    {
        File::put($this->templateRoot.'/locale-test.ini', implode("\n", [
            'name_fragment={{ session.loggedIn ? playerDetails.getName() \:',
        ]));
        File::put($this->templateRoot.'/default/locale-fragment.tpl', implode("\n", [
            'document.habboLoggedIn = {{ session.loggedIn }};',
            'var habboName = "{{ locale.name_fragment|escape(\'js\') }} "" }}";',
        ]));
        $this->forgetTemplateServices();

        $this->assertSame(
            "document.habboLoggedIn = false;\nvar habboName = \"\";",
            app(LegacyTemplate::class)->render('locale-fragment'),
        );

        $userId = $this->createUser('TemplateUser');
        request()->session()->put('authenticated', true);
        request()->session()->put('user.id', $userId);
        $this->forgetTemplateServices();

        $this->assertSame(
            "document.habboLoggedIn = true;\nvar habboName = \"TemplateUser\";",
            app(LegacyTemplate::class)->render('locale-fragment'),
        );
    }

    public function test_legacy_javascript_escape_keeps_readable_text(): void
    {
        File::put($this->templateRoot.'/locale-test.ini', implode("\n", [
            'js_text=<div class\=\\"register-label\\">Email\: \\"quoted\\"</div>\nNext',
        ]));
        File::put($this->templateRoot.'/default/js-escape.tpl', '{{ locale.js_text|escape(\'js\') }}');
        $this->forgetTemplateServices();

        $rendered = app(LegacyTemplate::class)->render('js-escape');

        $this->assertSame('<div class=\"register-label\">Email: \"quoted\"<\/div>\nNext', $rendered);
        $this->assertStringNotContainsString('\u003C', $rendered);
        $this->assertStringNotContainsString('\u0020', $rendered);
    }

    private function forgetTemplateServices(): void
    {
        app()->forgetInstance(LegacyLocale::class);
        app()->forgetInstance(LegacyTemplate::class);
    }

    private function createUser(string $username, int $rank = 1): int
    {
        return (int) \DB::table('users')->insertGetId([
            'username' => $username,
            'password' => '',
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => strtolower($username).'@example.test',
            'rank' => $rank,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
