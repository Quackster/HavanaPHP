<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_legacy_homepage_renders_from_havana_templates(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('<!DOCTYPE html', false)
            ->assertSee('Habbo', false)
            ->assertSee('login-habblet', false);
    }

    public function test_havana_status_endpoint_reports_paths(): void
    {
        $response = $this->get('/_havana/status');

        $response
            ->assertStatus(200)
            ->assertJsonPath('app', 'HavanaPHP')
            ->assertJsonPath('templatePath', base_path('resources/legacy/www-tpl').'/default')
            ->assertJsonPath('housekeepingPath', 'allseeingeye/hk');
    }
}
