<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\HotelStatus;
use App\Services\LegacyPasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table): void {
            $table->increments('id');
            $table->string('username');
            $table->text('password')->default('');
            $table->string('figure')->default('');
            $table->string('pool_figure')->default('');
            $table->char('sex', 1)->default('M');
            $table->string('motto', 100)->default('');
            $table->string('email')->default('');
            $table->integer('credits')->default(50);
            $table->integer('pixels')->default(0);
            $table->integer('tickets')->default(0);
            $table->integer('film')->default(0);
            $table->unsignedTinyInteger('rank')->default(1);
            $table->timestamp('last_online')->nullable();
            $table->string('remember_token', 50)->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamps();
            $table->string('sso_ticket')->nullable();
            $table->text('machine_id')->default('');
            $table->bigInteger('club_subscribed')->default(0);
            $table->bigInteger('club_expiration')->default(0);
            $table->bigInteger('club_gift_due')->default(0);
            $table->boolean('allow_stalking')->default(true);
            $table->boolean('allow_friend_requests')->default(true);
            $table->boolean('online_status_visible')->default(true);
            $table->boolean('profile_visible')->default(true);
            $table->boolean('wordfilter_enabled')->default(true);
            $table->boolean('trade_enabled')->default(false);
            $table->bigInteger('trade_ban_expiration')->default(0);
            $table->boolean('sound_enabled')->default(true);
            $table->integer('selected_room_id')->default(0);
            $table->boolean('tutorial_finished')->default(false);
            $table->boolean('daily_coins_enabled')->default(false);
            $table->integer('daily_respect_points')->default(3);
            $table->integer('respect_points')->default(0);
            $table->string('respect_day', 11)->default('');
            $table->integer('respect_given')->default(0);
            $table->bigInteger('totem_effect_expiry')->default(0);
            $table->integer('favourite_group')->default(0);
            $table->integer('home_room')->default(0);
            $table->boolean('has_flash_warning')->default(true);
        });

        Schema::create('users_statistics', function ($table): void {
            $table->integer('user_id');
            $table->integer('days_logged_in_row')->default(0);
            $table->integer('guestbook_unread_messages')->default(0);
            $table->integer('online_time')->default(0);
            $table->integer('battleball_score_month')->default(0);
            $table->integer('battleball_score_all_time')->default(0);
            $table->integer('snowstorm_score_month')->default(0);
            $table->integer('snowstorm_score_all_time')->default(0);
            $table->integer('wobble_squabble_score_month')->default(0);
            $table->integer('wobble_squabble_score_all_time')->default(0);
            $table->integer('xp_earned_month')->default(0);
            $table->integer('xp_all_time')->default(0);
            $table->integer('battleball_games_won')->default(0);
            $table->integer('snowstorm_games_won')->default(0);
            $table->integer('wobble_squabble_games_won')->default(0);
            $table->integer('guided_by')->default(0);
            $table->integer('has_tutorial')->default(1);
            $table->integer('players_guided')->default(0);
            $table->integer('newbie_room_layout')->default(0);
            $table->integer('newbie_gift')->default(0);
            $table->bigInteger('newbie_gift_time')->default(0);
            $table->dateTime('club_gift_due')->nullable();
            $table->integer('gifts_due')->default(0);
            $table->bigInteger('club_member_time')->default(0);
            $table->bigInteger('club_member_time_updated')->default(0);
            $table->string('activation_code')->nullable();
            $table->string('verify_code')->nullable();
            $table->string('forgot_password_code')->nullable();
            $table->bigInteger('forgot_recovery_requested_time')->nullable();
            $table->integer('is_guidable')->default(1);
            $table->bigInteger('mute_expires_at')->default(0);
        });

        Schema::create('users_ip_logs', function ($table): void {
            $table->integer('user_id');
            $table->string('ip_address', 45);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('users_wardrobes', function ($table): void {
            $table->integer('user_id');
            $table->integer('slot_id');
            $table->string('figure');
            $table->char('sex', 1)->default('M');
        });

        Schema::create('rooms_ads', function ($table): void {
            $table->increments('id');
            $table->boolean('is_loading_ad')->default(false);
            $table->integer('room_id')->default(-1);
            $table->string('url')->nullable();
            $table->string('image')->nullable();
            $table->boolean('enabled')->default(true);
        });

        Schema::create('users_referred', function ($table): void {
            $table->integer('user_id')->nullable();
            $table->integer('referred_id')->nullable();
        });

        Schema::create('users_bans', function ($table): void {
            $table->string('ban_type');
            $table->string('banned_value', 250);
            $table->text('message')->default('');
            $table->dateTime('banned_until');
            $table->dateTime('banned_at')->nullable();
            $table->integer('banned_by');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('article_categories', function ($table): void {
            $table->increments('id');
            $table->string('label');
            $table->string('category_index');
        });

        Schema::create('site_articles', function ($table): void {
            $table->increments('id');
            $table->string('title', 64)->default('Undefined Title');
            $table->integer('author_id')->nullable();
            $table->string('author_override', 50)->default('');
            $table->mediumText('short_story')->nullable();
            $table->mediumText('full_story')->nullable();
            $table->string('topstory', 500)->default('attention_topstory.png');
            $table->longText('topstory_override')->default('');
            $table->mediumText('article_image')->default('');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_future_published')->default(false);
            $table->integer('views')->default(0);
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('site_articles_categories', function ($table): void {
            $table->integer('article_id');
            $table->integer('category_id');
        });

        Schema::create('users_tags', function ($table): void {
            $table->integer('user_id')->nullable();
            $table->string('tag', 20);
            $table->string('room_id', 20)->default('0');
            $table->string('group_id', 20)->default('0');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('vouchers', function ($table): void {
            $table->string('voucher_code', 100)->unique();
            $table->integer('credits')->default(0);
            $table->dateTime('expiry_date')->nullable();
            $table->boolean('is_single_use')->default(true);
            $table->boolean('allow_new_users')->default(false);
        });

        Schema::create('vouchers_history', function ($table): void {
            $table->string('voucher_code', 100);
            $table->integer('user_id');
            $table->timestamp('used_at')->nullable();
            $table->integer('credits_redeemed')->nullable();
            $table->text('items_redeemed')->nullable();
        });

        Schema::create('vouchers_items', function ($table): void {
            $table->string('voucher_code', 100);
            $table->string('catalogue_sale_code', 100);
        });

        Schema::create('wordfilter', function ($table): void {
            $table->increments('id');
            $table->string('word', 100)->unique();
            $table->boolean('is_bannable')->default(false);
            $table->boolean('is_filterable')->default(true);
        });

        Schema::create('recycler_rewards', function ($table): void {
            $table->string('sprite')->primary();
            $table->integer('order_id')->default(0);
            $table->integer('chance')->default(5);
        });

        Schema::create('rooms_categories', function ($table): void {
            $table->increments('id');
            $table->integer('order_id');
            $table->integer('parent_id');
            $table->boolean('isnode')->default(false);
            $table->string('name');
            $table->boolean('public_spaces')->default(false);
            $table->boolean('allow_trading')->default(false);
            $table->integer('minrole_access')->default(1);
            $table->integer('minrole_setflatcat')->default(1);
            $table->boolean('club_only')->default(false);
            $table->boolean('is_top_priority')->default(false);
        });

        Schema::create('rooms_models', function ($table): void {
            $table->increments('id');
            $table->string('model_id');
            $table->string('model_name');
            $table->integer('door_x')->default(0);
            $table->integer('door_y')->default(0);
            $table->double('door_z')->default(0);
            $table->integer('door_dir')->default(2);
            $table->text('heightmap')->default('');
            $table->string('trigger_class')->default('flat_trigger');
        });

        Schema::create('rooms_entry_badges', function ($table): void {
            $table->integer('room_id');
            $table->string('badge', 15);
        });

        Schema::create('infobus_polls', function ($table): void {
            $table->increments('id');
            $table->integer('initiated_by');
            $table->text('poll_data');
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('infobus_polls_answers', function ($table): void {
            $table->integer('poll_id');
            $table->integer('answer');
            $table->integer('user_id');
        });

        Schema::create('users_transactions', function ($table): void {
            $table->integer('user_id');
            $table->longText('item_id')->default('');
            $table->longText('catalogue_id')->default('');
            $table->integer('amount')->default(1);
            $table->longText('description')->default('');
            $table->integer('credit_cost')->default(0);
            $table->integer('pixel_cost')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->boolean('is_visible')->default(true);
        });

        Schema::create('catalogue_collectables', function ($table): void {
            $table->integer('store_page')->primary();
            $table->integer('admin_page');
            $table->bigInteger('expiry')->default(0);
            $table->bigInteger('lifetime')->default(2678400);
            $table->integer('current_position')->default(0);
            $table->text('class_names');
        });

        Schema::create('items_definitions', function ($table): void {
            $table->increments('id');
            $table->string('sprite', 50);
            $table->string('name', 100);
            $table->string('description', 100)->default('');
            $table->integer('sprite_id')->default(0);
            $table->integer('length')->default(1);
            $table->integer('width')->default(1);
            $table->double('top_height')->default(0);
            $table->string('max_status', 11)->default('');
            $table->string('behaviour', 150)->default('');
            $table->string('interactor', 150)->default('');
            $table->boolean('is_tradable')->default(true);
            $table->boolean('is_recyclable')->default(true);
            $table->text('drink_ids')->nullable();
            $table->integer('rental_time')->default(-1);
            $table->text('allowed_rotations')->nullable();
            $table->string('heights', 50)->default('');
        });

        Schema::create('catalogue_items', function ($table): void {
            $table->increments('id');
            $table->string('sale_code')->default('');
            $table->text('page_id');
            $table->integer('order_id')->default(0);
            $table->integer('price_coins')->default(3);
            $table->integer('price_pixels')->default(0);
            $table->integer('seasonal_coins')->default(0);
            $table->integer('seasonal_pixels')->default(0);
            $table->boolean('hidden')->default(false);
            $table->integer('amount')->default(1);
            $table->integer('definition_id')->nullable();
            $table->string('item_specialspriteid', 25)->default('');
            $table->boolean('is_package')->default(false);
            $table->string('active_at', 50)->default('');
        });

        Schema::create('catalogue_pages', function ($table): void {
            $table->increments('id');
            $table->integer('old_id')->default(0);
            $table->integer('parent_id')->default(-1);
            $table->integer('order_id')->default(1);
            $table->integer('min_role')->default(1);
            $table->boolean('is_navigatable')->default(false);
            $table->boolean('is_club_only')->default(false);
            $table->text('name');
            $table->integer('icon')->default(0);
            $table->integer('colour')->default(0);
            $table->text('layout')->default('');
            $table->text('images')->default('[]');
            $table->text('texts')->default('[]');
            $table->string('seasonal_start', 200)->nullable();
            $table->integer('seasonal_length')->default(0);
        });

        Schema::create('catalogue_packages', function ($table): void {
            $table->increments('id');
            $table->string('salecode')->nullable();
            $table->integer('definition_id')->nullable();
            $table->string('special_sprite_id')->nullable();
            $table->integer('amount')->nullable();
        });

        Schema::create('catalogue_sale_badges', function ($table): void {
            $table->string('sale_code', 250);
            $table->string('badge_code', 250);
        });

        Schema::create('items', function ($table): void {
            $table->bigIncrements('id');
            $table->integer('order_id')->default(-1);
            $table->integer('user_id')->nullable();
            $table->integer('room_id')->default(0);
            $table->integer('definition_id');
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->string('z', 20)->default('0');
            $table->string('wall_position', 100)->default('');
            $table->integer('rotation')->default(0);
            $table->longText('custom_data')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_trading')->default(false);
            $table->bigInteger('expire_time')->default(-1);
            $table->timestamps();
        });

        Schema::create('messenger_categories', function ($table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('name', 50);
        });

        Schema::create('messenger_friends', function ($table): void {
            $table->integer('from_id');
            $table->integer('to_id');
            $table->integer('category_id')->default(0);
        });

        Schema::create('messenger_requests', function ($table): void {
            $table->integer('from_id')->nullable();
            $table->integer('to_id')->nullable();
        });

        Schema::create('users_badges', function ($table): void {
            $table->integer('user_id');
            $table->string('badge', 50);
            $table->boolean('equipped')->default(false);
            $table->integer('slot_id')->default(0);
        });

        Schema::create('rank_badges', function ($table): void {
            $table->unsignedTinyInteger('rank')->default(1);
            $table->char('badge', 3);
        });

        Schema::create('housekeeping_audit_log', function ($table): void {
            $table->string('action', 30);
            $table->integer('user_id');
            $table->integer('target_id')->default(-1);
            $table->string('message')->default('');
            $table->string('extra_notes')->default('');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('cms_minimail', function ($table): void {
            $table->increments('id');
            $table->integer('target_id');
            $table->integer('sender_id');
            $table->integer('to_id');
            $table->boolean('is_read')->default(false);
            $table->string('subject', 100)->default('');
            $table->text('message');
            $table->dateTime('date_sent')->nullable();
            $table->integer('conversation_id')->default(0);
            $table->boolean('is_trash')->default(false);
            $table->boolean('is_deleted')->default(false);
        });

        Schema::create('cms_recommended', function ($table): void {
            $table->integer('recommended_id');
            $table->string('type');
            $table->boolean('is_staff_pick')->default(false);
        });

        Schema::create('cms_alerts', function ($table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('alert_type', 30);
            $table->text('message')->nullable();
            $table->boolean('is_disabled')->default(false);
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('rooms', function ($table): void {
            $table->increments('id');
            $table->string('owner_id', 11);
            $table->integer('category')->default(2);
            $table->text('name')->default('');
            $table->text('description')->default('');
            $table->string('model', 50)->default('model_s');
            $table->string('ccts', 255)->default('');
            $table->integer('wallpaper')->default(0);
            $table->integer('floor')->default(0);
            $table->string('landscape', 10)->default('0');
            $table->boolean('showname')->default(true);
            $table->boolean('superusers')->default(false);
            $table->tinyInteger('accesstype')->default(0);
            $table->string('password')->default('');
            $table->integer('visitors_now')->default(0);
            $table->integer('visitors_max')->default(25);
            $table->integer('rating')->default(0);
            $table->string('icon_data', 255)->default('0|0|');
            $table->integer('group_id')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        Schema::create('rooms_rights', function ($table): void {
            $table->integer('user_id');
            $table->integer('room_id');
        });

        Schema::create('rooms_bans', function ($table): void {
            $table->integer('room_id');
            $table->integer('user_id');
            $table->bigInteger('expire_at');
        });

        Schema::create('rooms_events', function ($table): void {
            $table->integer('room_id')->primary();
            $table->integer('user_id');
            $table->integer('category_id');
            $table->string('name');
            $table->text('description');
            $table->bigInteger('expire_time');
            $table->text('tags')->default('');
        });

        Schema::create('groups_details', function ($table): void {
            $table->increments('id');
            $table->string('name', 45);
            $table->mediumText('description')->default('');
            $table->integer('owner_id');
            $table->integer('room_id')->default(0);
            $table->mediumText('badge')->default('b0503Xs09114s05013s05015');
            $table->integer('recommended')->default(0);
            $table->string('background')->default('bg_colour_08');
            $table->integer('views')->default(0);
            $table->smallInteger('topics')->default(0);
            $table->unsignedTinyInteger('group_type')->default(0);
            $table->unsignedTinyInteger('forum_type')->default(0);
            $table->unsignedTinyInteger('forum_premission')->default(0);
            $table->string('alias', 30)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('groups_memberships', function ($table): void {
            $table->integer('user_id');
            $table->integer('group_id');
            $table->string('member_rank', 1)->default('1');
            $table->boolean('is_pending')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('groups_edit_sessions', function ($table): void {
            $table->integer('user_id');
            $table->integer('group_id');
            $table->bigInteger('expire');
        });

        Schema::create('homes_details', function ($table): void {
            $table->integer('user_id')->unique();
            $table->string('background')->default('bg_pattern_abstract2');
        });

        Schema::create('homes_edit_sessions', function ($table): void {
            $table->integer('user_id');
            $table->bigInteger('expire');
        });

        Schema::create('homes_ratings', function ($table): void {
            $table->integer('user_id');
            $table->integer('home_id');
            $table->integer('rating')->default(0);
        });

        Schema::create('cms_stickers', function ($table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('x', 6)->default('0');
            $table->string('y', 6)->default('0');
            $table->string('z', 6)->default('0');
            $table->integer('sticker_id');
            $table->integer('skin_id')->default(0);
            $table->integer('group_id')->default(-1);
            $table->longText('text')->default('');
            $table->boolean('is_placed')->default(false);
            $table->string('extra_data', 11)->default('');
        });

        Schema::create('cms_stickers_catalogue', function ($table): void {
            $table->increments('id');
            $table->mediumText('name');
            $table->string('description')->default('');
            $table->string('type', 1);
            $table->mediumText('data');
            $table->integer('price')->default(0);
            $table->integer('amount')->default(1);
            $table->integer('category_id')->default(0);
            $table->integer('min_rank')->default(1);
            $table->integer('widget_type')->default(0);
        });

        Schema::create('cms_stickers_categories', function ($table): void {
            $table->integer('id')->primary();
            $table->string('name', 50);
            $table->integer('min_rank')->default(1);
            $table->integer('category_type')->default(1);
        });

        Schema::create('cms_guestbook_entries', function ($table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('home_id')->default(0);
            $table->integer('group_id')->default(0);
            $table->text('message');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('cms_forum_threads', function ($table): void {
            $table->increments('id');
            $table->string('topic_title', 32);
            $table->integer('poster_id');
            $table->boolean('is_open')->default(true);
            $table->boolean('is_stickied')->default(false);
            $table->integer('views')->default(0);
            $table->integer('group_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('modified_at')->nullable();
        });

        Schema::create('cms_forum_replies', function ($table): void {
            $table->increments('id');
            $table->integer('thread_id');
            $table->text('message');
            $table->integer('poster_id');
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('modified_at')->nullable();
        });

        Schema::create('cms_forums_read_replies', function ($table): void {
            $table->integer('user_id');
            $table->integer('reply_id');
        });

        Schema::create('soundmachine_songs', function ($table): void {
            $table->increments('id');
            $table->integer('user_id')->default(0);
            $table->string('title', 100);
            $table->bigInteger('item_id')->default(0);
            $table->integer('length')->default(0);
            $table->text('data');
            $table->boolean('burnt')->default(false);
        });

        Schema::create('soundmachine_disks', function ($table): void {
            $table->bigInteger('item_id');
            $table->bigInteger('soundmachine_id')->default(0);
            $table->integer('slot_id');
            $table->integer('song_id');
            $table->bigInteger('burned_at');
        });

        Schema::create('settings', function ($table): void {
            $table->string('setting', 50)->primary();
            $table->longText('value')->default('');
        });

        app(HavanaConfig::class)->reload();
    }

    public function test_legacy_account_submit_logs_in_and_redirects_to_security_check(): void
    {
        $hash = app(LegacyPasswordHasher::class)->make('secret123');
        User::query()->create([
            'username' => 'Alex',
            'password' => $hash,
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => 'alex@example.test',
        ]);

        $response = $this->withSession([
            'captcha.invalid' => true,
            'xssSeed' => 12345,
            'xssKey' => 1553932502,
            'xssRequested' => '/credits',
        ])->post('/account/submit', [
            'username' => 'Alex',
            'password' => 'secret123',
            '_login_remember_me' => 'true',
        ]);

        $response->assertRedirect('/security_check');
        $this->assertAuthenticated();
        $response->assertPlainCookie('remember_token');
        $response->assertSessionHas('captcha.invalid', false);
        $response->assertSessionMissing('xssSeed');
        $response->assertSessionMissing('xssKey');
        $response->assertSessionMissing('xssRequested');
    }

    public function test_legacy_remember_cookie_restores_authenticated_session(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'RememberedUser',
            'email' => 'remembered@example.test',
            'remember_token' => 'remember-me',
        ]);
        $this->insertStatistics($user->id);

        $response = $this->withUnencryptedCookie('remember_token', 'remember-me')
            ->withSession(['captcha.invalid' => true])
            ->get('/me');

        $response
            ->assertOk()
            ->assertSessionHas('authenticated', true)
            ->assertSessionHas('captcha.invalid', false)
            ->assertSessionHas('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_legacy_remember_cookie_redirects_public_entry_points_to_me(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'EntryRemembered',
            'email' => 'entry-remembered@example.test',
            'remember_token' => 'entry-token',
        ]);

        foreach (['/', '/home', '/index'] as $path) {
            $this->withUnencryptedCookie('remember_token', 'entry-token')
                ->get($path)
                ->assertRedirect('/me')
                ->assertSessionHas('authenticated', true)
                ->assertSessionHas('captcha.invalid', false)
                ->assertSessionHas('user.id', $user->id);
        }
    }

    public function test_legacy_remember_cookie_clears_invalid_tokens(): void
    {
        $this->withUnencryptedCookie('remember_token', 'stale-token')
            ->get('/me')
            ->assertRedirect('/')
            ->assertCookieExpired('remember_token')
            ->assertSessionMissing('authenticated')
            ->assertSessionMissing('user.id');

        $this->assertGuest();
    }

    public function test_account_submit_accepts_legacy_get_request(): void
    {
        $response = $this->withSession([
            'authenticated' => true,
            'user.id' => 404,
            'xssSeed' => 12345,
            'xssKey' => 1553932502,
            'xssRequested' => '/credits',
        ])->get('/account/submit');

        $response
            ->assertOk()
            ->assertSee('manual_redirect_link', false)
            ->assertSee('username=', false)
            ->assertSee('rememberme=false', false);

        $response->assertSessionMissing('authenticated');
        $response->assertSessionMissing('user.id');
        $response->assertSessionMissing('xssSeed');
        $response->assertSessionMissing('xssKey');
        $response->assertSessionMissing('xssRequested');
    }

    public function test_login_popup_and_account_login_render_legacy_template(): void
    {
        foreach (['/login_popup', '/account/login'] as $path) {
            $response = $this->withSession(['alertMessage' => "Incorrect username or password\n"])
                ->get($path);

            $response
                ->assertOk()
                ->assertSee('id="popup"', false)
                ->assertSee('class="login-habblet"', false)
                ->assertSee('action="http://localhost/account/submit"', false)
                ->assertSee('id="login-submit-button"', false)
                ->assertSessionHas('page', 'login_popup')
                ->assertSessionMissing('alertMessage');
        }
    }

    public function test_security_check_matches_legacy_redirect_template(): void
    {
        $this->get('/security_check')
            ->assertRedirect('/');

        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/security_check')
            ->assertOk()
            ->assertSee("window.location.replace('http://localhost/me');", false)
            ->assertSee('id="manual_redirect_link"', false)
            ->assertSee('href="http://localhost/me"', false);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'lastBrowsedPage' => '/community',
            ])
            ->get('/security_check')
            ->assertOk()
            ->assertSee("window.location.replace('http://localhost/community');", false)
            ->assertSee('href="http://localhost/community"', false);
    }

    public function test_account_logout_renders_legacy_signed_out_page(): void
    {
        $user = User::query()->create([
            'username' => 'Alex',
            'password' => app(LegacyPasswordHasher::class)->make('secret123'),
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => 'alex@example.test',
            'remember_token' => 'remember-me',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'lastBrowsedPage' => '/community',
                'minimailLabel' => 'inbox',
            ])
            ->get('/account/logout');

        $response
            ->assertOk()
            ->assertSee('You have successfully signed out', false)
            ->assertSee('id="logout-ok"', false)
            ->assertSessionHas('page', 'logout')
            ->assertSessionMissing('authenticated')
            ->assertSessionMissing('user.id')
            ->assertSessionMissing('lastBrowsedPage')
            ->assertSessionMissing('minimailLabel');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'remember_token' => null,
        ]);
    }

    public function test_welcome_renders_for_users_who_can_select_room(): void
    {
        $user = $this->createLegacyUser(['selected_room_id' => 0]);
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/welcome');

        $response
            ->assertOk()
            ->assertSee('roomselection-welcome', false)
            ->assertSessionHas('page', 'welcome');
    }

    public function test_welcome_redirects_after_room_selection_is_finished(): void
    {
        $user = $this->createLegacyUser(['selected_room_id' => -1]);
        $this->insertStatistics($user->id);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/welcome')
            ->assertRedirect('/me');
    }

    public function test_me_renders_for_legacy_session_authentication(): void
    {
        $user = $this->createLegacyUser();
        $sender = $this->createLegacyUser([
            'username' => 'MeMailSender',
            'email' => 'me-mail-sender@example.test',
        ]);
        $onlineFriend = $this->createLegacyUser([
            'username' => 'OnlinePal',
            'email' => 'online-pal@example.test',
        ]);
        $offlineFriend = $this->createLegacyUser([
            'username' => 'OfflinePal',
            'email' => 'offline-pal@example.test',
        ]);
        $hiddenOnlineFriend = $this->createLegacyUser([
            'username' => 'HiddenOnlinePal',
            'email' => 'hidden-online-pal@example.test',
        ]);
        $requester = $this->createLegacyUser([
            'username' => 'RequestingPal',
            'email' => 'requesting-pal@example.test',
        ]);
        $user->forceFill([
            'last_online' => '2026-06-29 08:15:30',
            'club_expiration' => time() + 172800,
        ])->save();
        $onlineFriend->forceFill(['is_online' => true, 'online_status_visible' => true])->save();
        $offlineFriend->forceFill(['is_online' => false, 'online_status_visible' => true])->save();
        $hiddenOnlineFriend->forceFill(['is_online' => true, 'online_status_visible' => false])->save();
        $this->insertStatistics($user->id, [
            'newbie_room_layout' => 2,
            'newbie_gift' => 1,
            'newbie_gift_time' => time() + 3600,
            'guestbook_unread_messages' => 3,
        ]);
        $articleId = \DB::table('site_articles')->insertGetId([
            'title' => 'Me Page News',
            'author_id' => $user->id,
            'author_override' => '',
            'short_story' => 'Me page short story',
            'full_story' => 'Me page full story',
            'topstory' => 'attention_topstory.png',
            'topstory_override' => '',
            'article_image' => '',
            'is_published' => true,
            'is_future_published' => false,
            'views' => 0,
            'created_at' => now(),
        ]);
        $categoryId = \DB::table('article_categories')->insertGetId([
            'label' => 'News',
            'category_index' => 'news',
        ]);
        \DB::table('site_articles_categories')->insert([
            'article_id' => $articleId,
            'category_id' => $categoryId,
        ]);
        \DB::table('users_tags')->insert([
            'user_id' => $user->id,
            'tag' => 'retro',
            'room_id' => '0',
            'group_id' => '0',
            'created_at' => now(),
        ]);
        \DB::table('cms_alerts')->insert([
            'user_id' => $user->id,
            'alert_type' => 'CREDIT_DONATION',
            'message' => 'Daily credits arrived',
            'is_disabled' => false,
            'created_at' => now(),
        ]);
        \DB::table('cms_minimail')->insert([
            'target_id' => $user->id,
            'sender_id' => $sender->id,
            'to_id' => $user->id,
            'subject' => 'Me inbox subject',
            'message' => 'Me inbox body',
            'date_sent' => '2026-06-29 09:30:00',
            'is_read' => false,
            'conversation_id' => 0,
            'is_trash' => false,
            'is_deleted' => false,
        ]);
        \DB::table('messenger_friends')->insert([
            ['from_id' => $onlineFriend->id, 'to_id' => $user->id, 'category_id' => 0],
            ['from_id' => $user->id, 'to_id' => $onlineFriend->id, 'category_id' => 0],
            ['from_id' => $offlineFriend->id, 'to_id' => $user->id, 'category_id' => 0],
            ['from_id' => $hiddenOnlineFriend->id, 'to_id' => $user->id, 'category_id' => 0],
        ]);
        \DB::table('messenger_requests')->insert([
            'from_id' => $requester->id,
            'to_id' => $user->id,
        ]);
        $joinedGroupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Joined Me Group',
            'description' => 'A joined group',
            'owner_id' => $user->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'alias' => 'joined-me-group',
            'created_at' => now(),
        ]);
        $recommendedGroupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Recommended Me Group',
            'description' => 'A recommended group',
            'owner_id' => $user->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'alias' => 'recommended-me-group',
            'created_at' => now(),
        ]);
        $staffPickGroupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Staff Pick Me Group',
            'description' => 'A staff pick group',
            'owner_id' => $user->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'alias' => 'staff-pick-me-group',
            'created_at' => now(),
        ]);
        \DB::table('groups_memberships')->insert([
            [
                'user_id' => $user->id,
                'group_id' => $joinedGroupId,
                'member_rank' => '3',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $requester->id,
                'group_id' => $joinedGroupId,
                'member_rank' => '1',
                'is_pending' => true,
                'created_at' => now(),
            ],
        ]);
        $threadId = \DB::table('cms_forum_threads')->insertGetId([
            'topic_title' => 'Me Feed Thread',
            'poster_id' => $onlineFriend->id,
            'is_open' => true,
            'is_stickied' => false,
            'views' => 0,
            'group_id' => $joinedGroupId,
            'created_at' => now()->subMinute(),
            'modified_at' => now()->subMinute(),
        ]);
        \DB::table('cms_forum_replies')->insert([
            'thread_id' => $threadId,
            'message' => 'Unread feed reply',
            'poster_id' => $onlineFriend->id,
            'is_edited' => false,
            'is_deleted' => false,
            'created_at' => now(),
            'modified_at' => now(),
        ]);
        \DB::table('cms_recommended')->insert([
            [
                'recommended_id' => $recommendedGroupId,
                'type' => 'GROUP',
                'is_staff_pick' => false,
            ],
            [
                'recommended_id' => $staffPickGroupId,
                'type' => 'GROUP',
                'is_staff_pick' => true,
            ],
        ]);

        $response = $this->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/me');

        $response
            ->assertOk()
            ->assertSee('Alex', false)
            ->assertSee('Me Page News', false)
            ->assertSee('Me page short story', false)
            ->assertSee('Daily credits arrived', false)
            ->assertSee('retro', false)
            ->assertSee('feed-guestbook', false)
            ->assertSee('noob_stool_2.png', false)
            ->assertSee('Jun 29, 2026 08:15:30 AM', false)
            ->assertSee('Joined Me Group', false)
            ->assertSee('/groups/joined-me-group', false)
            ->assertSee('Recommended Me Group', false)
            ->assertSee('/groups/recommended-me-group', false)
            ->assertSee('Staff Pick Me Group', false)
            ->assertSee('/groups/staff-pick-me-group', false)
            ->assertSee('MeMailSender', false)
            ->assertSee('Me inbox subject', false)
            ->assertSee('1 - 1', false)
            ->assertDontSee('no-messages', false)
            ->assertSee('feed-notification', false)
            ->assertSee('1 friend requests', false)
            ->assertSee('feed-friends', false)
            ->assertSee('/home/OnlinePal', false)
            ->assertSee('OnlinePal', false)
            ->assertDontSee('OfflinePal', false)
            ->assertDontSee('HiddenOnlinePal', false)
            ->assertSee('feed-pending-members', false)
            ->assertSee('/groups/'.$joinedGroupId.'/id', false)
            ->assertSee('feed-group-discussion', false)
            ->assertSee('/groups/'.$joinedGroupId.'/id/discussions', false)
            ->assertSessionHas('page', 'me');
    }

    public function test_me_matches_legacy_session_cookie_and_ip_side_effects(): void
    {
        $user = $this->createLegacyUser();
        $user->forceFill(['machine_id' => '#machine-key'])->save();
        $this->insertStatistics($user->id);

        $response = $this->withSession([
            'authenticated' => true,
            'user.id' => $user->id,
            'captcha.invalid' => true,
        ])->get('/me');

        $response
            ->assertOk()
            ->assertCookie('SECURITY_KEY', 'machine-key')
            ->assertSessionMissing('captcha.invalid')
            ->assertSessionHas('page', 'me');

        $this->assertDatabaseHas('users_ip_logs', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
        ]);

        $this->withCookie('SECURITY_KEY', 'machine-key')
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/me')
            ->assertOk();

        $this->assertSame(1, \DB::table('users_ip_logs')->where('user_id', $user->id)->count());
    }

    public function test_me_clears_stale_legacy_session_user(): void
    {
        $this->withSession([
            'authenticated' => true,
            'user.id' => 404,
        ])->get('/me')
            ->assertRedirect('/')
            ->assertSessionMissing('authenticated')
            ->assertSessionMissing('user.id');
    }

    public function test_me_and_welcome_redirect_active_banned_users(): void
    {
        $user = $this->createLegacyUser(['selected_room_id' => 0]);
        $this->insertStatistics($user->id);
        \DB::table('users_bans')->insert([
            'ban_type' => 'USER_ID',
            'banned_value' => (string) $user->id,
            'message' => 'Banned from account routes',
            'banned_until' => now()->addDay(),
            'banned_at' => now(),
            'banned_by' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/me')
            ->assertRedirect('/account/banned');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/welcome')
            ->assertRedirect('/account/banned');
    }

    public function test_api_login_returns_existing_sso_ticket(): void
    {
        $hash = app(LegacyPasswordHasher::class)->make('secret123');
        User::query()->create([
            'username' => 'Alex',
            'password' => $hash,
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => 'alex@example.test',
            'sso_ticket' => 'existing-ticket',
        ]);

        $response = $this->get('/api/login?username=Alex&password=secret123');

        $response
            ->assertOk()
            ->assertSee('existing-ticket', false);

        $this->get('/api/ticket')
            ->assertOk()
            ->assertContent('');

        $this->get('/api/ticket?username=Alex&password=wrong')
            ->assertOk()
            ->assertContent('');

        $ticketResponse = $this->get('/api/ticket?username=Alex&password=secret123');
        $ticketResponse
            ->assertOk()
            ->assertHeader('Content-Type', 'text/json; charset=utf-8')
            ->assertJsonPath('ssoTicket', 'existing-ticket')
            ->assertJsonPath('host', app(HavanaConfig::class)->string('loader.game.ip'));
    }

    public function test_api_login_and_ticket_rotate_sso_when_configured(): void
    {
        \DB::table('settings')->insert([
            ['setting' => 'reset.sso.after.login', 'value' => 'true'],
        ]);
        app(HavanaConfig::class)->reload();

        $hash = app(LegacyPasswordHasher::class)->make('secret123');
        $user = User::query()->create([
            'username' => 'SsoResetUser',
            'password' => $hash,
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => 'sso-reset@example.test',
            'sso_ticket' => 'old-ticket',
        ]);

        $loginResponse = $this->get('/api/login?username=SsoResetUser&password=secret123')
            ->assertOk();
        $loginTicket = trim((string) $loginResponse->getContent());
        $this->assertNotSame('', $loginTicket);
        $this->assertNotSame('old-ticket', $loginTicket);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'sso_ticket' => $loginTicket,
        ]);

        $ticketResponse = $this->get('/api/ticket?username=SsoResetUser&password=secret123')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/json; charset=utf-8');
        $jsonTicket = $ticketResponse->json('ssoTicket');
        $this->assertNotSame('', $jsonTicket);
        $this->assertNotSame($loginTicket, $jsonTicket);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'sso_ticket' => $jsonTicket,
        ]);
    }

    public function test_api_advertisement_verify_and_imaging_routes(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'VerifyUser',
            'email' => 'verify-user@example.test',
        ]);
        $this->insertStatistics($user->id, ['verify_code' => 'verify-token']);
        \DB::table('rooms_ads')->insert([
            'id' => 42,
            'is_loading_ad' => true,
            'room_id' => -1,
            'url' => 'https://example.test/ad-click',
            'image' => 'https://example.test/ad-image.gif',
            'enabled' => true,
        ]);

        $this->get('/api/advertisement/get_img?ad=42')
            ->assertRedirect('https://example.test/ad-image.gif');
        $this->get('/api/advertisement/get_url?ad=42')
            ->assertRedirect('https://example.test/ad-click');
        $this->get('/api/advertisement/get_img?ad=999')
            ->assertOk()
            ->assertContent('');
        $this->get('/api/advertisement/get_url')
            ->assertOk()
            ->assertContent('');

        $this->get('/api/verify/get/verify-token')
            ->assertOk()
            ->assertContent('VerifyUser');
        $this->get('/api/verify/get/missing-token')
            ->assertOk()
            ->assertContent('error: INVALID');
        $this->get('/api/verify/clear/verify-token')
            ->assertOk()
            ->assertContent('SUCCESS');
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'verify_code' => null,
        ]);

        \DB::table('settings')->updateOrInsert(
            ['setting' => 'site.imaging.endpoint'],
            ['value' => '']
        );
        \DB::table('settings')->updateOrInsert(
            ['setting' => 'site.imaging.endpoint.timeout'],
            ['value' => '']
        );
        app(HavanaConfig::class)->reload();

        $this->get('/habbo-imaging/avatarimage?figure=hd-180-1')
            ->assertNoContent();
        $this->get('/habbo-imaging/badge/b0503Xs09114s05013s05015.gif')
            ->assertNoContent();
        $this->get('/habbo-imaging/badge-fill/b0503Xs09114s05013s05015.png')
            ->assertNoContent();
    }

    public function test_imaging_routes_proxy_configured_renderer_endpoint(): void
    {
        \DB::table('settings')->updateOrInsert(
            ['setting' => 'site.imaging.endpoint'],
            ['value' => 'https://imager.example.test']
        );
        \DB::table('settings')->updateOrInsert(
            ['setting' => 'site.imaging.endpoint.timeout'],
            ['value' => '5']
        );
        app(HavanaConfig::class)->reload();

        Http::fake([
            'https://imager.example.test/habbo-imaging/avatarimage*' => Http::response('PNGDATA', 200, [
                'Content-Type' => 'image/png',
            ]),
            'https://imager.example.test/habbo-imaging/badge/*' => Http::response('GIFDATA', 200),
        ]);

        $this->get('/habbo-imaging/avatarimage?figure=hd-180-1&size=s')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertContent('PNGDATA');

        $this->get('/habbo-imaging/badge/b0503Xs09114s05013s05015.gif')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif')
            ->assertContent('GIFDATA');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://imager.example.test/habbo-imaging/avatarimage?figure=hd-180-1&size=s'
                && $request->hasHeader('User-Agent', 'Imager');
        });
    }

    public function test_imaging_routes_fall_back_when_renderer_endpoint_fails(): void
    {
        \DB::table('settings')->updateOrInsert(
            ['setting' => 'site.imaging.endpoint'],
            ['value' => 'https://imager.example.test']
        );
        app(HavanaConfig::class)->reload();

        Http::fake([
            'https://imager.example.test/*' => Http::response('missing', 503, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $this->get('/habbo-imaging/badge-fill/b0503Xs09114s05015.png')
            ->assertNoContent();
    }

    public function test_captcha_endpoint_stores_text_and_returns_png(): void
    {
        $response = $this->get('/captcha.jpg');

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('content-type'));
        $this->assertNotEmpty(session('captcha-text'));
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_register_page_renders_legacy_template(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSee('<!DOCTYPE html', false)
            ->assertSee('registerform', false)
            ->assertSee('captcha.jpg', false);
    }

    public function test_register_redirects_authenticated_users_and_renders_disabled_template_before_referral(): void
    {
        $user = $this->createLegacyUser();

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/register')
            ->assertRedirect('/me');

        auth()->logout();
        $this->flushSession();

        \DB::table('settings')->insert([
            'setting' => 'registration.disabled',
            'value' => 'true',
        ]);
        app(HavanaConfig::class)->reload();

        $this->get('/register?referral=42')
            ->assertOk()
            ->assertSee('Registration is currently disabled', false)
            ->assertSessionMissing('referral');
    }

    public function test_register_cancel_matches_legacy_session_cleanup(): void
    {
        $this->withSession([
            'referral' => 123,
            'captcha.invalid' => true,
            'email.invalid' => true,
            'registerUsername' => 'StagedUser',
            'registerPassword' => 'secret123',
        ])
            ->get('/register/cancel')
            ->assertRedirect('/')
            ->assertSessionMissing('referral')
            ->assertSessionMissing('captcha.invalid')
            ->assertSessionHas('email.invalid', true)
            ->assertSessionHas('registerUsername', 'StagedUser')
            ->assertSessionHas('registerPassword', 'secret123');
    }

    public function test_register_blank_legacy_fields_redirects_and_clears_captcha_invalid(): void
    {
        $this->withSession(['captcha.invalid' => true])
            ->post('/register', [
                'bean.avatarName' => '',
                'bean.captchaResponse' => 'abc123',
                'retypedPassword' => 'secret123',
                'bean.email' => 'blank@example.test',
            ])
            ->assertRedirect('/register?errorCode=blank_fields')
            ->assertSessionHas('captcha.invalid', false);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'blank@example.test']);
    }

    public function test_register_creates_user_statistics_and_logs_in(): void
    {
        $this->withSession(['captcha-text' => 'abc123']);

        $response = $this->post('/register', [
            'bean.avatarName' => 'NewUser',
            'bean.captchaResponse' => 'abc123',
            'retypedPassword' => 'secret123',
            'bean.email' => 'new@example.test',
            'bean.day' => '1',
            'bean.month' => '1',
            'bean.year' => '2000',
            'bean.figure' => 'hd-180-1.hr-100-61.ch-210-66.lg-270-82.sh-290-80',
            'bean.gender' => 'M',
            'terms' => 'true',
            'extra1' => 'x',
            'extra2' => 'x',
        ]);

        $response->assertRedirect('/welcome');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'NewUser',
            'email' => 'new@example.test',
            'sex' => 'M',
        ]);

        $userId = User::query()->where('username', 'NewUser')->value('id');
        $this->assertDatabaseHas('users_statistics', ['user_id' => $userId]);
        $this->assertDatabaseHas('users_ip_logs', [
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_register_persists_referral_and_matches_legacy_success_session_cleanup(): void
    {
        $this->withSession([
            'captcha-text' => 'abc123',
            'referral' => 77,
            'captcha.invalid' => true,
            'email.invalid' => true,
        ]);

        $response = $this->post('/register', [
            'bean.avatarName' => 'ReferralUser',
            'bean.captchaResponse' => 'abc123',
            'retypedPassword' => 'secret123',
            'bean.email' => 'referral@example.test',
            'bean.day' => '1',
            'bean.month' => '1',
            'bean.year' => '2000',
            'bean.figure' => 'hd-180-1.hr-100-61.ch-210-66.lg-270-82.sh-290-80',
            'bean.gender' => 'M',
            'terms' => 'true',
            'extra1' => 'x',
            'extra2' => 'x',
        ]);

        $response
            ->assertRedirect('/welcome')
            ->assertSessionMissing('referral')
            ->assertSessionMissing('captcha.invalid')
            ->assertSessionHas('email.invalid', true);

        $userId = User::query()->where('username', 'ReferralUser')->value('id');
        $this->assertDatabaseHas('users_referred', [
            'user_id' => 77,
            'referred_id' => $userId,
        ]);
    }

    public function test_register_blocks_when_ip_or_machine_reaches_legacy_account_limit(): void
    {
        \DB::table('settings')->insert([
            'setting' => 'max.connections.per.ip',
            'value' => '2',
        ]);
        app(HavanaConfig::class)->reload();

        $ipUserOne = $this->createLegacyUser(['username' => 'IpOne', 'email' => 'ip-one@example.test']);
        $ipUserTwo = $this->createLegacyUser(['username' => 'IpTwo', 'email' => 'ip-two@example.test']);
        \DB::table('users_ip_logs')->insert([
            ['user_id' => $ipUserOne->id, 'ip_address' => '203.0.113.5', 'created_at' => now()],
            ['user_id' => $ipUserTwo->id, 'ip_address' => '203.0.113.5', 'created_at' => now()],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->get('/register')
            ->assertRedirect('/')
            ->assertSessionHas('alertMessage', 'You already have enough accounts registered');

        $this->createLegacyUser([
            'username' => 'MachineOne',
            'email' => 'machine-one@example.test',
            'machine_id' => '#machine-limit',
        ]);
        $this->createLegacyUser([
            'username' => 'MachineTwo',
            'email' => 'machine-two@example.test',
            'machine_id' => '#machine-limit',
        ]);

        $this->withCookie('SECURITY_KEY', 'machine-limit')
            ->get('/register')
            ->assertRedirect('/')
            ->assertSessionHas('alertMessage', 'You already have enough accounts registered');
    }

    public function test_register_rejects_bad_captcha(): void
    {
        $this->withSession(['captcha-text' => 'abc123']);

        $response = $this->post('/register', [
            'bean.avatarName' => 'NewUser',
            'bean.captchaResponse' => 'wrong',
            'retypedPassword' => 'secret123',
            'bean.email' => 'new@example.test',
            'bean.day' => '1',
            'bean.month' => '1',
            'bean.year' => '2000',
            'bean.figure' => 'hd-180-1.hr-100-61.ch-210-66.lg-270-82.sh-290-80',
            'bean.gender' => 'M',
            'terms' => 'true',
            'extra1' => 'x',
            'extra2' => 'x',
        ]);

        $response->assertRedirect('/register?error=bad_captcha');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['username' => 'NewUser']);
    }

    public function test_register_rejects_bad_look_before_creating_user(): void
    {
        $this->withSession(['captcha-text' => 'abc123']);

        $response = $this->post('/register', [
            'bean.avatarName' => 'BadLookUser',
            'bean.captchaResponse' => 'abc123',
            'retypedPassword' => 'secret123',
            'bean.email' => 'bad-look@example.test',
            'bean.day' => '1',
            'bean.month' => '1',
            'bean.year' => '2000',
            'bean.figure' => 'hd-180-1',
            'bean.gender' => 'M',
            'terms' => 'true',
            'extra1' => 'x',
            'extra2' => 'x',
        ]);

        $response->assertRedirect('/register?error=bad_look');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['username' => 'BadLookUser']);
    }

    public function test_register_revalidates_session_email_after_captcha_before_creating_user(): void
    {
        $response = $this->withSession([
            'captcha-text' => 'abc123',
            'registerUsername' => 'SessionEmailUser',
            'registerPassword' => 'secret123',
            'registerFigure' => 'hd-180-1.ch-876-62.lg-280-62',
            'registerGender' => 'M',
            'registerEmail' => 'not-an-email',
            'registerDay' => '1',
            'registerMonth' => '1',
            'registerYear' => '2000',
        ])->post('/register', [
            'bean.captchaResponse' => 'abc123',
            'step' => 'captcha',
            'extra' => 'x',
            'another' => 'x',
        ]);

        $response
            ->assertRedirect('/register?error=bad_email')
            ->assertSessionHas('email.invalid', true);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['username' => 'SessionEmailUser']);
    }

    public function test_password_forgot_redirects_when_email_is_disabled(): void
    {
        $this->get('/account/password/forgot')->assertRedirect('/');
    }

    public function test_password_forgot_name_lookup_matches_legacy_email_validation(): void
    {
        $this->enableEmail();
        $user = $this->createLegacyUser([
            'email' => 'lookup@example.test',
        ]);
        $this->insertStatistics($user->id);

        $this->post('/account/password/forgot', [
            'ownerEmailAddress' => 'not-an-email',
            'actionList' => '1',
        ])
            ->assertOk()
            ->assertSee('Invalid username or e-mail address', false);

        $this->post('/account/password/forgot', [
            'ownerEmailAddress' => 'missing@example.test',
            'actionList' => '1',
        ])
            ->assertOk()
            ->assertSee('Invalid username or e-mail address', false);

        $this->post('/account/password/forgot', [
            'ownerEmailAddress' => 'lookup@example.test',
            'actionList' => '1',
        ])
            ->assertOk()
            ->assertSee('E-Mail sent', false);

        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'forgot_password_code' => null,
            'forgot_recovery_requested_time' => null,
        ]);
    }

    public function test_password_forgot_sets_recovery_code_when_email_is_enabled(): void
    {
        $this->enableEmail();
        $hash = app(LegacyPasswordHasher::class)->make('secret123');
        $user = User::query()->create([
            'username' => 'Alex',
            'password' => $hash,
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => 'alex@example.test',
        ]);
        $this->insertStatistics($user->id);

        Mail::shouldReceive('html')
            ->once()
            ->withArgs(function (string $html, callable $callback): bool {
                return str_contains($html, '/account/password/recovery?id=')
                    && str_contains($html, 'Alex');
            });

        $response = $this->post('/account/password/forgot', [
            'forgottenpw-username' => 'Alex',
            'forgottenpw-email' => 'alex@example.test',
            'actionForgot' => '1',
        ]);

        $response
            ->assertOk()
            ->assertSee('E-Mail sent', false);

        $this->assertDatabaseMissing('users_statistics', [
            'user_id' => $user->id,
            'forgot_password_code' => null,
        ]);
        $this->assertDatabaseMissing('users_statistics', [
            'user_id' => $user->id,
            'forgot_recovery_requested_time' => null,
        ]);
    }

    public function test_password_recovery_updates_password_and_clears_code(): void
    {
        $this->enableEmail();
        $hash = app(LegacyPasswordHasher::class)->make('secret123');
        $user = User::query()->create([
            'username' => 'Alex',
            'password' => $hash,
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => 'alex@example.test',
        ]);
        $this->insertStatistics($user->id, ['forgot_password_code' => 'recover-me']);

        $response = $this->post('/account/password/recovery', [
            'user_id' => $user->id,
            'recovery_code' => 'recover-me',
            'password' => 'newsecret',
            'confirmpassword' => 'newsecret',
        ]);

        $response
            ->assertOk()
            ->assertSee('Your password has been changed successfully.', false)
            ->assertSessionMissing('alertMessage')
            ->assertSessionMissing('alertColour');

        $user->refresh();
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('newsecret', $user->password));
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'forgot_password_code' => null,
        ]);
    }

    public function test_password_recovery_validation_errors_preserve_recovery_code(): void
    {
        $this->enableEmail();
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id, ['forgot_password_code' => 'recover-me']);

        $this->post('/account/password/recovery', [
            'user_id' => $user->id,
            'recovery_code' => 'recover-me',
            'password' => 'newsecret',
            'confirmpassword' => 'different',
        ])
            ->assertOk()
            ->assertSee("The passwords don't match", false)
            ->assertSessionMissing('alertMessage')
            ->assertSessionMissing('alertColour');

        $user->refresh();
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('secret123', $user->password));
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'forgot_password_code' => 'recover-me',
        ]);

        $this->post('/account/password/recovery', [
            'user_id' => $user->id,
            'recovery_code' => 'recover-me',
            'password' => 'short',
            'confirmpassword' => 'short',
        ])
            ->assertOk()
            ->assertSee('Password is too short, 6 characters minimum', false)
            ->assertSessionMissing('alertMessage')
            ->assertSessionMissing('alertColour');

        $user->refresh();
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('secret123', $user->password));
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'forgot_password_code' => 'recover-me',
        ]);
    }

    public function test_password_recovery_clears_invalid_code_alert_after_render(): void
    {
        $this->enableEmail();

        $response = $this->get('/account/password/recovery?id=404&code=missing');

        $response
            ->assertOk()
            ->assertSee('The recovery code was invalid', false)
            ->assertSessionMissing('alertMessage')
            ->assertSessionMissing('alertColour');
    }

    public function test_account_activation_clears_activation_code(): void
    {
        $this->enableEmail();
        $hash = app(LegacyPasswordHasher::class)->make('secret123');
        $user = User::query()->create([
            'username' => 'Alex',
            'password' => $hash,
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => 'alex@example.test',
        ]);
        $this->insertStatistics($user->id, ['activation_code' => 'activate-me']);

        $response = $this->get('/account/activate?id='.$user->id.'&code=activate-me');

        $response
            ->assertOk()
            ->assertSee('Your email address is now verified', false);

        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'activation_code' => null,
        ]);
    }

    public function test_account_activation_renders_invalid_link_without_clearing_code(): void
    {
        $this->enableEmail();
        $user = $this->createLegacyUser([
            'email' => 'activation-invalid@example.test',
        ]);
        $this->insertStatistics($user->id, ['activation_code' => 'activate-me']);

        $this->get('/account/activate')
            ->assertOk()
            ->assertSee('The activation link was invalid', false);

        $this->get('/account/activate?id='.$user->id.'&code=wrong-code')
            ->assertOk()
            ->assertSee('The activation link was invalid', false);

        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'activation_code' => 'activate-me',
        ]);
    }

    public function test_profile_redirects_guest_to_homepage(): void
    {
        $this->get('/profile')->assertRedirect('/');
    }

    public function test_profile_preferences_tab_renders_legacy_template(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile?tab=2');

        $response
            ->assertOk()
            ->assertSee('profile/profileupdate', false)
            ->assertSee('avatarmotto', false);
    }

    public function test_profile_change_looks_redirects_non_club_users(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile')
            ->assertRedirect('/');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile?tab=1')
            ->assertRedirect('/');
    }

    public function test_profile_change_looks_renders_stored_wardrobe_slots(): void
    {
        $user = $this->createLegacyUser();
        $user->forceFill(['club_expiration' => time() + 86400])->save();
        $this->insertStatistics($user->id);
        \DB::table('users_wardrobes')->insert([
            'user_id' => $user->id,
            'slot_id' => 2,
            'figure' => 'hr-100-61.hd-180-1.ch-210-66.lg-270-82.sh-290-80',
            'sex' => 'f',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Wardrobe.add(2, "hr-100-61.hd-180-1.ch-210-66.lg-270-82.sh-290-80", "F", true);', false)
            ->assertSee('http://localhost/habbo-imaging/avatarimage?figure=hr-100-61.hd-180-1.ch-210-66.lg-270-82.sh-290-80&size=s&direction=4&head_direction=4&crr=0&gesture=sml&frame=1', false);
    }

    public function test_profile_friend_management_tab_renders_legacy_friend_context(): void
    {
        $user = $this->createLegacyUser();
        $friend = $this->createLegacyUser([
            'username' => 'ManagedFriend',
            'email' => 'managed-friend@example.test',
            'last_online' => '2026-06-29 15:45:00',
        ]);
        $staleFriend = $this->createLegacyUser([
            'username' => 'StaleCategoryFriend',
            'email' => 'stale-category-friend@example.test',
            'last_online' => '2026-06-28 11:30:00',
        ]);
        $this->insertStatistics($user->id);
        $this->insertStatistics($friend->id);
        $this->insertStatistics($staleFriend->id);
        $categoryId = \DB::table('messenger_categories')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Best Friends',
        ]);
        \DB::table('messenger_friends')->insert([
            [
                'from_id' => $friend->id,
                'to_id' => $user->id,
                'category_id' => $categoryId,
            ],
            [
                'from_id' => $staleFriend->id,
                'to_id' => $user->id,
                'category_id' => 999,
            ],
        ]);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile?tab=5');

        $response
            ->assertOk()
            ->assertSee('friend-management-container', false)
            ->assertSee('Best Friends', false)
            ->assertSee('ManagedFriend', false)
            ->assertSee('StaleCategoryFriend', false)
            ->assertSee('new FriendManagement({ currentCategoryId: 0, pageListLimit: 30, pageNumber: 1});', false);

        $this->assertDatabaseHas('messenger_friends', [
            'from_id' => $staleFriend->id,
            'to_id' => $user->id,
            'category_id' => 0,
        ]);
    }

    public function test_profile_club_route_renders_legacy_membership_context(): void
    {
        $this->get('/club')->assertRedirect('/');

        $user = $this->createLegacyUser([
            'username' => 'ClubProfileUser',
        ]);
        $user->forceFill(['club_expiration' => time() + (4 * 86400)])->save();
        $this->insertStatistics($user->id, [
            'club_member_time' => 62 * 86400,
        ]);
        \DB::table('items_definitions')->insert([
            'sprite' => 'mocchamaster',
            'name' => 'Moccha Master',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'lastClubGiftMonth' => 5,
            ])
            ->get('/club');

        $response
            ->assertOk()
            ->assertSee('href="http://localhost/me"', false)
            ->assertSee('href="http://localhost/profile"', false)
            ->assertSee('You have 4 Habbo Club day(s) left', false)
            ->assertSee('You have been a member for 2 month(s)', false)
            ->assertSee('Purchase 31 days', false)
            ->assertSee('Purchase 93 days', false)
            ->assertSee('Purchase 186 days', false)
            ->assertSee('#5', false)
            ->assertSee('mocchamaster.png', false)
            ->assertSee('Moccha Master', false)
            ->assertSessionHas('page', 'me')
            ->assertSessionHas('lastClubGiftMonth', 5);
    }

    public function test_profile_update_saves_legacy_preferences(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/profileupdate', [
                'motto' => 'This motto is deliberately longer than thirty-two characters',
                'visibility' => 'NOBODY',
                'showOnlineStatus' => 'false',
                'wordFilterSetting' => 'false',
                'allowFriendRequests' => 'true',
                'followFriendSetting' => 'false',
            ]);

        $response->assertRedirect('/profile?tab=2');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'motto' => 'This motto is deliberately longe',
            'profile_visible' => false,
            'online_status_visible' => false,
            'wordfilter_enabled' => false,
            'allow_friend_requests' => true,
            'allow_stalking' => false,
        ]);
    }

    public function test_profile_action_routes_accept_legacy_get_requests(): void
    {
        $user = $this->createLegacyUser([
            'motto' => 'Keep me',
            'profile_visible' => true,
            'online_status_visible' => true,
            'wordfilter_enabled' => false,
            'allow_friend_requests' => true,
            'allow_stalking' => true,
        ]);
        $this->insertStatistics($user->id, ['activation_code' => 'old-code']);

        $session = ['authenticated' => true, 'user.id' => $user->id, 'captcha-text' => 'abc123'];

        $this->actingAs($user)
            ->withSession($session)
            ->get('/profile/passwordupdate')
            ->assertOk()
            ->assertSee('Please enter all fields', false)
            ->assertSessionMissing('captcha-text');

        $this->actingAs($user)
            ->withSession($session)
            ->get('/profile/emailupdate')
            ->assertRedirect('/profile?tab=3')
            ->assertSessionHas('alertMessage', 'Please enter all fields')
            ->assertSessionMissing('captcha-text');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile/characterupdate')
            ->assertRedirect('/profile');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile/wardrobeStore')
            ->assertRedirect('/');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile/securitysettingupdate')
            ->assertRedirect('/profile?tab=6')
            ->assertSessionHas('alertMessage', 'You did not enter a password');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile/send_email')
            ->assertRedirect('/profile/verify');

        $this->assertDatabaseMissing('users_statistics', [
            'user_id' => $user->id,
            'activation_code' => 'old-code',
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/profile/profileupdate')
            ->assertRedirect('/profile?tab=2')
            ->assertSessionHas('settings.saved.successfully', 'true');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'motto' => '',
            'profile_visible' => false,
            'online_status_visible' => false,
            'wordfilter_enabled' => true,
            'allow_friend_requests' => false,
            'allow_stalking' => false,
        ]);
    }

    public function test_profile_password_update_changes_password_and_logs_out(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'captcha-text' => 'abc123',
            ])
            ->post('/profile/passwordupdate', [
                'currentpassword' => 'secret123',
                'newpassword' => 'newsecret',
                'newpasswordconfirm' => 'newsecret',
                'captcha' => 'abc123',
            ]);

        $response->assertOk();
        $this->assertGuest();

        $user->refresh();
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('newsecret', $user->password));
    }

    public function test_profile_password_update_validation_errors_preserve_password(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'captcha-text' => 'abc123',
            ])
            ->post('/profile/passwordupdate', [
                'currentpassword' => 'secret123',
                'newpassword' => 'short',
                'newpasswordconfirm' => 'short',
                'captcha' => 'abc123',
            ])
            ->assertOk()
            ->assertSee('Password is too short, 6 characters minimum', false)
            ->assertSessionMissing('alertMessage')
            ->assertSessionMissing('alertColour')
            ->assertSessionMissing('captcha-text');

        $user->refresh();
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('secret123', $user->password));
        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'captcha-text' => 'abc123',
            ])
            ->post('/profile/passwordupdate', [
                'currentpassword' => 'secret123',
                'newpassword' => 'newsecret',
                'newpasswordconfirm' => 'different',
                'captcha' => 'abc123',
            ])
            ->assertOk()
            ->assertSee("The passwords don't match", false)
            ->assertSessionMissing('alertMessage')
            ->assertSessionMissing('alertColour')
            ->assertSessionMissing('captcha-text');

        $user->refresh();
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('secret123', $user->password));
        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'captcha-text' => 'abc123',
            ])
            ->post('/profile/passwordupdate', [
                'currentpassword' => 'secret123',
                'newpassword' => 'newsecret',
                'newpasswordconfirm' => 'newsecret',
                'captcha' => 'wrong',
            ])
            ->assertOk()
            ->assertSee('The security code was invalid, please try again.', false)
            ->assertSessionMissing('alertMessage')
            ->assertSessionMissing('alertColour')
            ->assertSessionMissing('captcha-text');

        $user->refresh();
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('secret123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_profile_wardrobe_store_returns_legacy_avatar_url(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/wardrobeStore', [
                'slot' => '3',
                'figure' => 'hd-180-1.ch-876-62.lg-280-62',
                'gender' => 'm',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('slot', '3')
            ->assertJsonPath('u', 'http://localhost/habbo-imaging/avatarimage?figure=hd-180-1.ch-876-62.lg-280-62&size=s&direction=4&head_direction=4&crr=0&gesture=sml&frame=1')
            ->assertJsonPath('f', 'hd-180-1.ch-876-62.lg-280-62')
            ->assertJsonPath('g', 77);

        $this->assertDatabaseHas('users_wardrobes', [
            'user_id' => $user->id,
            'slot_id' => 3,
            'figure' => 'hd-180-1.ch-876-62.lg-280-62',
            'sex' => 'M',
        ]);
    }

    public function test_profile_wardrobe_store_rejects_invalid_legacy_inputs(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        foreach ([
            ['slot' => '3abc', 'figure' => 'hd-180-1.ch-876-62.lg-280-62', 'gender' => 'F'],
            ['slot' => '6', 'figure' => 'hd-180-1.ch-876-62.lg-280-62', 'gender' => 'F'],
            ['slot' => '3', 'figure' => 'bad-999-1', 'gender' => 'F'],
            ['slot' => '3', 'figure' => 'hd-180-1.ch-876-62.lg-280-62', 'gender' => 'X'],
        ] as $payload) {
            $this->actingAs($user)
                ->withSession(['authenticated' => true, 'user.id' => $user->id])
                ->post('/profile/wardrobeStore', $payload)
                ->assertRedirect('/');
        }

        $this->assertDatabaseMissing('users_wardrobes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_security_setting_update_toggles_trade_setting(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/securitysettingupdate', [
                'password' => 'secret123',
                'tradingsetting' => 'true',
            ]);

        $response->assertRedirect('/profile?tab=6');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'trade_enabled' => true,
        ]);
    }

    public function test_security_setting_update_validation_blocks_trade_changes(): void
    {
        $user = $this->createLegacyUser([
            'email' => 'trade-user@example.test',
        ]);
        $this->insertStatistics($user->id, ['activation_code' => null]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/securitysettingupdate', [
                'password' => '',
                'tradingsetting' => 'true',
            ])
            ->assertRedirect('/profile?tab=6')
            ->assertSessionHas('alertMessage', 'You did not enter a password')
            ->assertSessionHas('alertColour', 'red');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'trade_enabled' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/securitysettingupdate', [
                'password' => 'wrong',
                'tradingsetting' => 'true',
            ])
            ->assertRedirect('/profile?tab=6')
            ->assertSessionHas('alertMessage', 'Your current password is invalid')
            ->assertSessionHas('alertColour', 'red');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'trade_enabled' => false,
        ]);

        \DB::table('settings')->insert([
            'setting' => 'trade.email.verification',
            'value' => 'true',
        ]);
        app(HavanaConfig::class)->reload();
        \DB::table('users_statistics')->where('user_id', $user->id)->update([
            'activation_code' => 'verify-first',
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/securitysettingupdate', [
                'password' => 'secret123',
                'tradingsetting' => 'true',
            ])
            ->assertRedirect('/profile?tab=6')
            ->assertSessionHas('alertMessage', 'You must verify your email before enabling trade.')
            ->assertSessionHas('alertColour', 'red');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'trade_enabled' => false,
        ]);
    }

    public function test_security_setting_update_blocks_duplicate_trade_pass_email(): void
    {
        $user = $this->createLegacyUser([
            'email' => 'shared-trade@example.test',
        ]);
        $other = $this->createLegacyUser([
            'username' => 'OtherTradeUser',
            'email' => 'shared-trade@example.test',
        ]);
        $other->forceFill(['trade_enabled' => true])->save();
        $this->insertStatistics($user->id, ['activation_code' => null]);
        $this->insertStatistics($other->id, ['activation_code' => null]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/securitysettingupdate', [
                'password' => 'secret123',
                'tradingsetting' => 'true',
            ])
            ->assertRedirect('/profile?tab=6')
            ->assertSessionHas('alertMessage', 'This email is already used for a trade pass.')
            ->assertSessionHas('alertColour', 'red');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'trade_enabled' => false,
        ]);
    }

    public function test_profile_email_update_does_not_persist_when_activation_mail_fails(): void
    {
        $this->enableEmail();
        \DB::table('settings')->insert([
            'setting' => 'trade.email.verification',
            'value' => 'true',
        ]);
        app(HavanaConfig::class)->reload();
        $this->assertTrue(app(HavanaConfig::class)->boolean('email.smtp.enable'));
        Mail::shouldReceive('html')->andThrow(new \RuntimeException('mail failed'));

        $user = $this->createLegacyUser([
            'email' => 'old@example.test',
        ]);
        $user->forceFill(['trade_enabled' => true])->save();
        $this->insertStatistics($user->id, ['activation_code' => 'old-code']);

        $response = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'captcha-text' => 'abc123',
            ])
            ->post('/profile/emailupdate', [
                'password' => 'secret123',
                'email' => 'new@example.test',
                'captcha' => 'abc123',
            ]);

        $response->assertRedirect('/profile?tab=3');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.test',
            'trade_enabled' => true,
        ]);
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'activation_code' => 'old-code',
        ]);
    }

    public function test_profile_email_update_sends_activation_and_disables_trade_when_required(): void
    {
        $this->enableEmail();
        \DB::table('settings')->insert([
            'setting' => 'trade.email.verification',
            'value' => 'true',
        ]);
        app(HavanaConfig::class)->reload();

        Mail::shouldReceive('html')
            ->once()
            ->withArgs(function (string $html, callable $callback): bool {
                return str_contains($html, '/account/activate?id=')
                    && str_contains($html, 'EmailChangeUser')
                    && str_contains($html, 'new@example.test');
            });

        $user = $this->createLegacyUser([
            'username' => 'EmailChangeUser',
            'email' => 'old@example.test',
        ]);
        $user->forceFill(['trade_enabled' => true])->save();
        $this->insertStatistics($user->id, ['activation_code' => 'old-code']);

        $response = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'captcha-text' => 'abc123',
            ])
            ->post('/profile/emailupdate', [
                'password' => 'secret123',
                'email' => 'new@example.test',
                'captcha' => 'abc123',
            ]);

        $response->assertRedirect('/profile?tab=3')
            ->assertSessionHas('alertMessage', 'Your email has been changed successfully.')
            ->assertSessionMissing('captcha-text');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.test',
            'trade_enabled' => false,
        ]);
        $this->assertDatabaseMissing('users_statistics', [
            'user_id' => $user->id,
            'activation_code' => 'old-code',
        ]);
        $this->assertNotNull(\DB::table('users_statistics')
            ->where('user_id', $user->id)
            ->value('activation_code'));
    }

    public function test_profile_character_update_requires_legacy_figure_and_gender_fields(): void
    {
        $user = $this->createLegacyUser([
            'figure' => 'hd-180-1',
            'sex' => 'M',
        ]);
        $this->insertStatistics($user->id);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/characterupdate', [
                'figureData' => 'hd-190-1',
            ])
            ->assertRedirect('/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'figure' => 'hd-180-1',
            'sex' => 'M',
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/characterupdate', [
                'figureData' => 'bad-999-1',
                'newGender' => 'M',
            ])
            ->assertRedirect('/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'figure' => 'hd-180-1',
            'sex' => 'M',
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/characterupdate', [
                'figureData' => 'hd-180-1.ch-876-62.lg-280-62',
                'newGender' => 'M',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHas('settings.saved.successfully', 'true');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'figure' => 'hd-180-1.ch-876-62.lg-280-62',
            'sex' => 'M',
        ]);
    }

    public function test_send_email_refreshes_activation_code(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id, ['activation_code' => 'old-code']);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/send_email');

        $response->assertRedirect('/profile/verify');

        $this->assertDatabaseMissing('users_statistics', [
            'user_id' => $user->id,
            'activation_code' => 'old-code',
        ]);
    }

    public function test_send_email_renders_legacy_activation_email_template(): void
    {
        $this->enableEmail();
        $user = $this->createLegacyUser([
            'username' => 'EmailUser',
            'email' => 'email-user@example.test',
        ]);
        $this->insertStatistics($user->id, ['activation_code' => 'old-code']);

        Mail::shouldReceive('html')
            ->once()
            ->withArgs(function (string $html, callable $callback): bool {
                return str_contains($html, '/account/activate?id=')
                    && str_contains($html, 'EmailUser')
                    && str_contains($html, 'email-user@example.test');
            });

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/profile/send_email');

        $response->assertRedirect('/profile/verify');

        $this->assertDatabaseMissing('users_statistics', [
            'user_id' => $user->id,
            'activation_code' => 'old-code',
        ]);
    }

    public function test_client_redirects_guest_to_login_popup(): void
    {
        $this->get('/client')->assertRedirect('/login_popup');
    }

    public function test_client_redirects_authenticated_user_to_shockwave_with_query(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/client?forwardId=2&roomId=7');

        $response->assertRedirect('/shockwave_client?forwardId=2&roomId=7');
    }

    public function test_client_routes_clear_legacy_xss_challenge_keys(): void
    {
        $this->withSession([
            'xssSeed' => 12345,
            'xssKey' => 1553932502,
            'xssRequested' => '/credits',
        ])
            ->get('/client')
            ->assertRedirect('/login_popup')
            ->assertSessionMissing('xssSeed')
            ->assertSessionMissing('xssKey')
            ->assertSessionMissing('xssRequested');

        $user = $this->createLegacyUser(['sso_ticket' => 'xss-clear-ticket']);
        $this->insertStatistics($user->id);

        foreach ([
            '/shockwave_client',
            '/flash_client',
            '/client_popup/install_shockwave',
            '/client_error?error_id=1',
            '/client_connection_failed?error_id=2',
        ] as $path) {
            $this->actingAs($user)
                ->withSession([
                    'authenticated' => true,
                    'user.id' => $user->id,
                    'xssSeed' => 12345,
                    'xssKey' => 1553932502,
                    'xssRequested' => '/credits',
                ])
                ->get($path);

            $this->assertFalse(session()->has('xssSeed'), $path);
            $this->assertFalse(session()->has('xssKey'), $path);
            $this->assertFalse(session()->has('xssRequested'), $path);
        }
    }

    public function test_shockwave_client_renders_and_persists_sso_ticket(): void
    {
        $user = $this->createLegacyUser(['sso_ticket' => null]);
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/shockwave_client?forwardId=2&roomId=7');

        $response
            ->assertOk()
            ->assertSee('HabboClientUtils', false)
            ->assertSee('forward.type=2;forward.id=7;processlog.url=', false);

        $user->refresh();
        $this->assertNotEmpty($user->sso_ticket);
        $response->assertSee('sso.ticket='.$user->sso_ticket, false);
    }

    public function test_shockwave_client_rotates_existing_sso_when_configured(): void
    {
        \DB::table('settings')->insert([
            ['setting' => 'reset.sso.after.login', 'value' => 'true'],
        ]);
        app(HavanaConfig::class)->reload();

        $user = $this->createLegacyUser(['sso_ticket' => 'old-client-ticket']);
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/shockwave_client');

        $response->assertOk();
        $user->refresh();
        $this->assertNotSame('old-client-ticket', $user->sso_ticket);
        $this->assertNotEmpty($user->sso_ticket);
        $response->assertSee('sso.ticket='.$user->sso_ticket, false);
    }

    public function test_shockwave_client_invalid_forward_params_keep_legacy_defaults(): void
    {
        $user = $this->createLegacyUser(['sso_ticket' => 'existing-ticket']);
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/shockwave_client?forwardId=abc&roomId=xyz');

        $response
            ->assertOk()
            ->assertSee('forward.type=-1;forward.id=-1;processlog.url=', false);
    }

    public function test_shockwave_client_create_room_seeds_starter_room_and_newbie_gift(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'StarterUser',
            'sso_ticket' => 'starter-ticket',
            'selected_room_id' => 0,
        ]);
        $this->insertStatistics($user->id, [
            'newbie_room_layout' => 0,
            'newbie_gift' => 0,
            'newbie_gift_time' => 0,
        ]);
        \DB::table('items_definitions')->insert([
            [
                'id' => 951,
                'sprite' => 'noob_stool*3',
                'name' => 'Starter Stool 3',
                'description' => 'Starter stool',
            ],
            [
                'id' => 952,
                'sprite' => 'noob_table*3',
                'name' => 'Starter Table 3',
                'description' => 'Starter table',
            ],
            [
                'id' => 953,
                'sprite' => 'noob_window_double',
                'name' => 'Starter Window',
                'description' => 'Starter window',
            ],
        ]);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/shockwave_client?createRoom=2');

        $response
            ->assertOk()
            ->assertSee('forward.type=2;', false);

        $roomId = (int) \DB::table('users')->where('id', $user->id)->value('selected_room_id');
        $this->assertGreaterThan(0, $roomId);
        $response->assertSee('forward.id='.$roomId.';processlog.url=', false);
        $this->assertDatabaseHas('rooms', [
            'id' => $roomId,
            'owner_id' => (string) $user->id,
            'name' => "StarterUser's Room",
            'description' => 'StarterUser has entered the building',
            'model' => 'model_s',
            'wallpaper' => 1901,
            'floor' => 301,
        ]);
        $this->assertDatabaseHas('items', [
            'user_id' => $user->id,
            'room_id' => $roomId,
            'definition_id' => 951,
            'x' => 2,
            'y' => 2,
            'rotation' => 4,
        ]);
        $this->assertDatabaseHas('items', [
            'user_id' => $user->id,
            'room_id' => 0,
            'definition_id' => 952,
        ]);
        $this->assertDatabaseHas('items', [
            'user_id' => $user->id,
            'room_id' => $roomId,
            'definition_id' => 953,
            'wall_position' => ':w=3,0 l=13,71 r',
        ]);
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'newbie_room_layout' => 3,
            'newbie_gift' => 1,
        ]);
        $this->assertGreaterThan(time(), (int) \DB::table('users_statistics')->where('user_id', $user->id)->value('newbie_gift_time'));
    }

    public function test_shockwave_client_ignores_non_numeric_create_room(): void
    {
        $user = $this->createLegacyUser([
            'sso_ticket' => 'starter-ticket',
            'selected_room_id' => 0,
        ]);
        $this->insertStatistics($user->id, [
            'newbie_room_layout' => 0,
            'newbie_gift' => 0,
            'newbie_gift_time' => 0,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/shockwave_client?createRoom=abc');

        $response
            ->assertOk()
            ->assertDontSee('forward.type=', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'selected_room_id' => 0,
        ]);
        $this->assertSame(0, \DB::table('rooms')->count());
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'newbie_room_layout' => 0,
            'newbie_gift' => 0,
        ]);
    }

    public function test_flash_client_renders_existing_sso_ticket(): void
    {
        $user = $this->createLegacyUser(['sso_ticket' => 'existing-ticket']);
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/flash_client');

        $response
            ->assertOk()
            ->assertSee('"sso.ticket": "existing-ticket"', false)
            ->assertSee('Habbo.swf', false);
    }

    public function test_client_requires_reauthentication_when_session_is_stale(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'clientAuthenticate' => true,
            ])
            ->get('/shockwave_client');

        $response->assertRedirect('/account/reauthenticate');
        $this->assertSame('/shockwave_client', session('clientRequest'));
    }

    public function test_client_routes_redirect_active_banned_users_to_banned_page(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'ClientBanned',
            'email' => 'client-banned@example.test',
        ]);
        $this->insertStatistics($user->id);
        \DB::table('users_bans')->insert([
            'ban_type' => 'USER_ID',
            'banned_value' => (string) $user->id,
            'message' => 'Client route ban',
            'banned_until' => now()->addDay(),
            'banned_at' => now(),
            'banned_by' => 0,
            'is_active' => true,
        ]);

        foreach ([
            '/shockwave_client',
            '/flash_client',
            '/client_popup/install_shockwave',
            '/client_error?error_id=1',
            '/client_connection_failed?error_id=2',
        ] as $path) {
            $this->actingAs($user)
                ->withSession(['authenticated' => true, 'user.id' => $user->id])
                ->get($path)
                ->assertRedirect('/account/banned');
        }
    }

    public function test_reauthenticate_redirects_back_to_client_request(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'clientAuthenticate' => true,
                'clientRequest' => '/shockwave_client?forwardId=2&roomId=7',
            ])
            ->post('/account/reauthenticate', ['password' => 'secret123']);

        $response->assertRedirect('/shockwave_client?forwardId=2&roomId=7');
        $this->assertFalse(session('clientAuthenticate'));
    }

    public function test_client_blank_and_count_endpoints_match_legacy_shape(): void
    {
        \DB::table('settings')->insert([
            ['setting' => 'hotel.check.online', 'value' => 'false'],
            ['setting' => 'players.online', 'value' => '1234'],
        ]);
        app(HavanaConfig::class)->reload();
        app(HotelStatus::class)->clearCache();

        $this->get('/clientlog/update')->assertOk()->assertContent('');
        $this->get('/cacheCheck')->assertOk()->assertContent('');

        $response = $this->get('/components/updateHabboCount');

        $response->assertOk();
        $this->assertSame('{"habboCountText":"1,234 members online"}', $response->headers->get('X-JSON'));
    }

    public function test_namecheck_returns_legacy_x_json_messages(): void
    {
        $this->createLegacyUser(['username' => 'Taken']);

        $taken = $this->post('/habblet/ajax/namecheck', ['name' => 'Taken']);
        $taken->assertOk()->assertContent('');
        $this->assertSame(
            '{"registration_name":"A user with this name already exists."}',
            $taken->headers->get('X-JSON'),
        );

        $valid = $this->post('/habblet/ajax/namecheck', ['name' => 'FreshName']);
        $valid->assertOk()->assertContent('');
        $this->assertSame('{"registration_name":""}', $valid->headers->get('X-JSON'));
    }

    public function test_update_motto_saves_filtered_motto_and_returns_escaped_text(): void
    {
        $user = $this->createLegacyUser(['motto' => 'Old']);
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/updatemotto', [
                'motto' => '<b>New motto</b>',
            ]);

        $response->assertOk()->assertSee('New motto', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'motto' => 'New motto',
        ]);
    }

    public function test_update_motto_empty_text_returns_legacy_placeholder(): void
    {
        $user = $this->createLegacyUser(['motto' => 'Old']);
        $this->insertStatistics($user->id);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/updatemotto', ['motto' => '   ']);

        $response
            ->assertOk()
            ->assertSee('Click to enter your motto/ status', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'motto' => '',
        ]);
    }

    public function test_roomselection_confirm_renders_legacy_template(): void
    {
        $this->get('/habblet/ajax/roomselectionConfirm')
            ->assertOk()
            ->assertSee('roomselection-hide', false);
    }

    public function test_roomselection_hide_sets_selected_room_and_newbie_layout_to_hidden(): void
    {
        $user = $this->createLegacyUser(['selected_room_id' => 0]);
        $this->insertStatistics($user->id, ['newbie_room_layout' => 0]);

        $response = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/roomselectionHide');

        $response->assertOk()->assertContent('');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'selected_room_id' => -1,
        ]);
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'newbie_room_layout' => -1,
        ]);
    }

    public function test_nextgift_renders_and_giftqueue_hide_advances_completed_queue(): void
    {
        $user = $this->createLegacyUser(['selected_room_id' => 12]);
        $this->insertStatistics($user->id, [
            'newbie_room_layout' => 2,
            'newbie_gift' => 1,
            'newbie_gift_time' => time() - 60,
        ]);
        \DB::table('items_definitions')->insert([
            [
                'id' => 901,
                'sprite' => 'present_gen',
                'name' => 'Wrapped present',
                'description' => 'A wrapped gift',
            ],
            [
                'id' => 902,
                'sprite' => 'noob_stool*2',
                'name' => 'Starter Stool',
                'description' => 'First starter gift',
            ],
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/habblet/ajax/nextgift')
            ->assertOk()
            ->assertSee('client?forwardId=2&roomId=12', false)
            ->assertSee('noob_plant.png', false);
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'newbie_gift' => 2,
        ]);
        $this->assertGreaterThan(time(), (int) \DB::table('users_statistics')->where('user_id', $user->id)->value('newbie_gift_time'));
        $this->assertDatabaseHas('cms_alerts', [
            'user_id' => $user->id,
            'alert_type' => 'PRESENT',
            'message' => 'A new gift has arrived. This time you received a Starter Stool.',
        ]);
        $this->assertDatabaseHas('items', [
            'user_id' => $user->id,
            'definition_id' => 901,
        ]);

        \DB::table('users_statistics')->where('user_id', $user->id)->update([
            'newbie_gift' => 3,
            'newbie_gift_time' => time() + 60,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/giftqueueHide')
            ->assertOk()
            ->assertContent('');

        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'newbie_gift' => 4,
        ]);
    }

    public function test_room_navigation_component_is_legacy_blank_response(): void
    {
        $this->get('/components/roomNavigation')->assertOk()->assertContent('');
    }

    public function test_add_and_remove_myhabbo_tags_use_users_tags_table(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/myhabbo/tag/add', ['tagName' => 'Retro'])
            ->assertOk()
            ->assertContent('valid');

        $this->assertDatabaseHas('users_tags', [
            'user_id' => $user->id,
            'tag' => 'retro',
            'room_id' => '0',
            'group_id' => '0',
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/habblet/mytagslist')
            ->assertOk()
            ->assertSee('retro', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/myhabbo/tag/remove', ['tagName' => 'retro'])
            ->assertOk()
            ->assertSee('profile-tags-container', false);

        $this->assertDatabaseMissing('users_tags', [
            'user_id' => $user->id,
            'tag' => 'retro',
        ]);
    }

    public function test_add_tag_rejects_invalid_tag_and_enforces_limit(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/myhabbo/tag/add', ['tagName' => 'bad tag!'])
            ->assertOk()
            ->assertContent('invalidtag');

        for ($index = 0; $index < 8; $index++) {
            \DB::table('users_tags')->insert([
                'user_id' => $user->id,
                'tag' => 'tag'.$index,
                'room_id' => '0',
                'group_id' => '0',
                'created_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/myhabbo/tag/add', ['tagName' => 'ninth'])
            ->assertOk()
            ->assertContent('taglimit');
    }

    public function test_tagfight_renders_winner_from_tag_counts(): void
    {
        \DB::table('users_tags')->insert([
            ['user_id' => 1, 'tag' => 'alpha', 'room_id' => '0', 'group_id' => '0', 'created_at' => now()],
            ['user_id' => 2, 'tag' => 'beta', 'room_id' => '0', 'group_id' => '0', 'created_at' => now()],
            ['user_id' => 3, 'tag' => 'beta', 'room_id' => '0', 'group_id' => '0', 'created_at' => now()],
        ]);

        $this->post('/habblet/ajax/tagfight', [
            'tag1' => 'alpha',
            'tag2' => 'beta',
        ])
            ->assertOk()
            ->assertSee('The winner is:', false)
            ->assertSee('tagfight_end_1.gif', false);
    }

    public function test_tagmatch_only_errors_when_submitted_name_is_not_a_friend(): void
    {
        $user = $this->createLegacyUser(['username' => 'TagMatcher']);
        $friend = $this->createLegacyUser([
            'username' => 'MatchFriend',
            'email' => 'matchfriend@example.test',
        ]);
        $this->insertStatistics($user->id);
        $this->insertStatistics($friend->id);
        \DB::table('messenger_friends')->insert([
            ['from_id' => $user->id, 'to_id' => $friend->id, 'category_id' => 0],
            ['from_id' => $friend->id, 'to_id' => $user->id, 'category_id' => 0],
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/tagmatch', ['friendName' => 'MatchFriend'])
            ->assertOk()
            ->assertDontSee('tag-match-error', false)
            ->assertDontSee('Friend not found. Are you sure that they really exist?', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/tagmatch', ['friendName' => 'MissingFriend'])
            ->assertOk()
            ->assertSee('tag-match-error', false)
            ->assertSee('Friend not found. Are you sure that they really exist?', false);
    }

    public function test_redeemvoucher_applies_credit_voucher_and_records_history(): void
    {
        $user = $this->createLegacyUser(['credits' => 50]);
        $this->insertStatistics($user->id);
        \DB::table('vouchers')->insert([
            'voucher_code' => 'FREE100',
            'credits' => 100,
            'expiry_date' => null,
            'is_single_use' => true,
            'allow_new_users' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/redeemvoucher', ['voucherCode' => 'FREE100'])
            ->assertOk()
            ->assertSee('redeem-success', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits' => 150,
        ]);
        $this->assertDatabaseHas('vouchers_history', [
            'voucher_code' => 'FREE100',
            'user_id' => $user->id,
            'credits_redeemed' => 100,
            'items_redeemed' => null,
        ]);
        $this->assertDatabaseMissing('vouchers', [
            'voucher_code' => 'FREE100',
        ]);
    }

    public function test_redeemvoucher_blocks_new_accounts_and_redeems_item_vouchers(): void
    {
        $newUser = $this->createLegacyUser(['username' => 'NewVoucherUser']);
        $this->insertStatistics($newUser->id, ['online_time' => 0]);
        \DB::table('vouchers')->insert([
            'voucher_code' => 'VETERAN25',
            'credits' => 25,
            'expiry_date' => null,
            'is_single_use' => true,
            'allow_new_users' => false,
        ]);

        $this->actingAs($newUser)
            ->withSession(['authenticated' => true, 'user.id' => $newUser->id])
            ->post('/habblet/ajax/redeemvoucher', ['voucherCode' => 'VETERAN25'])
            ->assertOk()
            ->assertSee('redeem-error', false)
            ->assertSee('too new', false);

        $this->assertDatabaseHas('users', [
            'id' => $newUser->id,
            'credits' => 50,
        ]);
        $this->assertDatabaseMissing('vouchers_history', [
            'voucher_code' => 'VETERAN25',
            'user_id' => $newUser->id,
        ]);
        $this->assertDatabaseMissing('vouchers', [
            'voucher_code' => 'VETERAN25',
        ]);

        $itemUser = $this->createLegacyUser([
            'username' => 'ItemVoucherUser',
            'email' => 'item-voucher@example.test',
        ]);
        $this->insertStatistics($itemUser->id, ['online_time' => 7200]);
        \DB::table('items_definitions')->insert([
            'id' => 901,
            'sprite' => 'rare_lamp',
            'name' => 'Rare Lamp',
            'description' => 'Voucher rare',
        ]);
        \DB::table('catalogue_items')->insert([
            'id' => 701,
            'sale_code' => 'rare_lamp',
            'page_id' => '83',
            'order_id' => 6,
            'price_coins' => 0,
            'price_pixels' => 0,
            'amount' => 2,
            'definition_id' => 901,
        ]);
        \DB::table('vouchers')->insert([
            'voucher_code' => 'ITEMONLY',
            'credits' => 0,
            'expiry_date' => null,
            'is_single_use' => true,
            'allow_new_users' => true,
        ]);
        \DB::table('vouchers_items')->insert([
            'voucher_code' => 'ITEMONLY',
            'catalogue_sale_code' => 'rare_lamp',
        ]);

        $this->actingAs($itemUser)
            ->withSession(['authenticated' => true, 'user.id' => $itemUser->id])
            ->post('/habblet/ajax/redeemvoucher', ['voucherCode' => 'ITEMONLY'])
            ->assertOk()
            ->assertSee('redeem-success', false);

        $this->assertSame(2, \DB::table('items')->where('user_id', $itemUser->id)->where('definition_id', 901)->count());
        $this->assertDatabaseHas('vouchers_history', [
            'voucher_code' => 'ITEMONLY',
            'user_id' => $itemUser->id,
            'credits_redeemed' => null,
            'items_redeemed' => '1,rare_lamp',
        ]);
        $this->assertDatabaseMissing('vouchers', [
            'voucher_code' => 'ITEMONLY',
        ]);
        $this->assertDatabaseMissing('vouchers_items', [
            'voucher_code' => 'ITEMONLY',
        ]);
    }

    public function test_proxy_token_and_clear_hand_legacy_shapes(): void
    {
        $user = $this->createLegacyUser();
        $sender = $this->createLegacyUser([
            'username' => 'ProxySender',
            'email' => 'proxy-sender@example.test',
        ]);
        $this->insertStatistics($user->id);
        $this->insertStatistics($sender->id);
        $deleteItemId = \DB::table('items')->insertGetId([
            'user_id' => $user->id,
            'room_id' => 0,
            'definition_id' => 501,
            'is_hidden' => false,
            'is_trading' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('cms_minimail')->insert([
            'target_id' => $user->id,
            'sender_id' => $sender->id,
            'to_id' => $user->id,
            'subject' => 'Proxy inbox subject',
            'message' => 'Proxy inbox body',
            'date_sent' => now(),
        ]);
        $hiddenItemId = \DB::table('items')->insertGetId([
            'user_id' => $user->id,
            'room_id' => 0,
            'definition_id' => 502,
            'is_hidden' => true,
            'is_trading' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roomItemId = \DB::table('items')->insertGetId([
            'user_id' => $user->id,
            'room_id' => 12,
            'definition_id' => 503,
            'is_hidden' => false,
            'is_trading' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/habblet/proxy?hid=h24')
            ->assertOk()
            ->assertSee('tag-search-form', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/habblet/cproxy?habbletKey=news')
            ->assertOk()
            ->assertSee('news-articlelist', false)
            ->assertSee('news-footer', false)
            ->assertSee('News.init(false);', false)
            ->assertSee('web-gallery/v2/styles/news.css', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/habblet/cproxy')
            ->assertOk()
            ->assertSee('ProxySender', false)
            ->assertSee('Proxy inbox subject', false)
            ->assertSee('1 - 1', false)
            ->assertDontSee('no-messages', false);

        $token = $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/token_generate');

        $token->assertOk();
        $this->assertStringStartsWith('token-', $token->getContent());
        $this->assertSame($token->getContent(), session('authenticationToken'));

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/clear_hand')
            ->assertOk()
            ->assertContent('Failed to securely verify request');
        $this->assertDatabaseHas('items', ['id' => $deleteItemId]);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'xssSeed' => 12345,
                'xssKey' => 1553932502,
                'xssRequested' => '/credits',
            ])
            ->post('/habblet/ajax/clear_hand')
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('items', ['id' => $deleteItemId]);
        $this->assertDatabaseHas('items', ['id' => $hiddenItemId]);
        $this->assertDatabaseHas('items', ['id' => $roomItemId]);
        $this->assertFalse(session()->has('xssSeed'));
        $this->assertFalse(session()->has('xssKey'));
        $this->assertFalse(session()->has('xssRequested'));
    }

    public function test_friendmanagement_category_crud_renders_category_widget(): void
    {
        $user = $this->createLegacyUser();
        $this->insertStatistics($user->id);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/friendmanagement/ajax/createcategory', ['name' => 'Best Friends'])
            ->assertOk()
            ->assertSee('Best Friends', false);

        $categoryId = (int) \DB::table('messenger_categories')->where('user_id', $user->id)->value('id');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/friendmanagement/ajax/editCategory', [
                'categoryId' => $categoryId,
                'name' => 'Crew',
            ])
            ->assertOk()
            ->assertSee('Crew', false);

        $this->assertDatabaseHas('messenger_categories', [
            'id' => $categoryId,
            'user_id' => $user->id,
            'name' => 'Crew',
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/friendmanagement/ajax/updatecategoryoptions')
            ->assertOk()
            ->assertSee('Crew', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/friendmanagement/ajax/deletecategory', ['categoryId' => $categoryId])
            ->assertOk()
            ->assertDontSee('Crew', false);

        $this->assertDatabaseMissing('messenger_categories', ['id' => $categoryId]);
    }

    public function test_friendmanagement_view_move_and_delete_friends(): void
    {
        $user = $this->createLegacyUser(['username' => 'Alex']);
        $friend = $this->createLegacyUser([
            'username' => 'Blake',
            'email' => 'blake@example.test',
        ]);
        $newerFriend = $this->createLegacyUser([
            'username' => 'Casey',
            'email' => 'casey@example.test',
        ]);
        $friend->forceFill(['last_online' => now()->subDay()])->save();
        $newerFriend->forceFill(['last_online' => now()])->save();
        $this->insertStatistics($user->id);
        $this->insertStatistics($friend->id);
        $this->insertStatistics($newerFriend->id);
        $categoryId = \DB::table('messenger_categories')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Crew',
        ]);
        \DB::table('messenger_friends')->insert([
            ['from_id' => $user->id, 'to_id' => $friend->id, 'category_id' => 0],
            ['from_id' => $friend->id, 'to_id' => $user->id, 'category_id' => 0],
            ['from_id' => $user->id, 'to_id' => $newerFriend->id, 'category_id' => 0],
            ['from_id' => $newerFriend->id, 'to_id' => $user->id, 'category_id' => 999],
        ]);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'xssSeed' => 12345,
                'xssKey' => 1553932502,
                'xssRequested' => '/credits',
            ])
            ->get('/friendmanagement/ajax/viewcategory?pageSize=30&pageNumber=1')
            ->assertOk()
            ->assertSee('Blake', false)
            ->assertSee('Casey', false)
            ->assertSee('friend-list-table', false);
        $this->assertFalse(session()->has('xssSeed'));
        $this->assertFalse(session()->has('xssKey'));
        $this->assertFalse(session()->has('xssRequested'));
        $this->assertDatabaseHas('messenger_friends', [
            'from_id' => $newerFriend->id,
            'to_id' => $user->id,
            'category_id' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/friendmanagement/ajax/movefriends', [
                'pageSize' => 30,
                'moveCategoryId' => $categoryId,
                'friendList' => [$friend->id.'abc'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('messenger_friends', [
            'from_id' => $friend->id,
            'to_id' => $user->id,
            'category_id' => 0,
        ]);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'xssSeed' => 12345,
                'xssKey' => 1553932502,
                'xssRequested' => '/credits',
            ])
            ->post('/friendmanagement/ajax/movefriends', [
                'pageSize' => 30,
                'moveCategoryId' => $categoryId,
                'friendList' => [$friend->id],
            ])
            ->assertOk()
            ->assertSee('Blake', false);
        $this->assertFalse(session()->has('xssSeed'));
        $this->assertFalse(session()->has('xssKey'));
        $this->assertFalse(session()->has('xssRequested'));

        $this->assertDatabaseHas('messenger_friends', [
            'from_id' => $friend->id,
            'to_id' => $user->id,
            'category_id' => $categoryId,
        ]);
        $this->assertDatabaseHas('messenger_friends', [
            'from_id' => $user->id,
            'to_id' => $friend->id,
            'category_id' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/friendmanagement/ajax/viewcategory?pageSize=1&pageNumber=1&categoryId='.$categoryId)
            ->assertOk()
            ->assertDontSee('Blake', false)
            ->assertDontSee('Casey', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/friendmanagement/ajax/deletefriends', ['friendId' => $friend->id.'abc'])
            ->assertOk()
            ->assertSee('Blake', false);

        $this->assertDatabaseHas('messenger_friends', [
            'from_id' => $user->id,
            'to_id' => $friend->id,
        ]);
        $this->assertDatabaseHas('messenger_friends', [
            'from_id' => $friend->id,
            'to_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'xssSeed' => 12345,
                'xssKey' => 1553932502,
                'xssRequested' => '/credits',
            ])
            ->post('/friendmanagement/ajax/deletefriends', ['friendId' => $friend->id])
            ->assertOk()
            ->assertDontSee('Blake', false);
        $this->assertFalse(session()->has('xssSeed'));
        $this->assertFalse(session()->has('xssKey'));
        $this->assertFalse(session()->has('xssRequested'));

        $this->assertDatabaseMissing('messenger_friends', [
            'from_id' => $user->id,
            'to_id' => $friend->id,
        ]);
        $this->assertDatabaseMissing('messenger_friends', [
            'from_id' => $friend->id,
            'to_id' => $user->id,
        ]);
    }

    public function test_invite_and_friendmanagement_routes_accept_legacy_get_requests(): void
    {
        $user = $this->createLegacyUser(['username' => 'FriendGetUser']);
        $target = $this->createLegacyUser(['username' => 'FriendGetTarget']);
        $this->insertStatistics($user->id);
        $this->insertStatistics($target->id);

        foreach ([
            '/habblet/habbosearchcontent',
            '/habblet/ajax/confirmAddFriend',
            '/habblet/ajax/addFriend',
            '/myhabbo/friends/add',
            '/friendmanagement/ajax/editCategory',
            '/friendmanagement/ajax/createcategory',
            '/friendmanagement/ajax/deletecategory',
            '/friendmanagement/ajax/movefriends',
            '/friendmanagement/ajax/deletefriends',
        ] as $path) {
            $this->get($path)->assertRedirect('/');
        }

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/habblet/habbosearchcontent?searchString=FriendGet&pageNumber=1')
            ->assertOk()
            ->assertSee('FriendGetTarget', false)
            ->assertSee('avatar-habblet-list-container', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/habblet/ajax/confirmAddFriend?accountId='.$target->id)
            ->assertOk()
            ->assertSee('FriendGetUser', false)
            ->assertDontSee('FriendGetTarget', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/habblet/ajax/addFriend')
            ->assertOk()
            ->assertSee('There was an error finding the user for the friend request.', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/myhabbo/friends/add')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-javascript')
            ->assertSee('There was an error finding the user for the friend request.', false);

        $this->assertSame(0, \DB::table('messenger_requests')->count());

        foreach ([
            '/friendmanagement/ajax/editCategory',
            '/friendmanagement/ajax/createcategory',
            '/friendmanagement/ajax/deletecategory',
        ] as $path) {
            $this->actingAs($user)
                ->withSession(['authenticated' => true, 'user.id' => $user->id])
                ->get($path)
                ->assertOk()
                ->assertSee('category-list', false);
        }

        foreach ([
            '/friendmanagement/ajax/movefriends',
            '/friendmanagement/ajax/deletefriends',
        ] as $path) {
            $this->actingAs($user)
                ->withSession(['authenticated' => true, 'user.id' => $user->id])
                ->get($path)
                ->assertOk()
                ->assertSee('friend-list-table', false);
        }

        $this->assertSame(0, \DB::table('messenger_categories')->where('user_id', $user->id)->count());
        $this->assertSame(0, \DB::table('messenger_friends')->where('to_id', $user->id)->count());
    }

    public function test_tag_page_and_ajax_search_render_user_tag_results(): void
    {
        $user = $this->createLegacyUser(['username' => 'TaggedUser', 'motto' => 'Tagged motto']);
        $this->insertStatistics($user->id);
        \DB::table('users_tags')->insert([
            ['user_id' => $user->id, 'tag' => 'retro', 'room_id' => '0', 'group_id' => '0', 'created_at' => now()],
            ['user_id' => $user->id, 'tag' => 'hotel', 'room_id' => '0', 'group_id' => '0', 'created_at' => now()],
        ]);

        $this->get('/tag/retro')
            ->assertOk()
            ->assertSee('TaggedUser', false)
            ->assertSee('retro', false)
            ->assertSee('tag-search-habblet-container', false);

        $this->post('/habblet/ajax/tagsearch', ['tag' => 'retro'])
            ->assertOk()
            ->assertSee('TaggedUser', false)
            ->assertSee('Tagged motto', false);
    }

    public function test_proxy_renders_staff_pick_rooms_highest_rooms_hot_groups_and_tag_cloud(): void
    {
        $owner = $this->createLegacyUser(['username' => 'RoomOwner']);
        $this->insertStatistics($owner->id);
        $roomId = \DB::table('rooms')->insertGetId([
            'owner_id' => (string) $owner->id,
            'name' => 'Top Room',
            'description' => 'A very good room',
            'model' => 'model_s',
            'visitors_now' => 5,
            'visitors_max' => 25,
            'rating' => 99,
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('cms_recommended')->insert([
            'recommended_id' => $roomId,
            'type' => 'ROOM',
            'is_staff_pick' => true,
        ]);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Hot Group',
            'description' => 'Busy group',
            'owner_id' => $owner->id,
            'room_id' => $roomId,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 1,
            'views' => 10,
            'alias' => 'hot-group',
            'created_at' => now(),
        ]);
        \DB::table('groups_memberships')->insert([
            ['user_id' => $owner->id, 'group_id' => $groupId, 'member_rank' => '3', 'is_pending' => false, 'created_at' => now()],
            ['user_id' => 999, 'group_id' => $groupId, 'member_rank' => '1', 'is_pending' => false, 'created_at' => now()],
        ]);
        \DB::table('users_tags')->insert([
            ['user_id' => $owner->id, 'tag' => 'retro', 'room_id' => '0', 'group_id' => '0', 'created_at' => now()],
        ]);

        $this->get('/habblet/proxy?hid=h21')
            ->assertOk()
            ->assertSee('Top Room', false)
            ->assertSee('RoomOwner', false);

        $this->get('/habblet/proxy?hid=h120')
            ->assertOk()
            ->assertSee('Top Room', false)
            ->assertSee('A very good room', false);

        $this->get('/habblet/proxy?hid=groups')
            ->assertOk()
            ->assertSee('Hot Group', false)
            ->assertSee('/groups/hot-group', false);

        $this->get('/habblet/proxy?hid=h24')
            ->assertOk()
            ->assertSee('retro', false);
    }

    public function test_invite_search_and_friend_request_routes_render_and_persist_request(): void
    {
        $user = $this->createLegacyUser(['username' => 'Requester']);
        $target = $this->createLegacyUser(['username' => 'TargetUser', 'email' => 'target@example.test']);
        $existingFriend = $this->createLegacyUser(['username' => 'ExistingFriend', 'email' => 'friend@example.test']);
        $this->insertStatistics($user->id);
        $this->insertStatistics($target->id);
        $this->insertStatistics($existingFriend->id);
        \DB::table('messenger_friends')->insert([
            ['from_id' => $existingFriend->id, 'to_id' => $user->id, 'category_id' => 0],
            ['from_id' => $user->id, 'to_id' => $existingFriend->id, 'category_id' => 0],
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/habbosearchcontent', ['searchString' => 'Target', 'pageNumber' => 1])
            ->assertOk()
            ->assertSee('TargetUser', false)
            ->assertSee('avatarid="'.$target->id.'"', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/confirmAddFriend', ['accountId' => $target->id])
            ->assertOk()
            ->assertSee('Requester', false)
            ->assertDontSee('TargetUser', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/confirmAddFriend', ['accountId' => 99999])
            ->assertOk()
            ->assertSee('Requester', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/addFriend', ['accountId' => $target->id.'abc'])
            ->assertOk()
            ->assertSee('There was an error finding the user for the friend request.', false);
        $this->assertDatabaseMissing('messenger_requests', [
            'from_id' => $user->id,
            'to_id' => $target->id,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/myhabbo/friends/add', ['accountId' => $target->id.'abc'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-javascript')
            ->assertSee('There was an error finding the user for the friend request.', false);
        $this->assertDatabaseMissing('messenger_requests', [
            'from_id' => $user->id,
            'to_id' => $target->id,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/addFriend', ['accountId' => $target->id])
            ->assertOk()
            ->assertSee('Friend request has been sent successfully.', false);

        $this->assertDatabaseHas('messenger_requests', [
            'from_id' => $user->id,
            'to_id' => $target->id,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/myhabbo/friends/add', ['accountId' => $target->id])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-javascript')
            ->assertSee('There is already a friend request for this user.', false);

        for ($i = 1; $i <= 31; $i++) {
            $searchUser = $this->createLegacyUser([
                'username' => sprintf('LimitSearch%02d', $i),
                'email' => sprintf('limit-search-%02d@example.test', $i),
            ]);
            $this->insertStatistics($searchUser->id);
        }

        $this->createLegacyUser([
            'username' => 'BeforeLimitSearch',
            'email' => 'before-limit-search@example.test',
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/habbosearchcontent', ['searchString' => 'LimitSearch', 'pageNumber' => 6])
            ->assertOk()
            ->assertSee('LimitSearch26', false)
            ->assertSee('LimitSearch30', false)
            ->assertSee('avatar-habblet-list-container-totalPages" value="6', false)
            ->assertDontSee('LimitSearch31', false)
            ->assertDontSee('BeforeLimitSearch', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/habbosearchcontent', ['searchString' => 'LimitSearch', 'pageNumber' => 7])
            ->assertOk()
            ->assertSee('not found', false)
            ->assertDontSee('LimitSearch31', false);
    }

    public function test_minimail_routes_send_list_load_trash_undelete_and_empty_messages(): void
    {
        $sender = $this->createLegacyUser(['username' => 'Sender']);
        $recipient = $this->createLegacyUser(['username' => 'Recipient', 'email' => 'recipient@example.test']);
        $this->insertStatistics($sender->id);
        $this->insertStatistics($recipient->id);
        \DB::table('messenger_friends')->insert([
            ['from_id' => $recipient->id, 'to_id' => $sender->id, 'category_id' => 0],
            ['from_id' => $sender->id, 'to_id' => $recipient->id, 'category_id' => 0],
        ]);

        $this->actingAs($sender)
            ->post('/minimail/loadMessages', ['label' => 'inbox', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertContent('');
        $this->actingAs($sender)
            ->get('/minimail/recipients')
            ->assertOk()
            ->assertContent('');
        $this->actingAs($sender)
            ->post('/minimail/preview', ['body' => '[b]Auth only[/b]'])
            ->assertOk()
            ->assertContent('');
        $this->actingAs($sender)
            ->post('/minimail/sendMessage', [
                'recipientIds' => (string) $recipient->id,
                'subject' => 'Auth only',
                'body' => 'Auth only',
                'label' => 'sent',
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertContent('');
        $this->actingAs($sender)
            ->get('/minimail/loadMessage?messageId=1')
            ->assertOk()
            ->assertContent('');
        $this->actingAs($sender)
            ->post('/minimail/deleteMessage', ['messageId' => 1, 'label' => 'inbox', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertContent('');
        $this->actingAs($sender)
            ->post('/minimail/undeleteMessage', ['messageId' => 1, 'label' => 'trash', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertContent('');
        $this->actingAs($sender)
            ->post('/minimail/emptyTrash', ['label' => 'trash', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->get('/minimail/recipients')
            ->assertOk()
            ->assertSee('"name":"Recipient"', false);

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->post('/minimail/preview', ['body' => '[b]Bold[/b] <script>alert(1)</script>'])
            ->assertOk()
            ->assertSee('<b>Bold</b>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>', false);

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->post('/minimail/preview', ['body' => "Line one\nLine two"])
            ->assertOk()
            ->assertSee('Line one<br>Line two', false);

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->post('/minimail/sendMessage', [
                'recipientIds' => (string) $recipient->id,
                'subject' => "Hello [b]subject[/b]\nSecond subject line",
                'body' => "[quote]Hello [i]body[/i][/quote][br][color=red]Red[/color]\nPlain line",
                'label' => 'sent',
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('Hello <b>subject</b>', false);

        $this->assertDatabaseHas('cms_minimail', [
            'target_id' => $recipient->id,
            'sender_id' => $sender->id,
            'to_id' => $recipient->id,
            'subject' => "Hello [b]subject[/b]\nSecond subject line",
            'message' => "[quote]Hello [i]body[/i][/quote][br][color=red]Red[/color]\nPlain line",
        ]);

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id, 'minimailLabel' => 'inbox'])
            ->post('/minimail/loadMessages', ['label' => 'Sent', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('label-sent', false)
            ->assertSee('Hello <b>subject</b><br>Second subject line', false);
        $this->assertSame('Sent', session('minimailLabel'));

        \DB::table('wordfilter')->insert([
            'word' => 'blocked',
            'is_bannable' => false,
            'is_filterable' => true,
        ]);

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->post('/minimail/sendMessage', [
                'recipientIds' => (string) $recipient->id,
                'subject' => 'Filtered subject',
                'body' => 'This contains blocked text',
                'label' => 'sent',
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON');

        $this->assertDatabaseMissing('cms_minimail', [
            'subject' => 'Filtered subject',
        ]);

        \DB::table('users_statistics')->where('user_id', $sender->id)->update([
            'mute_expires_at' => time() + 600,
        ]);

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->post('/minimail/sendMessage', [
                'recipientIds' => (string) $recipient->id,
                'subject' => 'Muted subject',
                'body' => 'Muted body',
                'label' => 'sent',
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertHeader('X-JSON', '{"message":"You are muted and cannot send messages.","totalMessages":1}');

        $this->assertDatabaseMissing('cms_minimail', [
            'subject' => 'Muted subject',
        ]);

        \DB::table('users_statistics')->where('user_id', $sender->id)->update([
            'mute_expires_at' => time() - 1,
        ]);

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->post('/minimail/sendMessage', [
                'recipientIds' => (string) $recipient->id,
                'subject' => 'Expired mute subject',
                'body' => 'Expired mute body',
                'label' => 'sent',
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('Expired mute subject', false);

        $this->assertDatabaseHas('cms_minimail', [
            'target_id' => $recipient->id,
            'sender_id' => $sender->id,
            'to_id' => $recipient->id,
            'subject' => 'Expired mute subject',
            'message' => 'Expired mute body',
        ]);

        \DB::table('cms_minimail')->where('subject', 'Expired mute subject')->delete();

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->post('/minimail/sendMessage', [
                'recipientIds' => $recipient->id.'abc',
                'subject' => 'Malformed recipient',
                'body' => 'Malformed recipient body',
                'label' => 'sent',
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON');

        $this->assertDatabaseMissing('cms_minimail', [
            'subject' => 'Malformed recipient',
        ]);

        $messageId = (int) \DB::table('cms_minimail')
            ->where('target_id', $recipient->id)
            ->value('id');

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->post('/minimail/loadMessages', ['label' => 'inbox', 'start' => 'oops', 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($recipient)
            ->withSession([
                'authenticated' => true,
                'user.id' => $recipient->id,
                'minimailLabel' => 'inbox',
                'xssSeed' => 12345,
                'xssKey' => 1553932502,
                'xssRequested' => '/credits',
            ])
            ->post('/minimail/loadMessages', ['label' => 'inbox', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('Sender', false)
            ->assertSee('Hello <b>subject</b><br>Second subject line', false);
        $this->assertFalse(session()->has('xssSeed'));
        $this->assertFalse(session()->has('xssKey'));
        $this->assertFalse(session()->has('xssRequested'));

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->post('/minimail/loadMessages', ['label' => 'unknown', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON', '{"totalMessages":0}')
            ->assertDontSee('Sender', false);
        $this->assertSame('unknown', session('minimailLabel'));

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->post('/minimail/loadMessages', ['label' => 'inbox', 'start' => -1, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('0 - 1', false)
            ->assertSee('Sender', false);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->get('/minimail/loadMessage')
            ->assertOk()
            ->assertContent('1');

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->get('/minimail/loadMessage?messageId=abc')
            ->assertOk()
            ->assertContent('1');

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->get('/minimail/loadMessage?messageId=0')
            ->assertOk()
            ->assertContent('2');

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->get('/minimail/loadMessage?messageId='.$messageId)
            ->assertOk()
            ->assertSee('Hello <b>subject</b><br>Second subject line', false)
            ->assertSee('<div class="bbcode-quote">Hello <i>body</i></div><br><font color="red">Red</font><br>Plain line', false);

        $this->assertDatabaseHas('cms_minimail', [
            'id' => $messageId,
            'is_read' => true,
        ]);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->post('/minimail/sendMessage', [
                'messageId' => $messageId.'abc',
                'body' => 'Malformed reply body',
                'label' => 'conversation',
                'conversationId' => $messageId,
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON');

        $this->assertDatabaseMissing('cms_minimail', [
            'message' => 'Malformed reply body',
        ]);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'inbox'])
            ->post('/minimail/sendMessage', [
                'messageId' => $messageId,
                'body' => 'Reply body',
                'label' => 'conversation',
                'conversationId' => $messageId,
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('Re: Hello <b>subject</b><br>Second subject line', false);

        $this->assertDatabaseHas('cms_minimail', [
            'id' => $messageId,
            'conversation_id' => $messageId,
        ]);
        $this->assertDatabaseHas('cms_minimail', [
            'target_id' => $sender->id,
            'sender_id' => $recipient->id,
            'to_id' => $sender->id,
            'subject' => "Re: Hello [b]subject[/b]\nSecond subject line",
            'message' => 'Reply body',
            'conversation_id' => $messageId,
        ]);
        $this->assertDatabaseHas('cms_minimail', [
            'target_id' => $recipient->id,
            'sender_id' => $recipient->id,
            'to_id' => $sender->id,
            'subject' => "Re: Hello [b]subject[/b]\nSecond subject line",
            'message' => 'Reply body',
            'conversation_id' => $messageId,
        ]);

        $replyMessageId = (int) \DB::table('cms_minimail')
            ->where('target_id', $sender->id)
            ->where('message', 'Reply body')
            ->value('id');

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id, 'minimailLabel' => 'conversation'])
            ->post('/minimail/sendMessage', [
                'messageId' => $replyMessageId,
                'body' => 'Reply to reply',
                'label' => 'conversation',
                'conversationId' => $replyMessageId,
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('Re: Re: Hello <b>subject</b><br>Second subject line', false);

        $this->assertDatabaseHas('cms_minimail', [
            'id' => $replyMessageId,
            'conversation_id' => $replyMessageId,
        ]);
        $this->assertDatabaseHas('cms_minimail', [
            'target_id' => $recipient->id,
            'sender_id' => $sender->id,
            'to_id' => $recipient->id,
            'subject' => "Re: Re: Hello [b]subject[/b]\nSecond subject line",
            'message' => 'Reply to reply',
            'conversation_id' => $replyMessageId,
        ]);

        $recipientReplyCopyId = (int) \DB::table('cms_minimail')
            ->where('target_id', $recipient->id)
            ->where('message', 'Reply to reply')
            ->value('id');

        \DB::table('cms_minimail')
            ->where('id', $recipientReplyCopyId)
            ->update([
                'is_trash' => true,
                'is_deleted' => false,
            ]);

        $this->actingAs($sender)
            ->withSession(['authenticated' => true, 'user.id' => $sender->id])
            ->post('/minimail/deleteMessage', ['messageId' => $recipientReplyCopyId, 'label' => 'sent', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON');

        $this->assertDatabaseHas('cms_minimail', [
            'id' => $recipientReplyCopyId,
            'target_id' => $recipient->id,
            'sender_id' => $sender->id,
            'is_trash' => true,
            'is_deleted' => true,
        ]);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'conversation'])
            ->post('/minimail/loadMessages', [
                'label' => 'conversation',
                'conversationId' => $replyMessageId.'abc',
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertDontSee('Re: Re: Hello <b>subject</b><br>Second subject line', false);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id, 'minimailLabel' => 'conversation'])
            ->post('/minimail/loadMessages', [
                'label' => 'conversation',
                'conversationId' => $replyMessageId,
                'start' => 0,
                'unreadOnly' => 'false',
            ])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('Re: Re: Hello <b>subject</b><br>Second subject line', false);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id])
            ->post('/minimail/deleteMessage', ['messageId' => $messageId.'abc', 'label' => 'trash', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertContent('');

        $this->assertDatabaseHas('cms_minimail', [
            'id' => $messageId,
            'is_trash' => false,
        ]);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id])
            ->post('/minimail/deleteMessage', ['messageId' => $messageId, 'label' => 'trash', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON');

        $this->assertDatabaseHas('cms_minimail', [
            'id' => $messageId,
            'is_trash' => true,
        ]);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id])
            ->post('/minimail/undeleteMessage', ['messageId' => $messageId, 'label' => 'inbox', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON');

        $this->assertDatabaseHas('cms_minimail', [
            'id' => $messageId,
            'is_trash' => false,
        ]);

        \DB::table('cms_minimail')->where('id', $messageId)->update(['is_trash' => true]);

        $this->actingAs($recipient)
            ->withSession(['authenticated' => true, 'user.id' => $recipient->id])
            ->post('/minimail/emptyTrash', ['label' => 'trash', 'start' => 0, 'unreadOnly' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON');

        $this->assertDatabaseHas('cms_minimail', [
            'id' => $messageId,
            'is_deleted' => true,
        ]);
    }

    public function test_minimail_routes_accept_legacy_get_requests(): void
    {
        $user = $this->createLegacyUser(['username' => 'MinimailGetUser']);
        $friend = $this->createLegacyUser([
            'username' => 'MinimailGetFriend',
            'email' => 'minimail-get-friend@example.test',
        ]);
        $this->insertStatistics($user->id);
        $this->insertStatistics($friend->id);
        \DB::table('messenger_friends')->insert([
            ['from_id' => $friend->id, 'to_id' => $user->id, 'category_id' => 0],
        ]);

        foreach ([
            '/minimail/loadMessages',
            '/minimail/preview',
            '/minimail/sendMessage',
            '/minimail/deleteMessage',
            '/minimail/undeleteMessage',
            '/minimail/emptyTrash',
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertContent('');
        }

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/minimail/preview?body=%5Bb%5DHello%5B%2Fb%5D')
            ->assertOk()
            ->assertSee('<b>Hello</b>', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/minimail/loadMessages?label=inbox&start=0&unreadOnly=false')
            ->assertOk()
            ->assertHeader('X-JSON', '{"totalMessages":0}')
            ->assertSessionHas('minimailLabel', 'inbox');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/minimail/sendMessage?label=sent&start=0&unreadOnly=false')
            ->assertOk()
            ->assertHeader('X-JSON', '{"message":"Message sent successfully.","totalMessages":0}');

        foreach ([
            '/minimail/deleteMessage?messageId=999&label=inbox&start=0&unreadOnly=false',
            '/minimail/undeleteMessage?messageId=999&label=trash&start=0&unreadOnly=false',
        ] as $path) {
            $this->actingAs($user)
                ->withSession(['authenticated' => true, 'user.id' => $user->id])
                ->get($path)
                ->assertOk()
                ->assertContent('');
        }

        \DB::table('cms_minimail')->insert([
            'target_id' => $user->id,
            'sender_id' => $friend->id,
            'to_id' => $user->id,
            'subject' => 'Trash me',
            'message' => 'Trash body',
            'date_sent' => now(),
            'is_read' => false,
            'conversation_id' => 0,
            'is_trash' => true,
            'is_deleted' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/minimail/emptyTrash?label=trash&start=0&unreadOnly=false')
            ->assertOk()
            ->assertHeader('X-JSON', '{"message":"The trash has been emptied. Good Job!","totalMessages":0}');

        $this->assertDatabaseHas('cms_minimail', [
            'target_id' => $user->id,
            'subject' => 'Trash me',
            'is_deleted' => true,
        ]);
    }

    public function test_quickmenu_routes_render_groups_rooms_and_friends(): void
    {
        $user = $this->createLegacyUser(['username' => 'QuickUser', 'favourite_group' => 1]);
        $onlineFriend = $this->createLegacyUser([
            'username' => 'OnlineFriend',
            'email' => 'online@example.test',
            'is_online' => true,
            'last_online' => now(),
        ]);
        $offlineFriend = $this->createLegacyUser([
            'username' => 'OfflineFriend',
            'email' => 'offline@example.test',
            'is_online' => false,
            'last_online' => now()->subDay(),
        ]);
        $hiddenOnlineFriend = $this->createLegacyUser([
            'username' => 'HiddenOnlineFriend',
            'email' => 'hidden-online@example.test',
            'is_online' => true,
            'online_status_visible' => false,
            'last_online' => now()->addMinute(),
        ]);
        $groupMemberOne = $this->createLegacyUser([
            'username' => 'GroupMemberOne',
            'email' => 'group-member-one@example.test',
        ]);
        $groupMemberTwo = $this->createLegacyUser([
            'username' => 'GroupMemberTwo',
            'email' => 'group-member-two@example.test',
        ]);
        $this->insertStatistics($user->id);
        $this->insertStatistics($onlineFriend->id);
        $this->insertStatistics($offlineFriend->id);
        $this->insertStatistics($hiddenOnlineFriend->id);
        $this->insertStatistics($groupMemberOne->id);
        $this->insertStatistics($groupMemberTwo->id);
        $roomId = \DB::table('rooms')->insertGetId([
            'owner_id' => (string) $user->id,
            'name' => 'Quick Room',
            'description' => 'Quick room description',
            'model' => 'model_s',
            'visitors_now' => 0,
            'visitors_max' => 25,
            'rating' => 0,
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Small Quick Group',
            'description' => 'Quick group description',
            'owner_id' => $user->id,
            'room_id' => $roomId,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'views' => 0,
            'alias' => 'small-quick-group',
            'created_at' => now(),
        ]);
        $largerGroupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Large Quick Group',
            'description' => 'Bigger quick group description',
            'owner_id' => $user->id,
            'room_id' => $roomId,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'views' => 0,
            'alias' => 'large-quick-group',
            'created_at' => now(),
        ]);
        $user->forceFill(['favourite_group' => $groupId])->save();
        \DB::table('groups_memberships')->insert([
            [
                'user_id' => $user->id,
                'group_id' => $groupId,
                'member_rank' => '3',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'group_id' => $largerGroupId,
                'member_rank' => '3',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $groupMemberOne->id,
                'group_id' => $largerGroupId,
                'member_rank' => '1',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $groupMemberTwo->id,
                'group_id' => $largerGroupId,
                'member_rank' => '1',
                'is_pending' => false,
                'created_at' => now(),
            ],
        ]);
        \DB::table('messenger_friends')->insert([
            ['from_id' => $onlineFriend->id, 'to_id' => $user->id, 'category_id' => 0],
            ['from_id' => $offlineFriend->id, 'to_id' => $user->id, 'category_id' => 0],
            ['from_id' => $hiddenOnlineFriend->id, 'to_id' => $user->id, 'category_id' => 0],
        ]);

        $this->actingAs($user)
            ->get('/quickmenu/groups')
            ->assertRedirect('/');
        $this->actingAs($user)
            ->get('/quickmenu/rooms')
            ->assertRedirect('/');
        $this->actingAs($user)
            ->get('/quickmenu/friends_all')
            ->assertRedirect('/');

        $groupsResponse = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'xssSeed' => 12345,
                'xssKey' => 1553932502,
                'xssRequested' => '/credits',
            ])
            ->get('/quickmenu/groups')
            ->assertOk()
            ->assertSee('Small Quick Group', false)
            ->assertSee('Large Quick Group', false)
            ->assertSee('/groups/small-quick-group', false)
            ->assertSee('favourite-group', false)
            ->assertSee('owned-group', false);
        $groupsContent = $groupsResponse->getContent();
        $this->assertLessThan(
            strpos($groupsContent, 'Small Quick Group'),
            strpos($groupsContent, 'Large Quick Group')
        );
        $this->assertFalse(session()->has('xssSeed'));
        $this->assertFalse(session()->has('xssKey'));
        $this->assertFalse(session()->has('xssRequested'));

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'xssSeed' => 12345,
                'xssKey' => 1553932502,
                'xssRequested' => '/credits',
            ])
            ->get('/quickmenu/rooms')
            ->assertOk()
            ->assertSee('Quick Room', false)
            ->assertSee('room-navigation-link_'.$roomId, false);
        $this->assertFalse(session()->has('xssSeed'));
        $this->assertFalse(session()->has('xssKey'));
        $this->assertFalse(session()->has('xssRequested'));

        $friendsResponse = $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'xssSeed' => 12345,
                'xssKey' => 1553932502,
                'xssRequested' => '/credits',
            ])
            ->get('/quickmenu/friends_all')
            ->assertOk()
            ->assertSee('OnlineFriend', false)
            ->assertSee('OfflineFriend', false)
            ->assertSee('HiddenOnlineFriend', false)
            ->assertSee('online-friends', false)
            ->assertSee('offline-friends', false);
        $friendsContent = $friendsResponse->getContent();
        $this->assertLessThan(
            strpos($friendsContent, 'HiddenOnlineFriend'),
            strpos($friendsContent, 'offline-friends')
        );
        $this->assertFalse(session()->has('xssSeed'));
        $this->assertFalse(session()->has('xssKey'));
        $this->assertFalse(session()->has('xssRequested'));
    }

    public function test_public_site_routes_render_articles_community_and_credit_pages(): void
    {
        $author = $this->createLegacyUser(['username' => 'Reporter']);
        $this->insertStatistics($author->id);
        $articleId = \DB::table('site_articles')->insertGetId([
            'title' => 'Public Route News',
            'author_id' => $author->id,
            'author_override' => '',
            'short_story' => 'Short public story',
            'full_story' => 'Full public story',
            'topstory' => 'attention_topstory.png',
            'topstory_override' => '',
            'article_image' => '',
            'is_published' => true,
            'is_future_published' => false,
            'views' => 0,
            'created_at' => now(),
        ]);
        $eventsId = \DB::table('article_categories')->insertGetId([
            'label' => 'Events',
            'category_index' => 'events',
        ]);
        \DB::table('site_articles_categories')->insert([
            'article_id' => $articleId,
            'category_id' => $eventsId,
        ]);

        $this->get('/articles')
            ->assertOk()
            ->assertSee('Public Route News', false)
            ->assertSee('Short public story', false);

        $this->get('/articles/'.$articleId.'-public-route-news')
            ->assertOk()
            ->assertSee('Full public story', false)
            ->assertSee('Reporter', false);

        $this->get('/community/events')
            ->assertOk()
            ->assertSee('Public Route News', false);

        $this->get('/community')
            ->assertOk()
            ->assertSee('Public Route News', false)
            ->assertSee('activehomes', false);

        $this->get('/credits')
            ->assertOk()
            ->assertSee('How to get Credits', false);

        $this->get('/credits/pixels')
            ->assertOk()
            ->assertSee('Pixels', false);

        \DB::table('settings')->insert([
            'setting' => 'collectables.page',
            'value' => '51',
        ]);
        app(HavanaConfig::class)->reload();
        \DB::table('catalogue_collectables')->insert([
            'store_page' => 51,
            'admin_page' => 83,
            'expiry' => time() - 1,
            'lifetime' => 86400,
            'current_position' => 0,
            'class_names' => 'rare_old,rare_new',
        ]);
        \DB::table('items_definitions')->insert([
            [
                'id' => 901,
                'sprite' => 'rare_old',
                'name' => 'Rare Old',
                'description' => 'Previous collectable',
            ],
            [
                'id' => 902,
                'sprite' => 'rare_new',
                'name' => 'Rare New',
                'description' => 'Current collectable',
            ],
        ]);
        \DB::table('catalogue_items')->insert([
            'id' => 701,
            'sale_code' => 'rare_new',
            'page_id' => '83',
            'order_id' => 5,
            'price_coins' => 200,
            'price_pixels' => 0,
            'amount' => 1,
            'definition_id' => 902,
        ]);
        $this->get('/credits/collectables')
            ->assertOk()
            ->assertSee('Collectables', false)
            ->assertSee('Rare New', false)
            ->assertSee('Current collectable', false)
            ->assertSee('rare_new', false)
            ->assertSee('Rare Old', false);
        $this->assertDatabaseHas('catalogue_collectables', [
            'store_page' => 51,
            'current_position' => 1,
        ]);
        $this->assertGreaterThan(time(), (int) \DB::table('catalogue_collectables')->where('store_page', 51)->value('expiry'));

        $this->get('/help/anything')
            ->assertOk()
            ->assertSee('faq', false);
    }

    public function test_games_highscores_and_credit_history_render_db_backed_rows(): void
    {
        $user = $this->createLegacyUser(['username' => 'ScoreUser']);
        $this->insertStatistics($user->id, [
            'battleball_score_month' => 123,
            'battleball_score_all_time' => 456,
        ]);
        \DB::table('users_transactions')->insert([
            'user_id' => $user->id,
            'item_id' => '1',
            'catalogue_id' => '2',
            'amount' => 1,
            'description' => 'Catalogue purchase',
            'credit_cost' => 25,
            'pixel_cost' => 0,
            'created_at' => now(),
            'is_visible' => true,
        ]);

        $this->get('/games')
            ->assertOk()
            ->assertSee('ScoreUser', false)
            ->assertSee('123', false);

        $this->get('/games/score_all_time')
            ->assertOk()
            ->assertSee('ScoreUser', false)
            ->assertSee('456', false);

        $this->withSession(['gameScoreViewMonthly' => true])
            ->post('/habblet/personalhighscores', ['gameId' => 1, 'pageNumber' => 1])
            ->assertOk()
            ->assertSee('ScoreUser', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/credits/history')
            ->assertOk()
            ->assertSee('Catalogue purchase', false)
            ->assertSee('25', false);
    }

    public function test_habblet_routes_accept_legacy_get_requests(): void
    {
        $this->get('/habblet/personalhighscores')
            ->assertOk()
            ->assertSee('highscores-habblet-list-container', false);

        $this->get('/habblet/ajax/updatemotto')
            ->assertOk()
            ->assertContent('');

        $this->get('/habblet/ajax/giftqueueHide')
            ->assertOk()
            ->assertContent('');

        $this->get('/myhabbo/tag/add')
            ->assertOk()
            ->assertContent('');

        $this->get('/myhabbo/tag/remove')
            ->assertOk()
            ->assertContent('');

        $this->get('/habblet/ajax/tagfight?tag1=retro&tag2=vintage')
            ->assertOk()
            ->assertSee('tagfight_end_0.gif', false);

        $this->get('/habblet/ajax/tagmatch')
            ->assertStatus(302)
            ->assertHeader('Location', '/');

        $this->get('/habblet/ajax/tagsearch?tag=retro')
            ->assertOk()
            ->assertSee('search-result-count', false);

        $this->get('/habblet/ajax/redeemvoucher')
            ->assertOk()
            ->assertContent('');

        $this->get('/habblet/ajax/clear_hand')
            ->assertOk()
            ->assertContent('');

        $this->get('/habblet/ajax/token_generate')
            ->assertOk()
            ->assertContent('');

        $this->get('/habblet/ajax/removeFeedItem')
            ->assertRedirect('/');

        $this->get('/habblet/ajax/load_events')
            ->assertOk()
            ->assertContent('');

        $this->get('/habblet/ajax/collectiblesConfirm')
            ->assertRedirect('/');

        $this->get('/habblet/ajax/collectiblesPurchase')
            ->assertRedirect('/');

        $this->get('/habboclub/habboclub_confirm')
            ->assertOk()
            ->assertSee('habboclub.showSubscriptionResultWindow(1', false);

        $this->get('/habboclub/habboclub_subscribe')
            ->assertOk()
            ->assertContent('');

        $this->get('/habboclub/habboclub_reminder_remove')
            ->assertOk()
            ->assertContent('');

        $this->get('/habblet/ajax/habboclub_gift')
            ->assertOk()
            ->assertContent('');

        $this->get('/habblet/ajax/preview_news_article?body=Hello%20there')
            ->assertOk()
            ->assertSee('Hello there', false);
    }

    public function test_home_store_widget_routes_accept_legacy_get_requests(): void
    {
        foreach ([
            '/myhabbo/save',
            '/myhabbo/noteeditor/editor',
            '/myhabbo/noteeditor/preview',
            '/myhabbo/noteeditor/place',
            '/myhabbo/stickie/edit',
            '/myhabbo/stickie/delete',
            '/myhabbo/store/inventory',
            '/myhabbo/store/inventory_items',
            '/myhabbo/store/inventory_preview',
            '/myhabbo/sticker/place_sticker',
            '/myhabbo/sticker/remove_sticker',
            '/myhabbo/widget/add',
            '/myhabbo/widget/delete',
            '/myhabbo/widget/edit',
            '/myhabbo/store/main',
            '/myhabbo/store/items',
            '/myhabbo/store/preview',
            '/myhabbo/store/purchase_confirm',
            '/myhabbo/store/background_warning',
            '/myhabbo/store/purchase_stickers',
            '/myhabbo/store/purchase_backgrounds',
            '/myhabbo/store/purchase_stickie_notes',
            '/myhabbo/traxplayer/select_song',
            '/myhabbo/guestbook/preview',
            '/myhabbo/guestbook/add',
            '/myhabbo/guestbook/remove',
            '/myhabbo/guestbook/configure',
        ] as $path) {
            $this->get($path)->assertRedirect('/');
        }

        foreach ([
            '/myhabbo/tag/list',
            '/myhabbo/groups/groupinfo',
            '/myhabbo/avatarlist/membersearchpaging',
            '/myhabbo/badgelist/badgepaging',
            '/myhabbo/avatarlist/friendsearchpaging',
            '/myhabbo/avatarlist/avatarinfo',
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertContent('');
        }
    }

    public function test_group_routes_render_front_page_and_redirect_numeric_alias(): void
    {
        $owner = $this->createLegacyUser(['username' => 'GroupOwner']);
        $this->insertStatistics($owner->id);
        $roomId = \DB::table('rooms')->insertGetId([
            'owner_id' => (string) $owner->id,
            'name' => 'Group Home Room',
            'description' => 'Room for a group',
            'model' => 'model_s',
            'visitors_now' => 2,
            'visitors_max' => 25,
            'rating' => 0,
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Read Group',
            'description' => 'Read group description',
            'owner_id' => $owner->id,
            'room_id' => $roomId,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'read-group',
            'created_at' => now(),
        ]);

        $this->get('/groups/'.$groupId.'/id')
            ->assertRedirect('/groups/read-group');

        $this->get('/groups/read-group')
            ->assertOk()
            ->assertSee('Read Group', false)
            ->assertSee('b_bg_colour_08', false)
            ->assertSee('/groups/read-group/discussions', false);
    }

    public function test_group_action_routes_accept_legacy_get_requests(): void
    {
        $user = $this->createLegacyUser(['username' => 'GroupGetUser']);
        $this->insertStatistics($user->id);

        $this->get('/grouppurchase/purchase_confirmation')
            ->assertOk()
            ->assertContent('');

        $this->get('/grouppurchase/purchase_ajax')
            ->assertOk()
            ->assertContent('');

        foreach ([
            '/groups/actions/group_settings',
            '/groups/actions/saveEditingSession',
            '/groups/actions/update_group_settings',
            '/groups/actions/check_group_url',
            '/groups/actions/show_badge_editor',
            '/groups/actions/update_group_badge',
            '/groups/actions/confirm_delete_group',
            '/groups/actions/delete_group',
            '/myhabbo/groups/memberlist',
            '/myhabbo/groups/batch/revoke_rights',
            '/myhabbo/groups/batch/give_rights',
            '/myhabbo/groups/batch/remove',
            '/myhabbo/groups/batch/accept',
            '/myhabbo/groups/batch/decline',
        ] as $path) {
            $this->get($path)->assertRedirect('/');
        }

        foreach ([
            '/myhabbo/tag/addgrouptag',
            '/myhabbo/tag/listgrouptags',
            '/myhabbo/tag/removegrouptag',
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertContent('');
        }

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/groups/actions/check_group_url?url=retro-club')
            ->assertOk()
            ->assertSee('retro-club', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/myhabbo/groups/batch/confirm_revoke_rights?targetIds[]=1&targetIds[]=2')
            ->assertOk()
            ->assertSee('selected 2', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/myhabbo/groups/batch/confirm_give_rights?targetIds[]=1')
            ->assertOk()
            ->assertSee('selected 1', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/myhabbo/groups/batch/confirm_remove?targetIds[]=1')
            ->assertOk()
            ->assertSee('selected 1', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/myhabbo/groups/batch/confirm_decline?targetIds[]=1')
            ->assertOk()
            ->assertSee('selected 1', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/myhabbo/groups/batch/confirm_accept')
            ->assertOk()
            ->assertContent('');
    }

    public function test_group_discussions_render_topics_and_replies(): void
    {
        $owner = $this->createLegacyUser(['username' => 'ForumOwner']);
        $poster = $this->createLegacyUser([
            'username' => 'ForumPoster',
            'email' => 'poster@example.test',
            'figure' => 'hd-190-1',
        ]);
        $this->insertStatistics($owner->id);
        $this->insertStatistics($poster->id);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Forum Group',
            'description' => 'Forum group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 1,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'forum-group',
            'created_at' => now(),
        ]);
        $threadId = \DB::table('cms_forum_threads')->insertGetId([
            'topic_title' => 'Forum Topic',
            'poster_id' => $poster->id,
            'is_open' => true,
            'is_stickied' => true,
            'views' => 3,
            'group_id' => $groupId,
            'created_at' => now()->subHour(),
            'modified_at' => now()->subHour(),
        ]);
        \DB::table('cms_forum_replies')->insert([
            'thread_id' => $threadId,
            'message' => 'First forum message',
            'poster_id' => $poster->id,
            'is_edited' => false,
            'is_deleted' => false,
            'created_at' => now()->subMinutes(45),
            'modified_at' => now()->subMinutes(45),
        ]);

        $this->get('/groups/forum-group/discussions')
            ->assertOk()
            ->assertSee('Forum Topic', false)
            ->assertSee('ForumPoster', false)
            ->assertSee('/groups/forum-group/discussions/'.$threadId.'/id', false);

        $this->get('/groups/'.$groupId.'/id/discussions/'.$threadId.'/id')
            ->assertRedirect('/groups/forum-group/discussions/'.$threadId.'/id');

        $this->get('/groups/forum-group/discussions/'.$threadId.'/id')
            ->assertOk()
            ->assertSee('Forum Topic', false)
            ->assertSee('First forum message', false)
            ->assertSee('ForumPoster', false);
    }

    public function test_discussion_action_routes_accept_legacy_get_requests(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'DiscussionGetUser',
            'figure' => 'hd-180-1',
        ]);
        $this->insertStatistics($user->id);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Discussion GET Group',
            'description' => 'GET method group',
            'owner_id' => $user->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 1,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'discussion-get-group',
            'created_at' => now(),
        ]);
        \DB::table('groups_memberships')->insert([
            'user_id' => $user->id,
            'group_id' => $groupId,
            'member_rank' => '3',
            'is_pending' => false,
            'created_at' => now(),
        ]);
        $topicId = \DB::table('cms_forum_threads')->insertGetId([
            'topic_title' => 'GET Topic',
            'poster_id' => $user->id,
            'is_open' => true,
            'is_stickied' => false,
            'views' => 0,
            'group_id' => $groupId,
            'created_at' => now(),
            'modified_at' => now(),
        ]);

        foreach ([
            '/discussions/actions/newtopic',
            '/discussions/actions/savetopic',
            '/discussions/actions/previewtopic',
            '/discussions/actions/previewpost',
            '/discussions/actions/opentopicsettings',
            '/discussions/actions/confirm_delete_topic',
            '/discussions/actions/deletetopic',
            '/discussions/actions/savetopicsettings',
            '/discussions/actions/updatepost',
            '/discussions/actions/deletepost',
            '/discussions/actions/savepost',
        ] as $path) {
            $this->get($path)->assertRedirect('/');
        }

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/discussions/actions/newtopic')
            ->assertOk()
            ->assertSee('new-topic-name', false)
            ->assertSee('topic-form-save', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/discussions/actions/savetopic')
            ->assertOk()
            ->assertSee('Please supply a valid message.', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/discussions/actions/previewtopic?topicName=GET%20Preview&message=%5Bb%5DHello%5B%2Fb%5D')
            ->assertOk()
            ->assertSee('GET Preview', false)
            ->assertSee('[b]Hello[/b]', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/discussions/actions/previewpost?groupId='.$groupId.'&topicId='.$topicId.'&message=Reply%20preview')
            ->assertOk()
            ->assertSee('RE: GET Topic', false)
            ->assertSee('Reply preview', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/discussions/actions/opentopicsettings?groupId='.$groupId.'&topicId='.$topicId)
            ->assertOk()
            ->assertSee('topic-settings-form', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/discussions/actions/confirm_delete_topic')
            ->assertOk()
            ->assertSee('You are about to delete a topic are you sure?', false);
    }

    public function test_discussion_create_and_preview_actions(): void
    {
        $owner = $this->createLegacyUser(['username' => 'ActionForumOwner']);
        $poster = $this->createLegacyUser([
            'username' => 'ActionForumPoster',
            'email' => 'action-forum-poster@example.test',
            'figure' => 'hd-191-1',
            'motto' => 'Forum motto',
            'is_online' => true,
        ]);
        $this->insertStatistics($owner->id);
        $this->insertStatistics($poster->id);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Action Forum Group',
            'description' => 'Action forum group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'action-forum',
            'created_at' => now(),
        ]);
        $poster->forceFill(['favourite_group' => $groupId])->save();
        \DB::table('users_badges')->insert([
            'user_id' => $poster->id,
            'badge' => 'ACH_Forum1',
            'equipped' => true,
            'slot_id' => 1,
        ]);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/discussions/actions/pingsession')
            ->assertOk()
            ->assertContent('')
            ->assertHeader('X-JSON', '{"privilegeLevel":"1"}');

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/discussions/actions/newtopic')
            ->assertOk()
            ->assertSee('new-topic-name', false)
            ->assertSee('discussion-captcha', false);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/discussions/actions/previewtopic', [
                'groupId' => $groupId,
                'topicName' => 'Preview Topic',
                'message' => '<b>Preview body</b>',
            ])
            ->assertOk()
            ->assertSee('Preview Topic', false)
            ->assertSee('&lt;b&gt;Preview body&lt;/b&gt;', false)
            ->assertSee('ACH_Forum1', false)
            ->assertSee('b0503Xs09114s05013s05015', false);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id, 'captcha-text' => 'good'])
            ->post('/discussions/actions/savetopic', [
                'groupId' => $groupId,
                'topicName' => 'Bad Captcha Topic',
                'message' => 'Captcha body',
                'captcha' => 'bad',
            ])
            ->assertOk()
            ->assertContent('')
            ->assertHeader('X-JSON', '{"captchaError":"true"}');
        $this->assertDatabaseMissing('cms_forum_threads', [
            'group_id' => $groupId,
            'topic_title' => 'Bad Captcha Topic',
        ]);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id, 'captcha-text' => 'good'])
            ->post('/discussions/actions/savetopic', [
                'groupId' => $groupId.'abc',
                'topicName' => 'Malformed Group Topic',
                'message' => 'Malformed body',
                'captcha' => 'good',
            ])
            ->assertRedirect('/');
        $this->assertDatabaseMissing('cms_forum_threads', [
            'group_id' => $groupId,
            'topic_title' => 'Malformed Group Topic',
        ]);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id, 'captcha-text' => 'good'])
            ->post('/discussions/actions/savetopic', [
                'groupId' => $groupId,
                'topicName' => 'Created Topic',
                'message' => 'Created body',
                'captcha' => 'good',
            ])
            ->assertOk()
            ->assertSee('/groups/action-forum/discussions/', false);
        $threadId = (int) \DB::table('cms_forum_threads')->where('group_id', $groupId)->where('topic_title', 'Created Topic')->value('id');
        $this->assertGreaterThan(0, $threadId);
        $this->assertDatabaseHas('cms_forum_replies', [
            'thread_id' => $threadId,
            'poster_id' => $poster->id,
            'message' => 'Created body',
        ]);
        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'topics' => 1,
        ]);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/discussions/actions/previewpost', [
                'groupId' => $groupId.'abc',
                'topicId' => $threadId,
                'message' => 'Malformed group preview',
            ])
            ->assertRedirect('/');

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/discussions/actions/previewpost', [
                'groupId' => $groupId,
                'topicId' => $threadId.'abc',
                'message' => 'Malformed topic preview',
            ])
            ->assertRedirect('/');

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/discussions/actions/previewpost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'message' => 'Reply preview',
            ])
            ->assertOk()
            ->assertSee('RE: Created Topic', false)
            ->assertSee('Reply preview', false);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id, 'captcha-text' => 'good'])
            ->post('/discussions/actions/savetopic', [
                'groupId' => $groupId,
                'topicName' => 'Spam Topic',
                'message' => 'Created body',
                'captcha' => 'good',
            ])
            ->assertOk()
            ->assertSee('Do not spam the forums', false);
    }

    public function test_discussion_settings_reply_and_delete_actions(): void
    {
        $owner = $this->createLegacyUser(['username' => 'SettingsOwner']);
        $member = $this->createLegacyUser([
            'username' => 'SettingsMember',
            'email' => 'settings-member@example.test',
            'figure' => 'hd-195-1',
        ]);
        $this->insertStatistics($owner->id);
        $this->insertStatistics($member->id);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Settings Forum Group',
            'description' => 'Settings forum group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 1,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'settings-forum',
            'created_at' => now(),
        ]);
        \DB::table('groups_memberships')->insert([
            'group_id' => $groupId,
            'user_id' => $member->id,
            'member_rank' => 1,
            'is_pending' => false,
            'created_at' => now(),
        ]);
        $threadId = \DB::table('cms_forum_threads')->insertGetId([
            'topic_title' => 'Settings Topic',
            'poster_id' => $member->id,
            'is_open' => true,
            'is_stickied' => false,
            'views' => 0,
            'group_id' => $groupId,
            'created_at' => now(),
            'modified_at' => now(),
        ]);
        $firstReplyId = \DB::table('cms_forum_replies')->insertGetId([
            'thread_id' => $threadId,
            'message' => 'Original post',
            'poster_id' => $member->id,
            'is_edited' => false,
            'is_deleted' => false,
            'created_at' => now(),
            'modified_at' => now(),
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/opentopicsettings', [
                'groupId' => $groupId,
                'topicId' => $threadId,
            ])
            ->assertOk()
            ->assertSee('Settings Topic', false)
            ->assertSee('topic-settings-form', false);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/opentopicsettings', [
                'groupId' => $groupId.'abc',
                'topicId' => $threadId,
            ])
            ->assertRedirect('/');

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/opentopicsettings', [
                'groupId' => $groupId,
                'topicId' => $threadId.'abc',
            ])
            ->assertRedirect('/');

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id, 'captcha-text' => 'reply'])
            ->post('/discussions/actions/savepost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'message' => 'Second reply',
                'captcha' => 'reply',
            ])
            ->assertOk()
            ->assertSee('Second reply', false);
        $secondReplyId = (int) \DB::table('cms_forum_replies')->where('thread_id', $threadId)->where('message', 'Second reply')->value('id');
        $this->assertGreaterThan(0, $secondReplyId);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id, 'captcha-text' => 'reply'])
            ->post('/discussions/actions/savepost', [
                'groupId' => $groupId.'abc',
                'topicId' => $threadId,
                'message' => 'Malformed save reply',
                'captcha' => 'reply',
            ])
            ->assertRedirect('/');
        $this->assertDatabaseMissing('cms_forum_replies', ['message' => 'Malformed save reply']);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id, 'captcha-text' => 'reply'])
            ->post('/discussions/actions/savepost', [
                'groupId' => $groupId,
                'topicId' => $threadId.'abc',
                'message' => 'Malformed save reply',
                'captcha' => 'reply',
            ])
            ->assertRedirect('/');
        $this->assertDatabaseMissing('cms_forum_replies', ['message' => 'Malformed save reply']);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id, 'captcha-text' => 'edit'])
            ->post('/discussions/actions/updatepost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'postId' => $secondReplyId,
                'page' => 1,
                'message' => 'Blocked edit reply',
                'captcha' => 'edit',
            ])
            ->assertOk()
            ->assertContent('')
            ->assertHeader('X-JSON', '{"captchaError":"true"}');
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'message' => 'Second reply',
            'is_edited' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id, 'captcha-text' => 'edit'])
            ->post('/discussions/actions/updatepost', [
                'groupId' => $groupId.'abc',
                'topicId' => $threadId,
                'postId' => $secondReplyId,
                'page' => 1,
                'message' => 'Malformed edited reply',
                'captcha' => 'mismatch',
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'message' => 'Second reply',
            'is_edited' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id, 'captcha-text' => 'edit'])
            ->post('/discussions/actions/updatepost', [
                'groupId' => $groupId,
                'topicId' => $threadId.'abc',
                'postId' => $secondReplyId,
                'page' => 1,
                'message' => 'Malformed edited reply',
                'captcha' => 'mismatch',
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'message' => 'Second reply',
            'is_edited' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id, 'captcha-text' => 'edit'])
            ->post('/discussions/actions/updatepost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'postId' => $secondReplyId.'abc',
                'page' => 1,
                'message' => 'Malformed edited reply',
                'captcha' => 'mismatch',
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'message' => 'Second reply',
            'is_edited' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id, 'captcha-text' => 'edit'])
            ->post('/discussions/actions/updatepost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'postId' => $secondReplyId,
                'page' => 1,
                'message' => 'Edited reply',
                'captcha' => 'mismatch',
            ])
            ->assertOk()
            ->assertSee('Edited reply', false);
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'message' => 'Edited reply',
            'is_edited' => true,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/deletepost', [
                'groupId' => $groupId.'abc',
                'topicId' => $threadId,
                'postId' => $secondReplyId,
                'page' => 1,
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'is_deleted' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/deletepost', [
                'groupId' => $groupId,
                'topicId' => $threadId.'abc',
                'postId' => $secondReplyId,
                'page' => 1,
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'is_deleted' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/deletepost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'postId' => $secondReplyId.'abc',
                'page' => 1,
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'is_deleted' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/deletepost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'postId' => $secondReplyId,
                'page' => 1,
            ])
            ->assertOk();
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $secondReplyId,
            'is_deleted' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/discussions/actions/deletepost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'postId' => $firstReplyId,
                'page' => 1,
            ])
            ->assertOk();
        $this->assertDatabaseMissing('cms_forum_replies', ['id' => $firstReplyId]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/savetopicsettings', [
                'groupId' => $groupId.'abc',
                'topicId' => $threadId,
                'page' => 1,
                'topicName' => 'Malformed Settings Topic',
                'topicClosed' => 1,
                'topicSticky' => 1,
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_threads', [
            'id' => $threadId,
            'topic_title' => 'Settings Topic',
            'is_open' => true,
            'is_stickied' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/savetopicsettings', [
                'groupId' => $groupId,
                'topicId' => $threadId.'abc',
                'page' => 1,
                'topicName' => 'Malformed Settings Topic',
                'topicClosed' => 1,
                'topicSticky' => 1,
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_threads', [
            'id' => $threadId,
            'topic_title' => 'Settings Topic',
            'is_open' => true,
            'is_stickied' => false,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/savetopicsettings', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'page' => 1,
                'topicName' => 'Renamed Settings Topic',
                'topicClosed' => 1,
                'topicSticky' => 1,
            ])
            ->assertOk()
            ->assertSee('Renamed Settings Topic', false);
        $this->assertDatabaseHas('cms_forum_threads', [
            'id' => $threadId,
            'topic_title' => 'Renamed Settings Topic',
            'is_open' => false,
            'is_stickied' => true,
        ]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/confirm_delete_topic')
            ->assertOk()
            ->assertSee('discussion-action-ok', false);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/deletetopic', [
                'groupId' => $groupId.'abc',
                'topicId' => $threadId,
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_threads', ['id' => $threadId]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/deletetopic', [
                'groupId' => $groupId,
                'topicId' => $threadId.'abc',
            ])
            ->assertRedirect('/');
        $this->assertDatabaseHas('cms_forum_threads', ['id' => $threadId]);

        $this->actingAs($member)
            ->withSession(['authenticated' => true, 'user.id' => $member->id])
            ->post('/discussions/actions/deletetopic', [
                'groupId' => $groupId,
                'topicId' => $threadId,
            ])
            ->assertOk()
            ->assertContent('SUCCESS');
        $this->assertDatabaseMissing('cms_forum_threads', ['id' => $threadId]);
        $this->assertDatabaseMissing('cms_forum_replies', ['thread_id' => $threadId]);
        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'topics' => 0,
        ]);
    }

    public function test_discussion_reply_permissions_match_public_forum_legacy_behavior(): void
    {
        $owner = $this->createLegacyUser(['username' => 'ReplyPermOwner']);
        $outsider = $this->createLegacyUser([
            'username' => 'ReplyPermOutsider',
            'email' => 'reply-perm-outsider@example.test',
            'figure' => 'hd-180-1',
        ]);
        $this->insertStatistics($owner->id);
        $this->insertStatistics($outsider->id);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Reply Permission Group',
            'description' => 'Reply permission group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 1,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 2,
            'alias' => 'reply-permission',
            'created_at' => now(),
        ]);
        $threadId = \DB::table('cms_forum_threads')->insertGetId([
            'topic_title' => 'Public Admin Reply Topic',
            'poster_id' => $owner->id,
            'is_open' => true,
            'is_stickied' => false,
            'views' => 0,
            'group_id' => $groupId,
            'created_at' => now(),
            'modified_at' => now(),
        ]);
        \DB::table('cms_forum_replies')->insert([
            'thread_id' => $threadId,
            'message' => 'Owner opening post',
            'poster_id' => $owner->id,
            'is_edited' => false,
            'is_deleted' => false,
            'created_at' => now(),
            'modified_at' => now(),
        ]);

        $this->actingAs($outsider)
            ->withSession(['authenticated' => true, 'user.id' => $outsider->id, 'captcha-text' => 'reply'])
            ->post('/discussions/actions/savepost', [
                'groupId' => $groupId,
                'topicId' => $threadId,
                'message' => 'Public admin-permission reply',
                'captcha' => 'reply',
            ])
            ->assertOk()
            ->assertSee('Public admin-permission reply', false);

        $this->assertDatabaseHas('cms_forum_replies', [
            'thread_id' => $threadId,
            'poster_id' => $outsider->id,
            'message' => 'Public admin-permission reply',
        ]);
    }

    public function test_group_membership_favourite_tags_and_memberlist_actions(): void
    {
        $owner = $this->createLegacyUser(['username' => 'GroupAdmin', 'email' => 'admin@example.test']);
        $member = $this->createLegacyUser(['username' => 'GroupMember', 'email' => 'member@example.test']);
        $applicant = $this->createLegacyUser(['username' => 'Applicant', 'email' => 'applicant@example.test']);
        $this->insertStatistics($owner->id);
        $this->insertStatistics($member->id);
        $this->insertStatistics($applicant->id);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Action Group',
            'description' => 'Action group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'action-group',
            'created_at' => now(),
        ]);
        \DB::table('groups_memberships')->insert([
            [
                'user_id' => $owner->id,
                'group_id' => $groupId,
                'member_rank' => '3',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $member->id,
                'group_id' => $groupId,
                'member_rank' => '1',
                'is_pending' => false,
                'created_at' => now(),
            ],
        ]);

        $this->actingAs($applicant)
            ->withSession(['authenticated' => true, 'user.id' => $applicant->id])
            ->post('/groups/actions/join', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('groups_memberships', [
            'user_id' => $applicant->id,
            'group_id' => $groupId,
        ]);

        $this->actingAs($applicant)
            ->withSession(['authenticated' => true, 'user.id' => $applicant->id])
            ->post('/groups/actions/join', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('group-action-ok', false);
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $applicant->id,
            'group_id' => $groupId,
            'member_rank' => '1',
            'is_pending' => false,
        ]);

        $this->actingAs($applicant)
            ->withSession(['authenticated' => true, 'user.id' => $applicant->id])
            ->post('/groups/actions/confirm_select_favorite', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($applicant)
            ->withSession(['authenticated' => true, 'user.id' => $applicant->id])
            ->post('/groups/actions/select_favorite', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');
        $applicant->refresh();
        $this->assertSame(0, (int) $applicant->favourite_group);

        $this->actingAs($applicant)
            ->withSession(['authenticated' => true, 'user.id' => $applicant->id])
            ->post('/groups/actions/select_favorite', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('OK', false);
        $applicant->refresh();
        $this->assertSame($groupId, (int) $applicant->favourite_group);

        $this->actingAs($applicant)
            ->withSession(['authenticated' => true, 'user.id' => $applicant->id])
            ->post('/groups/actions/deselect_favorite', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');
        $applicant->refresh();
        $this->assertSame($groupId, (int) $applicant->favourite_group);

        $this->actingAs($applicant)
            ->withSession(['authenticated' => true, 'user.id' => $applicant->id])
            ->post('/groups/actions/leave', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');
        $applicant->refresh();
        $this->assertSame($groupId, (int) $applicant->favourite_group);
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $applicant->id,
            'group_id' => $groupId,
        ]);

        $this->actingAs($applicant)
            ->withSession(['authenticated' => true, 'user.id' => $applicant->id])
            ->post('/groups/actions/leave', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('/groups/'.$groupId.'/id', false);
        $applicant->refresh();
        $this->assertSame(0, (int) $applicant->favourite_group);
        $this->assertDatabaseMissing('groups_memberships', [
            'user_id' => $applicant->id,
            'group_id' => $groupId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/tag/addgrouptag', ['groupId' => $groupId.'abc', 'tagName' => 'broken'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('users_tags', [
            'tag' => 'broken',
            'group_id' => (string) $groupId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/tag/addgrouptag', ['groupId' => $groupId, 'tagName' => 'retro'])
            ->assertOk()
            ->assertSee('valid', false);
        $this->assertDatabaseHas('users_tags', [
            'user_id' => 0,
            'tag' => 'retro',
            'room_id' => '0',
            'group_id' => (string) $groupId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/tag/listgrouptags', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/tag/listgrouptags', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('/tag/retro', false)
            ->assertSee('tag-delete-link', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/groups/memberlist', ['groupId' => $groupId.'abc', 'pageNumber' => 1, 'pending' => 'false'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/groups/memberlist', ['groupId' => $groupId, 'pageNumber' => 1, 'pending' => 'false'])
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('GroupAdmin', false)
            ->assertSee('GroupMember', false)
            ->assertSee('group-memberlist-m-'.$member->id, false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/tag/removegrouptag', ['groupId' => $groupId.'abc', 'tagName' => 'retro'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('users_tags', [
            'tag' => 'retro',
            'group_id' => (string) $groupId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/tag/removegrouptag', ['groupId' => $groupId, 'tagName' => 'retro'])
            ->assertOk()
            ->assertDontSee('/tag/retro', false);
    }

    public function test_exclusive_group_join_creates_pending_membership(): void
    {
        $owner = $this->createLegacyUser(['username' => 'ExclusiveOwner', 'email' => 'exclusive-owner@example.test']);
        $candidate = $this->createLegacyUser(['username' => 'ExclusiveCandidate', 'email' => 'exclusive-candidate@example.test']);
        $this->insertStatistics($owner->id);
        $this->insertStatistics($candidate->id);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Exclusive Group',
            'description' => 'Exclusive group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 1,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'exclusive-group',
            'created_at' => now(),
        ]);

        $this->actingAs($candidate)
            ->withSession(['authenticated' => true, 'user.id' => $candidate->id])
            ->post('/groups/actions/join', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('group-action-ok', false);

        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $candidate->id,
            'group_id' => $groupId,
            'member_rank' => '1',
            'is_pending' => true,
        ]);
    }

    public function test_group_batch_member_moderation_actions(): void
    {
        $owner = $this->createLegacyUser(['username' => 'BatchOwner', 'email' => 'batch-owner@example.test']);
        $admin = $this->createLegacyUser(['username' => 'BatchAdmin', 'email' => 'batch-admin@example.test']);
        $member = $this->createLegacyUser(['username' => 'BatchMember', 'email' => 'batch-member@example.test']);
        $removeMember = $this->createLegacyUser([
            'username' => 'RemoveMember',
            'email' => 'remove-member@example.test',
            'favourite_group' => 1,
        ]);
        $pendingAccept = $this->createLegacyUser(['username' => 'PendingAccept', 'email' => 'pending-accept@example.test']);
        $pendingDecline = $this->createLegacyUser(['username' => 'PendingDecline', 'email' => 'pending-decline@example.test']);
        foreach ([$owner, $admin, $member, $removeMember, $pendingAccept, $pendingDecline] as $user) {
            $this->insertStatistics($user->id);
        }
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Batch Group',
            'description' => 'Batch group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 1,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'batch-group',
            'created_at' => now(),
        ]);
        $removeMember->forceFill(['favourite_group' => $groupId])->save();
        \DB::table('groups_memberships')->insert([
            [
                'user_id' => $owner->id,
                'group_id' => $groupId,
                'member_rank' => '3',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $admin->id,
                'group_id' => $groupId,
                'member_rank' => '2',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $member->id,
                'group_id' => $groupId,
                'member_rank' => '1',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $removeMember->id,
                'group_id' => $groupId,
                'member_rank' => '1',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $pendingAccept->id,
                'group_id' => $groupId,
                'member_rank' => '1',
                'is_pending' => true,
                'created_at' => now(),
            ],
            [
                'user_id' => $pendingDecline->id,
                'group_id' => $groupId,
                'member_rank' => '1',
                'is_pending' => true,
                'created_at' => now(),
            ],
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/groups/batch/confirm_give_rights', ['targetIds' => [$member->id]])
            ->assertOk()
            ->assertSee('group-action-ok', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/groups/batch/give_rights', ['groupId' => $groupId.'abc', 'targetIds' => [$member->id]])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $member->id,
            'group_id' => $groupId,
            'member_rank' => '1',
            'is_pending' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/groups/batch/give_rights', ['groupId' => $groupId, 'targetIds' => [$member->id]])
            ->assertOk()
            ->assertSee('OK', false);
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $member->id,
            'group_id' => $groupId,
            'member_rank' => '2',
            'is_pending' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/groups/batch/revoke_rights', ['groupId' => $groupId.'abc', 'targetIds' => (string) $member->id])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $member->id,
            'group_id' => $groupId,
            'member_rank' => '2',
            'is_pending' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/groups/batch/revoke_rights', ['groupId' => $groupId, 'targetIds' => (string) $member->id])
            ->assertOk()
            ->assertSee('OK', false);
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $member->id,
            'group_id' => $groupId,
            'member_rank' => '1',
            'is_pending' => false,
        ]);

        $this->actingAs($admin)
            ->withSession(['authenticated' => true, 'user.id' => $admin->id])
            ->post('/myhabbo/groups/batch/remove', ['groupId' => $groupId.'abc', 'targetIds' => [$removeMember->id]])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $removeMember->id,
            'group_id' => $groupId,
        ]);
        $removeMember->refresh();
        $this->assertSame($groupId, (int) $removeMember->favourite_group);

        $this->actingAs($admin)
            ->withSession(['authenticated' => true, 'user.id' => $admin->id])
            ->post('/myhabbo/groups/batch/remove', ['groupId' => $groupId, 'targetIds' => [$removeMember->id, $owner->id]])
            ->assertOk()
            ->assertSee('OK', false);
        $this->assertDatabaseMissing('groups_memberships', [
            'user_id' => $removeMember->id,
            'group_id' => $groupId,
        ]);
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $owner->id,
            'group_id' => $groupId,
            'member_rank' => '3',
        ]);
        $removeMember->refresh();
        $this->assertSame(0, (int) $removeMember->favourite_group);

        $this->actingAs($admin)
            ->withSession(['authenticated' => true, 'user.id' => $admin->id])
            ->post('/myhabbo/groups/batch/confirm_accept', ['groupId' => $groupId, 'targetIds' => [$pendingAccept->id]])
            ->assertOk()
            ->assertSee('Batch Group', false);

        $this->actingAs($admin)
            ->withSession(['authenticated' => true, 'user.id' => $admin->id])
            ->post('/myhabbo/groups/batch/confirm_accept', ['groupId' => $groupId.'abc', 'targetIds' => [$pendingAccept->id]])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($admin)
            ->withSession(['authenticated' => true, 'user.id' => $admin->id])
            ->post('/myhabbo/groups/batch/accept', ['groupId' => $groupId.'abc', 'targetIds' => [$pendingAccept->id]])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $pendingAccept->id,
            'group_id' => $groupId,
            'is_pending' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['authenticated' => true, 'user.id' => $admin->id])
            ->post('/myhabbo/groups/batch/accept', ['groupId' => $groupId, 'targetIds' => [$pendingAccept->id]])
            ->assertOk()
            ->assertSee('OK', false);
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $pendingAccept->id,
            'group_id' => $groupId,
            'member_rank' => '1',
            'is_pending' => false,
        ]);

        $this->actingAs($admin)
            ->withSession(['authenticated' => true, 'user.id' => $admin->id])
            ->post('/myhabbo/groups/batch/decline', ['groupId' => $groupId.'abc', 'targetIds' => [$pendingDecline->id]])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $pendingDecline->id,
            'group_id' => $groupId,
            'is_pending' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['authenticated' => true, 'user.id' => $admin->id])
            ->post('/myhabbo/groups/batch/decline', ['groupId' => $groupId, 'targetIds' => [$pendingDecline->id]])
            ->assertOk()
            ->assertSee('OK', false);
        $this->assertDatabaseMissing('groups_memberships', [
            'user_id' => $pendingDecline->id,
            'group_id' => $groupId,
        ]);
    }

    public function test_group_purchase_settings_badge_edit_session_and_delete_routes(): void
    {
        $owner = $this->createLegacyUser(['username' => 'ManageOwner', 'email' => 'manage-owner@example.test']);
        $member = $this->createLegacyUser([
            'username' => 'ManageMember',
            'email' => 'manage-member@example.test',
            'favourite_group' => 1,
        ]);
        $owner->forceFill(['credits' => 50])->save();
        $this->insertStatistics($owner->id);
        $this->insertStatistics($member->id);
        \DB::table('settings')->insert([
            'setting' => 'group.purchase.cost',
            'value' => '15',
        ]);
        app(HavanaConfig::class)->reload();

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/grouppurchase/group_create_form')
            ->assertOk()
            ->assertSee('15', false)
            ->assertSee('purchase-group-form-id', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/grouppurchase/purchase_confirmation', ['name' => '<b>Managed Group</b>'])
            ->assertOk()
            ->assertSee('Managed Group', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/grouppurchase/purchase_ajax', [
                'name' => '<b>Managed Group</b>',
                'description' => '<i>Managed description</i>',
            ])
            ->assertOk()
            ->assertSee('Managed Group', false)
            ->assertSee('purchase-result-success', false);
        $groupId = (int) \DB::table('groups_details')->where('name', 'Managed Group')->value('id');
        $this->assertGreaterThan(0, $groupId);
        $this->assertDatabaseHas('groups_memberships', [
            'user_id' => $owner->id,
            'group_id' => $groupId,
            'member_rank' => '3',
            'is_pending' => false,
        ]);
        $owner->refresh();
        $this->assertSame(35, (int) $owner->credits);

        $roomId = \DB::table('rooms')->insertGetId([
            'owner_id' => (string) $owner->id,
            'name' => 'Managed Room',
            'description' => 'Room description',
            'model' => 'model_s',
            'visitors_now' => 0,
            'visitors_max' => 25,
            'rating' => 0,
            'group_id' => 0,
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/group_settings', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('Managed Group', false)
            ->assertSee('Managed Room', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/group_settings', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/update_group_settings', [
                'groupId' => $groupId.'abc',
                'name' => 'Malformed Update',
                'description' => 'Should not update',
                'url' => 'malformed-update',
                'type' => 2,
                'forumType' => 1,
                'newTopicPermission' => 1,
                'roomId' => $roomId,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'name' => 'Managed Group',
            'alias' => null,
            'room_id' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/update_group_settings', [
                'groupId' => $groupId,
                'name' => 'Updated Group Name That Is Longer Than Thirty Characters',
                'description' => 'Updated description',
                'url' => 'updated-group!',
                'type' => 1,
                'forumType' => 1,
                'newTopicPermission' => 2,
                'roomId' => $roomId,
            ])
            ->assertOk()
            ->assertSee('Editing group settings successful', false)
            ->assertSee('/groups/updatedgroup', false);
        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'name' => 'Updated Group Name That Is Lon',
            'description' => 'Updated description',
            'group_type' => 1,
            'forum_type' => 1,
            'forum_premission' => 2,
            'room_id' => $roomId,
            'alias' => 'updatedgroup',
        ]);
        $this->assertDatabaseHas('rooms', [
            'id' => $roomId,
            'group_id' => $groupId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/check_group_url', ['url' => 'checked'])
            ->assertOk()
            ->assertSee('/groups/checked', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/show_badge_editor', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('BadgeEditor.swf', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/show_badge_editor', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/update_group_badge', ['groupId' => $groupId.'abc', 'code' => 'badgebad'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'badge' => 'b0503Xs09114s05013s05015',
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/update_group_badge', ['groupId' => $groupId, 'code' => 'b0503!!abc'])
            ->assertRedirect('/groups/updatedgroup');
        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'badge' => 'b0503abc',
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/groups/actions/startEditingSession/'.$groupId)
            ->assertRedirect('/groups/updatedgroup');
        $this->assertDatabaseHas('groups_edit_sessions', [
            'user_id' => $owner->id,
            'group_id' => $groupId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'groupEditSession' => $groupId])
            ->post('/groups/actions/saveEditingSession', ['background' => 'bg_colour_01:extra'])
            ->assertOk();
        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'background' => 'bg_colour_01',
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'groupEditSession' => $groupId])
            ->post('/groups/actions/cancelEditingSession')
            ->assertRedirect('/groups/updatedgroup');
        $this->assertDatabaseMissing('groups_edit_sessions', [
            'user_id' => $owner->id,
            'group_id' => $groupId,
        ]);

        $member->forceFill(['favourite_group' => $groupId])->save();
        \DB::table('groups_memberships')->insert([
            'user_id' => $member->id,
            'group_id' => $groupId,
            'member_rank' => '1',
            'is_pending' => false,
            'created_at' => now(),
        ]);
        \DB::table('users_tags')->insert([
            'user_id' => 0,
            'tag' => 'managed',
            'room_id' => '0',
            'group_id' => (string) $groupId,
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/confirm_delete_group', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('Updated Group Name That Is Lon', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/confirm_delete_group', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/delete_group', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('groups_details', ['id' => $groupId]);
        $this->assertDatabaseHas('groups_memberships', ['group_id' => $groupId]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/groups/actions/delete_group', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('group-action-ok', false);
        $this->assertDatabaseMissing('groups_details', ['id' => $groupId]);
        $this->assertDatabaseMissing('groups_memberships', ['group_id' => $groupId]);
        $this->assertDatabaseMissing('users_tags', ['group_id' => (string) $groupId]);
        $this->assertDatabaseHas('rooms', ['id' => $roomId, 'group_id' => 0]);
        $member->refresh();
        $this->assertSame(0, (int) $member->favourite_group);
    }

    public function test_home_routes_render_tags_favourite_group_and_id_lookup(): void
    {
        $owner = $this->createLegacyUser([
            'username' => 'HomeOwner',
            'email' => 'home-owner@example.test',
            'motto' => 'Home motto',
        ]);
        $viewer = $this->createLegacyUser([
            'username' => 'HomeViewer',
            'email' => 'home-viewer@example.test',
        ]);
        $this->insertStatistics($owner->id, ['guestbook_unread_messages' => 3]);
        $this->insertStatistics($viewer->id);
        \DB::table('homes_details')->insert([
            'user_id' => $owner->id,
            'background' => 'bg_pattern_space',
        ]);
        \DB::table('cms_stickers_catalogue')->insert([
            [
                'id' => 401,
                'name' => 'Home Duck',
                'description' => 'A placed sticker',
                'type' => '1',
                'data' => 'home_duck',
                'price' => 1,
                'amount' => 1,
                'category_id' => 100,
                'min_rank' => 1,
                'widget_type' => 0,
            ],
            [
                'id' => 402,
                'name' => 'Placed Note',
                'description' => 'A placed note',
                'type' => '3',
                'data' => 'stickienote',
                'price' => 1,
                'amount' => 1,
                'category_id' => 101,
                'min_rank' => 1,
                'widget_type' => 0,
            ],
            [
                'id' => 10200,
                'name' => 'Guestbook',
                'description' => 'Guestbook widget',
                'type' => '2',
                'data' => 'guestbookwidget',
                'price' => 0,
                'amount' => 1,
                'category_id' => 100,
                'min_rank' => 1,
                'widget_type' => 1,
            ],
            [
                'id' => 10400,
                'name' => 'Badges widget',
                'description' => 'Badge display',
                'type' => '2',
                'data' => 'badgeswidget',
                'price' => 0,
                'amount' => 1,
                'category_id' => 100,
                'min_rank' => 1,
                'widget_type' => 1,
            ],
        ]);
        $stickerId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '12',
            'y' => '34',
            'z' => '56',
            'sticker_id' => 401,
            'skin_id' => 0,
            'group_id' => 0,
            'text' => '',
            'is_placed' => true,
            'extra_data' => '',
        ]);
        $noteId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '78',
            'y' => '90',
            'z' => '12',
            'sticker_id' => 402,
            'skin_id' => 1,
            'group_id' => 0,
            'text' => 'Placed [b]note[/b]',
            'is_placed' => true,
            'extra_data' => '',
        ]);
        $guestbookWidgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '101',
            'y' => '202',
            'z' => '303',
            'sticker_id' => 10200,
            'skin_id' => 1,
            'group_id' => 0,
            'text' => '',
            'is_placed' => true,
            'extra_data' => 'private',
        ]);
        $badgeWidgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '111',
            'y' => '222',
            'z' => '333',
            'sticker_id' => 10400,
            'skin_id' => 1,
            'group_id' => 0,
            'text' => '',
            'is_placed' => true,
            'extra_data' => '',
        ]);
        \DB::table('cms_guestbook_entries')->insert([
            'user_id' => $viewer->id,
            'home_id' => $owner->id,
            'group_id' => 0,
            'message' => 'Home guestbook [b]entry[/b]',
            'created_at' => now(),
        ]);
        \DB::table('users_badges')->insert([
            [
                'user_id' => $owner->id,
                'badge' => 'ACH_01',
                'equipped' => false,
                'slot_id' => 0,
            ],
            [
                'user_id' => $owner->id,
                'badge' => 'ACH_02',
                'equipped' => false,
                'slot_id' => 0,
            ],
        ]);
        \DB::table('users_tags')->insert([
            [
                'user_id' => $owner->id,
                'tag' => 'homeone',
                'room_id' => '0',
                'group_id' => '0',
                'created_at' => now(),
            ],
            [
                'user_id' => $owner->id,
                'tag' => 'hometwo',
                'room_id' => '0',
                'group_id' => '0',
                'created_at' => now(),
            ],
        ]);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Home Favourite',
            'description' => 'Favourite group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'home-favourite',
            'created_at' => now(),
        ]);
        $owner->forceFill(['favourite_group' => $groupId])->save();

        $this->actingAs($viewer)
            ->withSession(['authenticated' => true, 'user.id' => $viewer->id])
            ->get('/home/HomeOwner')
            ->assertOk()
            ->assertSee('HomeOwner', false)
            ->assertSee('b_bg_pattern_space', false)
            ->assertSee('/home/HomeViewer', false)
            ->assertSee('sticker-'.$stickerId, false)
            ->assertSee('s_home_duck', false)
            ->assertSee('stickie-'.$noteId, false)
            ->assertSee('Placed <b>note</b>', false)
            ->assertSee('widget-'.$guestbookWidgetId, false)
            ->assertSee('guestbook-size">1', false)
            ->assertSee('gb-private', false)
            ->assertSee('Home guestbook <b>entry</b>', false)
            ->assertSee('widget-'.$badgeWidgetId, false)
            ->assertSee('ACH_01.gif', false)
            ->assertSee('ACH_02.gif', false)
            ->assertSee('badgeListTotalPages" value="1', false);

        $this->get('/home/'.$owner->id.'/id')
            ->assertOk()
            ->assertSee('HomeOwner', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/home/HomeOwner')
            ->assertOk();
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $owner->id,
            'guestbook_unread_messages' => 0,
        ]);

        $this->actingAs($viewer)
            ->withSession(['authenticated' => true, 'user.id' => $viewer->id])
            ->post('/myhabbo/tag/list', ['accountId' => $owner->id])
            ->assertOk()
            ->assertSee('/tag/homeone', false)
            ->assertSee('/tag/hometwo', false);
    }

    public function test_home_edit_session_save_and_cancel_routes(): void
    {
        $owner = $this->createLegacyUser([
            'username' => 'EditHomeOwner',
            'email' => 'edit-home-owner@example.test',
        ]);
        $this->insertStatistics($owner->id);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/myhabbo/startSession/'.$owner->id)
            ->assertRedirect('/home/EditHomeOwner');
        $this->assertDatabaseHas('homes_details', [
            'user_id' => $owner->id,
            'background' => 'bg_pattern_abstract2',
        ]);
        $this->assertDatabaseHas('homes_edit_sessions', [
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/home/EditHomeOwner')
            ->assertOk()
            ->assertSee('editmode', false)
            ->assertSee('/myhabbo/save', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/save', ['background' => 'bg_pattern_checked:extra'])
            ->assertOk()
            ->assertSee("waitAndGo('/home/EditHomeOwner')", false);
        $this->assertDatabaseHas('homes_details', [
            'user_id' => $owner->id,
            'background' => 'bg_pattern_checked',
        ]);
        $this->assertDatabaseMissing('homes_edit_sessions', [
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/myhabbo/startSession/'.$owner->id)
            ->assertRedirect('/home/EditHomeOwner');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->get('/myhabbo/cancel/'.$owner->id)
            ->assertRedirect('/home/EditHomeOwner');
        $this->assertDatabaseMissing('homes_edit_sessions', [
            'user_id' => $owner->id,
        ]);
    }

    public function test_home_widget_rating_groupinfo_and_member_paging_routes(): void
    {
        $owner = $this->createLegacyUser([
            'username' => 'RatingOwner',
            'email' => 'rating-owner@example.test',
        ]);
        $rater = $this->createLegacyUser([
            'username' => 'RatingRater',
            'email' => 'rating-rater@example.test',
        ]);
        $secondRater = $this->createLegacyUser([
            'username' => 'RatingSecond',
            'email' => 'rating-second@example.test',
        ]);
        $admin = $this->createLegacyUser([
            'username' => 'WidgetAdmin',
            'email' => 'widget-admin@example.test',
            'favourite_group' => 1,
        ]);
        $member = $this->createLegacyUser([
            'username' => 'WidgetMember',
            'email' => 'widget-member@example.test',
        ]);
        $owner->forceFill(['last_online' => now()->subDays(3)])->save();
        $admin->forceFill(['last_online' => now()->subDays(2)])->save();
        $member->forceFill(['last_online' => now()])->save();
        foreach ([$owner, $rater, $secondRater, $admin, $member] as $user) {
            $this->insertStatistics($user->id);
        }
        $ratingWidgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '0',
            'y' => '0',
            'z' => '1',
            'sticker_id' => 10800,
            'skin_id' => 1,
            'group_id' => -1,
            'text' => '',
            'is_placed' => true,
            'extra_data' => '',
        ]);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Widget Group',
            'description' => 'Widget group description',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'widget-group',
            'created_at' => now(),
        ]);
        $admin->forceFill(['favourite_group' => $groupId])->save();
        \DB::table('groups_memberships')->insert([
            [
                'user_id' => $owner->id,
                'group_id' => $groupId,
                'member_rank' => '3',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $admin->id,
                'group_id' => $groupId,
                'member_rank' => '2',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $member->id,
                'group_id' => $groupId,
                'member_rank' => '1',
                'is_pending' => false,
                'created_at' => now(),
            ],
        ]);
        $memberWidgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '0',
            'y' => '0',
            'z' => '2',
            'sticker_id' => 10700,
            'skin_id' => 1,
            'group_id' => $groupId,
            'text' => '',
            'is_placed' => true,
            'extra_data' => '',
        ]);

        $this->actingAs($rater)
            ->withSession(['authenticated' => true, 'user.id' => $rater->id])
            ->get('/myhabbo/rating/rate?ratingId='.$ratingWidgetId.'&givenRate=5')
            ->assertOk()
            ->assertSee('rating-average', false)
            ->assertSee('width:150px;', false)
            ->assertSee('1', false);
        $this->assertDatabaseHas('homes_ratings', [
            'user_id' => $rater->id,
            'home_id' => $owner->id,
            'rating' => 5,
        ]);

        $this->actingAs($rater)
            ->withSession(['authenticated' => true, 'user.id' => $rater->id])
            ->get('/myhabbo/rating/rate?ratingId='.$ratingWidgetId.'&givenRate=4')
            ->assertOk()
            ->assertContent('');
        $this->assertSame(1, \DB::table('homes_ratings')->where('home_id', $owner->id)->count());

        $this->actingAs($secondRater)
            ->withSession(['authenticated' => true, 'user.id' => $secondRater->id])
            ->get('/myhabbo/rating/rate?ratingId='.$ratingWidgetId.'&givenRate=4abc')
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('homes_ratings', [
            'user_id' => $secondRater->id,
            'home_id' => $owner->id,
        ]);

        $this->actingAs($secondRater)
            ->withSession(['authenticated' => true, 'user.id' => $secondRater->id])
            ->get('/myhabbo/rating/rate?ratingId='.$ratingWidgetId.'abc&givenRate=4')
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('homes_ratings', [
            'user_id' => $secondRater->id,
            'home_id' => $owner->id,
        ]);

        $this->actingAs($secondRater)
            ->withSession(['authenticated' => true, 'user.id' => $secondRater->id])
            ->get('/myhabbo/rating/rate?ratingId='.$ratingWidgetId.'&givenRate=4')
            ->assertOk()
            ->assertSee('rating-average', false)
            ->assertSee('width:120px;', false)
            ->assertSee('2', false);
        $this->assertDatabaseHas('homes_ratings', [
            'user_id' => $secondRater->id,
            'home_id' => $owner->id,
            'rating' => 4,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/myhabbo/rating/reset_ratings?ratingId='.$ratingWidgetId)
            ->assertOk()
            ->assertSee('rating-average', false)
            ->assertSee('width:30px;', false);
        $this->assertSame(0, \DB::table('homes_ratings')->where('home_id', $owner->id)->count());

        $this->post('/myhabbo/groups/groupinfo', ['groupId' => $groupId])
            ->assertOk()
            ->assertSee('Widget Group', false)
            ->assertSee('Widget group description', false)
            ->assertSee('/groups/widget-group', false);

        $this->post('/myhabbo/groups/groupinfo', ['groupId' => $groupId.'abc'])
            ->assertOk()
            ->assertContent('');

        $this->post('/myhabbo/avatarlist/membersearchpaging', [
            'groupId' => 999999,
            'widgetId' => $memberWidgetId,
            'pageNumber' => 1,
        ])
            ->assertOk()
            ->assertSee('WidgetAdmin', false)
            ->assertSee('WidgetMember', false)
            ->assertSeeInOrder(['WidgetMember', 'WidgetAdmin'], false)
            ->assertSee('avatar-list-'.$memberWidgetId.'-'.$admin->id, false)
            ->assertSee('favourite_group_icon', false);

        $this->post('/myhabbo/avatarlist/membersearchpaging', [
            'widgetId' => $memberWidgetId.'abc',
            'pageNumber' => 1,
        ])
            ->assertOk()
            ->assertContent('');

        $this->post('/myhabbo/avatarlist/membersearchpaging', [
            'widgetId' => $memberWidgetId,
            'pageNumber' => 1,
            'searchString' => 'WidgetA',
        ])
            ->assertOk()
            ->assertSee('WidgetAdmin', false)
            ->assertDontSee('WidgetMember', false)
            ->assertSee('totalPages" value="1', false);
    }

    public function test_home_guestbook_preview_add_remove_and_configure_routes(): void
    {
        $owner = $this->createLegacyUser([
            'username' => 'GuestbookOwner',
            'email' => 'guestbook-owner@example.test',
        ]);
        $poster = $this->createLegacyUser([
            'username' => 'GuestbookPoster',
            'email' => 'guestbook-poster@example.test',
            'figure' => 'hd-190-1',
        ]);
        $stranger = $this->createLegacyUser([
            'username' => 'GuestbookStranger',
            'email' => 'guestbook-stranger@example.test',
        ]);
        foreach ([$owner, $poster, $stranger] as $user) {
            $this->insertStatistics($user->id);
        }

        $widgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '12',
            'y' => '24',
            'z' => '6',
            'sticker_id' => 10200,
            'skin_id' => 1,
            'group_id' => -1,
            'text' => '',
            'is_placed' => true,
            'extra_data' => 'public',
        ]);

        \DB::table('wordfilter')->insert([
            'word' => 'blocked',
            'is_bannable' => false,
            'is_filterable' => true,
        ]);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/myhabbo/guestbook/preview', ['message' => '<b>Hello</b> blocked [b]world[/b]'])
            ->assertOk()
            ->assertSee('GuestbookPoster', false)
            ->assertSee('&lt;b&gt;Hello&lt;/b&gt;', false)
            ->assertSee('blocked', false)
            ->assertSee('<b>world</b>', false)
            ->assertDontSee('bobba', false);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/myhabbo/guestbook/preview', ['message' => '[b]'.str_repeat('x', 200).'[/b]'])
            ->assertOk()
            ->assertSee('<b>'.str_repeat('x', 197), false)
            ->assertDontSee(str_repeat('x', 198), false);
        $this->assertSame(0, \DB::table('cms_guestbook_entries')->count());

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/myhabbo/guestbook/add', [
                'widgetId' => $widgetId,
                'message' => 'First guestbook entry',
            ])
            ->assertOk()
            ->assertSee('guestbook-entry-', false)
            ->assertSee('GuestbookPoster', false)
            ->assertSee('First guestbook entry', false);
        $this->assertDatabaseHas('cms_guestbook_entries', [
            'user_id' => $poster->id,
            'home_id' => $owner->id,
            'group_id' => 0,
            'message' => 'First guestbook entry',
        ]);
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $owner->id,
            'guestbook_unread_messages' => 1,
        ]);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/myhabbo/guestbook/add', [
                'widgetId' => $widgetId.'abc',
                'message' => 'Malformed widget post',
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('cms_guestbook_entries', [
            'message' => 'Malformed widget post',
        ]);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/myhabbo/guestbook/add', [
                'widgetId' => $widgetId,
                'message' => 'blocked guestbook entry',
            ])
            ->assertOk()
            ->assertSee('bobba guestbook entry', false);
        $this->assertDatabaseMissing('cms_guestbook_entries', [
            'message' => 'blocked guestbook entry',
        ]);
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $owner->id,
            'guestbook_unread_messages' => 2,
        ]);

        \DB::table('cms_stickers')->where('id', $widgetId)->update(['extra_data' => 'private']);

        $this->actingAs($stranger)
            ->withSession(['authenticated' => true, 'user.id' => $stranger->id])
            ->post('/myhabbo/guestbook/add', [
                'widgetId' => $widgetId,
                'message' => 'Blocked private post',
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('cms_guestbook_entries', [
            'message' => 'Blocked private post',
        ]);

        \DB::table('messenger_friends')->insert([
            'from_id' => $poster->id,
            'to_id' => $owner->id,
            'category_id' => 0,
        ]);

        $this->actingAs($poster)
            ->withSession(['authenticated' => true, 'user.id' => $poster->id])
            ->post('/myhabbo/guestbook/add', [
                'widgetId' => $widgetId,
                'message' => 'Private friend post',
            ])
            ->assertOk()
            ->assertSee('Private friend post', false);

        $entryId = (int) \DB::table('cms_guestbook_entries')->where('message', 'Private friend post')->value('id');
        $this->actingAs($stranger)
            ->withSession(['authenticated' => true, 'user.id' => $stranger->id])
            ->post('/myhabbo/guestbook/remove', [
                'widgetId' => $widgetId,
                'entryId' => $entryId,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_guestbook_entries', ['id' => $entryId]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/guestbook/remove', [
                'widgetId' => $widgetId,
                'entryId' => $entryId.'abc',
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_guestbook_entries', ['id' => $entryId]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/guestbook/remove', [
                'widgetId' => $widgetId.'abc',
                'entryId' => $entryId,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_guestbook_entries', ['id' => $entryId]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/guestbook/remove', [
                'widgetId' => $widgetId,
                'entryId' => $entryId,
            ])
            ->assertOk()
            ->assertSee('GuestbookWidget', false);
        $this->assertDatabaseMissing('cms_guestbook_entries', ['id' => $entryId]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/guestbook/configure', ['widgetId' => $widgetId.'abc'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'extra_data' => 'private',
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/guestbook/configure', ['widgetId' => $widgetId])
            ->assertOk()
            ->assertSee('guestbook-type', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'extra_data' => 'public',
        ]);
    }

    public function test_home_badge_and_friend_widget_paging_routes(): void
    {
        $owner = $this->createLegacyUser([
            'username' => 'WidgetListOwner',
            'email' => 'widget-list-owner@example.test',
        ]);
        $firstFriend = $this->createLegacyUser([
            'username' => 'AlphaFriend',
            'email' => 'alpha-friend@example.test',
            'figure' => 'hd-201-1',
            'last_online' => now()->subDay(),
        ]);
        $secondFriend = $this->createLegacyUser([
            'username' => 'BetaFriend',
            'email' => 'beta-friend@example.test',
            'figure' => 'hd-202-1',
            'last_online' => now()->subDays(2),
        ]);
        foreach ([$owner, $firstFriend, $secondFriend] as $user) {
            $this->insertStatistics($user->id);
        }

        $badgeWidgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '0',
            'y' => '0',
            'z' => '1',
            'sticker_id' => 10400,
            'skin_id' => 1,
            'group_id' => -1,
            'text' => '',
            'is_placed' => true,
            'extra_data' => '',
        ]);
        $friendWidgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '0',
            'y' => '0',
            'z' => '2',
            'sticker_id' => 10500,
            'skin_id' => 1,
            'group_id' => -1,
            'text' => '',
            'is_placed' => true,
            'extra_data' => '',
        ]);

        for ($i = 1; $i <= 18; $i++) {
            \DB::table('users_badges')->insert([
                'user_id' => $owner->id,
                'badge' => sprintf('ACH_%02d', $i),
                'equipped' => $i <= 5,
                'slot_id' => $i,
            ]);
        }
        \DB::table('messenger_friends')->insert([
            [
                'from_id' => $firstFriend->id,
                'to_id' => $owner->id,
                'category_id' => 0,
            ],
            [
                'from_id' => $secondFriend->id,
                'to_id' => $owner->id,
                'category_id' => 0,
            ],
        ]);

        $this->post('/myhabbo/badgelist/badgepaging', [
            'widgetId' => $badgeWidgetId,
            'pageNumber' => 2,
        ])
            ->assertOk()
            ->assertSee('ACH_17.gif', false)
            ->assertSee('ACH_18.gif', false)
            ->assertSee('2 / 2', false)
            ->assertSee('badgeListTotalPages" value="2', false);

        $this->post('/myhabbo/badgelist/badgepaging', [
            'widgetId' => $badgeWidgetId.'abc',
            'pageNumber' => 2,
        ])
            ->assertOk()
            ->assertContent('');

        $this->post('/myhabbo/avatarlist/friendsearchpaging', [
            'widgetId' => $friendWidgetId,
            'pageNumber' => 1,
            'searchString' => 'Alpha',
        ])
            ->assertOk()
            ->assertSee('AlphaFriend', false)
            ->assertSee('hd-201-1', false)
            ->assertSee('avatar-list-'.$friendWidgetId.'-'.$firstFriend->id, false)
            ->assertDontSee('BetaFriend', false)
            ->assertSee('totalPages" value="1', false);

        $this->post('/myhabbo/avatarlist/friendsearchpaging', [
            'widgetId' => $friendWidgetId.'abc',
            'pageNumber' => 1,
            'searchString' => 'Alpha',
        ])
            ->assertOk()
            ->assertContent('');

        $this->post('/myhabbo/avatarlist/friendsearchpaging', [
            'widgetId' => $friendWidgetId,
            'pageNumber' => 1,
            'searchString' => 'Nobody',
        ])
            ->assertOk()
            ->assertSee('1 - 0 / 1', false)
            ->assertDontSee('AlphaFriend', false)
            ->assertDontSee('BetaFriend', false)
            ->assertDontSee("don't have any friends", false);
    }

    public function test_note_editor_preview_search_place_edit_and_delete_routes(): void
    {
        $owner = $this->createLegacyUser([
            'username' => 'NoteOwner',
            'email' => 'note-owner@example.test',
        ]);
        $friend = $this->createLegacyUser([
            'username' => 'NoteFriend',
            'email' => 'note-friend@example.test',
        ]);
        foreach ([$owner, $friend] as $user) {
            $this->insertStatistics($user->id);
        }
        $roomId = \DB::table('rooms')->insertGetId([
            'owner_id' => (string) $owner->id,
            'name' => 'Note Room',
            'description' => 'Room for notes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Note Group',
            'description' => 'Group for notes',
            'owner_id' => $owner->id,
            'room_id' => $roomId,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'note-group',
            'created_at' => now(),
        ]);
        $noteId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '0',
            'y' => '0',
            'z' => '0',
            'sticker_id' => 13,
            'skin_id' => 1,
            'group_id' => 0,
            'text' => '',
            'is_placed' => false,
            'extra_data' => '',
        ]);
        \DB::table('homes_edit_sessions')->insert([
            'user_id' => $owner->id,
            'expire' => time() + 900,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/noteeditor/editor', [
                'skin' => 3,
                'noteText' => 'Draft note',
            ])
            ->assertOk()
            ->assertSee('webstore-notes-form', false)
            ->assertSee('value="3" id="webstore-notes-skins-select-metalskin" selected', false)
            ->assertSee('Draft note', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/noteeditor/preview', [
                'skin' => 2,
                'noteText' => '<b>Hi</b> [b]there[/b]',
            ])
            ->assertOk()
            ->assertSee('n_skin_speechbubbleskin', false)
            ->assertSee('&lt;b&gt;Hi&lt;/b&gt;', false)
            ->assertSee('<b>there</b>', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/noteeditor/preview', [
                'skin' => 2,
                'noteText' => '[b]'.str_repeat('x', 500).'[/b]',
            ])
            ->assertOk()
            ->assertSee('<b>'.str_repeat('x', 497), false)
            ->assertDontSee(str_repeat('x', 498), false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/myhabbo/linktool/search?scope=1&query=NoteF')
            ->assertOk()
            ->assertSee('type="habbo"', false)
            ->assertSee('NoteFriend', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/myhabbo/linktool/search?scope=2&query=Note')
            ->assertOk()
            ->assertSee('type="room"', false)
            ->assertSee('Note Room', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->get('/myhabbo/linktool/search?scope=3&query=Note')
            ->assertOk()
            ->assertSee('type="group"', false)
            ->assertSee('Note Group', false)
            ->assertSee((string) $groupId, false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/noteeditor/place', [
                'skin' => 6,
                'noteText' => 'Placed [i]note[/i]',
            ])
            ->assertOk()
            ->assertHeader('X-JSON', (string) $noteId)
            ->assertSee('stickie-'.$noteId, false)
            ->assertSee('<i>note</i>', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $noteId,
            'x' => '20',
            'y' => '30',
            'z' => '1',
            'skin_id' => 6,
            'group_id' => 0,
            'text' => 'Placed [i]note[/i]',
            'is_placed' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/stickie/edit', [
                'stickieId' => $noteId.'abc',
                'skinId' => 8,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $noteId,
            'skin_id' => 6,
            'is_placed' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/stickie/edit', [
                'stickieId' => $noteId,
                'skinId' => 8,
            ])
            ->assertOk()
            ->assertHeader('X-JSON', '{"id":"'.$noteId.'","cssClass":"n_skin_defaultskin","type":"stickie"}')
            ->assertSee('n_skin_defaultskin', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $noteId,
            'skin_id' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/stickie/delete', ['stickieId' => $noteId.'abc'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', ['id' => $noteId]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/stickie/delete', ['stickieId' => $noteId])
            ->assertOk()
            ->assertContent('SUCCESS');
        $this->assertDatabaseMissing('cms_stickers', ['id' => $noteId]);
    }

    public function test_home_inventory_preview_place_remove_and_widget_actions(): void
    {
        $owner = $this->createLegacyUser([
            'username' => 'InventoryOwner',
            'email' => 'inventory-owner@example.test',
        ]);
        $rater = $this->createLegacyUser([
            'username' => 'InventoryRater',
            'email' => 'inventory-rater@example.test',
        ]);
        $this->insertStatistics($owner->id);
        $this->insertStatistics($rater->id);
        \DB::table('cms_stickers_categories')->insert([
            [
                'id' => 100,
                'name' => 'Animals',
                'min_rank' => 1,
                'category_type' => 1,
            ],
            [
                'id' => 104,
                'name' => 'Backgrounds',
                'min_rank' => 1,
                'category_type' => 2,
            ],
            [
                'id' => 199,
                'name' => 'Staff Only',
                'min_rank' => 5,
                'category_type' => 1,
            ],
        ]);
        \DB::table('cms_stickers_catalogue')->insert([
            [
                'id' => 501,
                'name' => 'Duck Sticker',
                'description' => 'A sticker',
                'type' => '1',
                'data' => 'duck',
                'price' => 1,
                'amount' => 1,
                'category_id' => 100,
                'min_rank' => 1,
                'widget_type' => 0,
            ],
            [
                'id' => 10900,
                'name' => 'Rating widget',
                'description' => 'Rate my page',
                'type' => '2',
                'data' => 'ratingwidget',
                'price' => 0,
                'amount' => 1,
                'category_id' => 100,
                'min_rank' => 1,
                'widget_type' => 1,
            ],
        ]);
        $stickerId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '0',
            'y' => '0',
            'z' => '0',
            'sticker_id' => 501,
            'skin_id' => 0,
            'group_id' => 0,
            'text' => '',
            'is_placed' => false,
            'extra_data' => '',
        ]);
        $widgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '0',
            'y' => '0',
            'z' => '0',
            'sticker_id' => 10900,
            'skin_id' => 1,
            'group_id' => 0,
            'text' => '',
            'is_placed' => false,
            'extra_data' => '',
        ]);
        \DB::table('homes_edit_sessions')->insert([
            'user_id' => $owner->id,
            'expire' => time() + 900,
        ]);
        \DB::table('homes_ratings')->insert([
            'user_id' => $rater->id,
            'home_id' => $owner->id,
            'rating' => 4,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/store/inventory')
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('subcategory-1-100-stickers', false)
            ->assertSee('Animals', false)
            ->assertSee('subcategory-1-104-stickers', false)
            ->assertSee('Backgrounds', false)
            ->assertDontSee('Staff Only', false)
            ->assertSee('inventory-item-'.$stickerId, false)
            ->assertSee('s_duck_pre', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/store/inventory_preview', [
                'itemId' => $stickerId,
                'type' => 'stickers',
            ])
            ->assertOk()
            ->assertHeader('X-JSON', '["s_duck_pre","duck","Duck Sticker","Sticker",null,1]');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/store/inventory_preview', [
                'itemId' => $stickerId.'abc',
                'type' => 'stickers',
            ])
            ->assertOk()
            ->assertHeader('X-JSON', '["","","","Sticker",null,1]');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/sticker/place_sticker', [
                'selectedStickerId' => $stickerId.'abc',
                'zindex' => 42,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $stickerId,
            'is_placed' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/sticker/place_sticker', [
                'selectedStickerId' => $stickerId,
                'zindex' => 42,
            ])
            ->assertOk()
            ->assertHeader('X-JSON', '["'.$stickerId.'"]')
            ->assertSee('sticker-'.$stickerId, false)
            ->assertSee('s_duck', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $stickerId,
            'x' => '20',
            'y' => '30',
            'z' => '42',
            'is_placed' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/sticker/remove_sticker', ['stickerId' => $stickerId.'abc'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $stickerId,
            'is_placed' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/sticker/remove_sticker', ['stickerId' => $stickerId])
            ->assertOk()
            ->assertContent('SUCCESS');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $stickerId,
            'x' => '0',
            'y' => '0',
            'z' => '0',
            'is_placed' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/store/inventory_items', ['type' => 'widgets'])
            ->assertOk()
            ->assertSee('inventory-item-p-'.$widgetId, false)
            ->assertSee('Rating widget', false);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/widget/add', [
                'widgetId' => $widgetId.'abc',
                'zindex' => 7,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'is_placed' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/widget/add', [
                'widgetId' => $widgetId,
                'zindex' => 7,
            ])
            ->assertOk()
            ->assertHeader('X-JSON', '["'.$widgetId.'"]')
            ->assertSee('RatingWidget', false)
            ->assertSee('width: 120px;', false)
            ->assertSee('1 votes total', false)
            ->assertSee('(1 users voted 4 or better', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'x' => '10',
            'y' => '10',
            'z' => '7',
            'is_placed' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/widget/edit', [
                'widgetId' => $widgetId.'abc',
                'skinId' => 8,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'skin_id' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/widget/edit', [
                'widgetId' => $widgetId,
                'skinId' => 8,
            ])
            ->assertOk()
            ->assertHeader('X-JSON', '{"id":"'.$widgetId.'","cssClass":"w_skin_defaultskin","type":"widget"}');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'skin_id' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/widget/delete', ['widgetId' => $widgetId.'abc'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'is_placed' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id, 'homeEditSession' => $owner->id])
            ->post('/myhabbo/widget/delete', ['widgetId' => $widgetId])
            ->assertOk()
            ->assertContent('SUCCESS');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'is_placed' => false,
        ]);
    }

    public function test_home_web_store_catalog_preview_confirm_and_purchase_routes(): void
    {
        $buyer = $this->createLegacyUser([
            'username' => 'StoreBuyer',
            'email' => 'store-buyer@example.test',
            'credits' => 20,
        ]);
        $this->insertStatistics($buyer->id);
        \DB::table('cms_stickers_categories')->insert([
            [
                'id' => 100,
                'name' => 'Animals',
                'min_rank' => 1,
                'category_type' => 1,
            ],
            [
                'id' => 104,
                'name' => 'Backgrounds',
                'min_rank' => 1,
                'category_type' => 2,
            ],
            [
                'id' => 101,
                'name' => 'Notes',
                'min_rank' => 1,
                'category_type' => 3,
            ],
        ]);
        \DB::table('cms_stickers_catalogue')->insert([
            [
                'id' => 601,
                'name' => 'Duck Sticker',
                'description' => 'A duck',
                'type' => '1',
                'data' => 'duck',
                'price' => 3,
                'amount' => 2,
                'category_id' => 100,
                'min_rank' => 1,
                'widget_type' => 0,
            ],
            [
                'id' => 602,
                'name' => 'Blue Background',
                'description' => 'A background',
                'type' => '4',
                'data' => 'blue_bg',
                'price' => 4,
                'amount' => 1,
                'category_id' => 104,
                'min_rank' => 1,
                'widget_type' => 0,
            ],
            [
                'id' => 603,
                'name' => 'Stickie Notes',
                'description' => 'Notes',
                'type' => '3',
                'data' => 'stickienote',
                'price' => 5,
                'amount' => 3,
                'category_id' => 101,
                'min_rank' => 1,
                'widget_type' => 0,
            ],
            [
                'id' => 604,
                'name' => 'Empty Sticker',
                'description' => 'No inventory rows',
                'type' => '1',
                'data' => 'empty',
                'price' => 2,
                'amount' => 0,
                'category_id' => 100,
                'min_rank' => 1,
                'widget_type' => 0,
            ],
            [
                'id' => 605,
                'name' => 'Rating Widget',
                'description' => 'Rate my page',
                'type' => '2',
                'data' => 'ratingwidget',
                'price' => 6,
                'amount' => 1,
                'category_id' => 100,
                'min_rank' => 1,
                'widget_type' => 1,
            ],
        ]);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/main')
            ->assertOk()
            ->assertHeader('X-JSON')
            ->assertSee('subcategory-1-100-stickers', false)
            ->assertSee('webstore-item-601', false)
            ->assertSee('s_duck_pre', false);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/items', ['subCategoryId' => 104])
            ->assertOk()
            ->assertSee('webstore-item-602', false)
            ->assertSee('b_blue_bg_pre', false);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/items', ['subCategoryId' => '104abc'])
            ->assertNotFound();

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/preview', ['productId' => 602])
            ->assertOk()
            ->assertHeader('X-JSON', '[{"bgCssClass":"b_blue_bg","itemCount":1,"previewCssClass":"b_blue_bg_pre","titleKey":"Blue Background"}]')
            ->assertSee('webstore-purchase', false);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/preview', ['productId' => '602abc'])
            ->assertNotFound();

        $widgetPreview = $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/preview', ['productId' => 605])
            ->assertOk()
            ->assertSee('webstore-purchase', false)
            ->assertSee('6 credits', false);
        $this->assertFalse($widgetPreview->headers->has('X-JSON'));

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/purchase_confirm', ['productId' => 601])
            ->assertOk()
            ->assertSee('webstore-confirm-submit', false)
            ->assertDontSee('webstore-confirm-cancel-only', false);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/purchase_confirm', ['productId' => '601abc'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/background_warning')
            ->assertOk()
            ->assertSee('webstore-warning-ok', false);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/purchase_stickers', ['selectedId' => '601abc'])
            ->assertNotFound();
        $this->assertSame(0, \DB::table('cms_stickers')->where('user_id', $buyer->id)->where('sticker_id', 601)->count());

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/purchase_stickers', ['selectedId' => 601])
            ->assertOk()
            ->assertContent('OK');
        $this->assertSame(2, \DB::table('cms_stickers')->where('user_id', $buyer->id)->where('sticker_id', 601)->count());
        $this->assertDatabaseHas('users', [
            'id' => $buyer->id,
            'credits' => 47,
        ]);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/purchase_backgrounds', ['selectedId' => 602])
            ->assertOk()
            ->assertContent('OK');
        $this->assertDatabaseHas('cms_stickers', [
            'user_id' => $buyer->id,
            'sticker_id' => 602,
            'is_placed' => false,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $buyer->id,
            'credits' => 43,
        ]);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/purchase_stickie_notes', ['selectedId' => 603])
            ->assertOk()
            ->assertContent('OK');
        $this->assertSame(3, \DB::table('cms_stickers')->where('user_id', $buyer->id)->where('sticker_id', 603)->count());
        $this->assertDatabaseHas('users', [
            'id' => $buyer->id,
            'credits' => 38,
        ]);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/purchase_stickers', ['selectedId' => 604])
            ->assertOk()
            ->assertContent('OK');
        $this->assertSame(0, \DB::table('cms_stickers')->where('user_id', $buyer->id)->where('sticker_id', 604)->count());
        $this->assertDatabaseHas('users', [
            'id' => $buyer->id,
            'credits' => 36,
        ]);

        $this->actingAs($buyer)
            ->withSession(['authenticated' => true, 'user.id' => $buyer->id])
            ->post('/myhabbo/store/purchase_backgrounds', ['selectedId' => 601])
            ->assertNotFound();
    }

    public function test_trax_select_song_and_song_payload_routes(): void
    {
        $owner = $this->createLegacyUser([
            'username' => 'TraxOwner',
            'email' => 'trax-owner@example.test',
        ]);
        $other = $this->createLegacyUser([
            'username' => 'TraxOther',
            'email' => 'trax-other@example.test',
        ]);
        foreach ([$owner, $other] as $user) {
            $this->insertStatistics($user->id);
        }
        \DB::table('cms_stickers_catalogue')->insert([
            'id' => 10800,
            'name' => 'Traxplayer',
            'description' => 'Play Trax on your homepage.',
            'type' => '2',
            'data' => 'traxplayerwidget',
            'price' => 0,
            'amount' => 1,
            'category_id' => 100,
            'min_rank' => 1,
            'widget_type' => 1,
        ]);
        $widgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '10',
            'y' => '20',
            'z' => '3',
            'sticker_id' => 10800,
            'skin_id' => 1,
            'group_id' => 0,
            'text' => '',
            'is_placed' => true,
            'extra_data' => '',
        ]);
        $songId = \DB::table('soundmachine_songs')->insertGetId([
            'user_id' => $owner->id,
            'title' => 'Owner Song',
            'item_id' => 777,
            'length' => 12,
            'data' => '1:2,3,4:2:5,6:3:7:4:8:',
            'burnt' => false,
        ]);
        $otherSongId = \DB::table('soundmachine_songs')->insertGetId([
            'user_id' => $other->id,
            'title' => 'Other Song',
            'item_id' => 778,
            'length' => 12,
            'data' => '1:9:',
            'burnt' => false,
        ]);
        $songDiskDefinitionId = \DB::table('items_definitions')->insertGetId([
            'sprite' => 'song_disk',
            'name' => 'Song Disk',
        ]);
        $songDiskItemId = \DB::table('items')->insertGetId([
            'user_id' => $owner->id,
            'room_id' => 0,
            'definition_id' => $songDiskDefinitionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('soundmachine_disks')->insert([
            'item_id' => $songDiskItemId,
            'soundmachine_id' => 0,
            'slot_id' => 0,
            'song_id' => $otherSongId,
            'burned_at' => time(),
        ]);
        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Trax Group',
            'description' => 'Group with trax',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'trax-group',
            'created_at' => now(),
        ]);
        $groupWidgetId = \DB::table('cms_stickers')->insertGetId([
            'user_id' => $owner->id,
            'x' => '10',
            'y' => '20',
            'z' => '3',
            'sticker_id' => 10800,
            'skin_id' => 1,
            'group_id' => $groupId,
            'text' => '',
            'is_placed' => true,
            'extra_data' => '',
        ]);

        $this->actingAs($other)
            ->withSession(['authenticated' => true, 'user.id' => $other->id])
            ->post('/myhabbo/traxplayer/select_song', [
                'widgetId' => $widgetId,
                'songId' => $otherSongId,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'extra_data' => '',
        ]);

        $this->actingAs($other)
            ->withSession(['authenticated' => true, 'user.id' => $other->id])
            ->post('/myhabbo/traxplayer/select_song', [
                'widgetId' => $groupWidgetId,
                'songId' => $otherSongId,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $groupWidgetId,
            'extra_data' => '',
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/traxplayer/select_song', [
                'widgetId' => $widgetId,
                'songId' => $songId,
            ])
            ->assertOk()
            ->assertSee('/trax/song/'.$songId, false)
            ->assertSee('traxplayer.swf', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'extra_data' => (string) $songId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/traxplayer/select_song', [
                'widgetId' => $widgetId.'abc',
                'songId' => $otherSongId,
            ])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'extra_data' => (string) $songId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/traxplayer/select_song', [
                'widgetId' => $widgetId,
                'songId' => $otherSongId,
            ])
            ->assertOk()
            ->assertSee('/trax/song/'.$otherSongId, false)
            ->assertSee('traxplayer.swf', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'extra_data' => (string) $otherSongId,
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/traxplayer/select_song', [
                'widgetId' => $widgetId,
                'songId' => $songId.'abc',
            ])
            ->assertOk()
            ->assertSee('traxplayer-content', false)
            ->assertDontSee('traxplayer.swf', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'extra_data' => '',
        ]);

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/traxplayer/select_song', [
                'widgetId' => $groupWidgetId,
                'songId' => $songId,
            ])
            ->assertOk()
            ->assertSee('/trax/song/'.$songId, false)
            ->assertSee('traxplayer.swf', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $groupWidgetId,
            'extra_data' => (string) $songId,
        ]);

        $this->get('/trax/song/'.$songId)
            ->assertOk()
            ->assertContent('status=0&name=Owner Song&author=TraxOwner&track1=2,3,4&track2=5,6&track3=7&track4=8');

        $this->actingAs($owner)
            ->withSession(['authenticated' => true, 'user.id' => $owner->id])
            ->post('/myhabbo/traxplayer/select_song', [
                'widgetId' => $widgetId,
                'songId' => 0,
            ])
            ->assertOk()
            ->assertSee('traxplayer-content', false)
            ->assertDontSee('traxplayer.swf', false);
        $this->assertDatabaseHas('cms_stickers', [
            'id' => $widgetId,
            'extra_data' => '',
        ]);

        $this->get('/trax/song/not-a-number')->assertOk()->assertContent('');
    }

    public function test_lightweight_habblet_avatar_event_feed_and_club_routes(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'HabbletUser',
            'email' => 'habblet-user@example.test',
            'credits' => 80,
        ]);
        $friend = $this->createLegacyUser([
            'username' => 'AvatarTarget',
            'email' => 'avatar-target@example.test',
            'figure' => 'hd-999-1',
            'is_online' => true,
        ]);
        foreach ([$user, $friend] as $account) {
            $this->insertStatistics($account->id);
        }
        \DB::table('settings')->insert([
            ['setting' => 'club.gift.timeunit', 'value' => 'HOURS'],
            ['setting' => 'club.gift.interval', 'value' => '2'],
        ]);
        app(HavanaConfig::class)->reload();
        $user->forceFill(['credits' => 80])->save();
        $roomId = \DB::table('rooms')->insertGetId([
            'owner_id' => (string) $user->id,
            'name' => 'Event Room',
            'description' => 'Room event host',
            'visitors_now' => 15,
            'visitors_max' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $expiredRoomId = \DB::table('rooms')->insertGetId([
            'owner_id' => (string) $user->id,
            'name' => 'Expired Event Room',
            'description' => 'Room event host',
            'visitors_now' => 1,
            'visitors_max' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('rooms_events')->insert([
            'room_id' => $roomId,
            'user_id' => $user->id,
            'category_id' => 7,
            'name' => 'Pool Party',
            'description' => 'Bring your towel',
            'expire_time' => time() + 3600,
            'tags' => '',
        ]);
        \DB::table('rooms_events')->insert([
            'room_id' => $expiredRoomId,
            'user_id' => $user->id,
            'category_id' => 7,
            'name' => 'Closed Party',
            'description' => 'This event already ended',
            'expire_time' => time() - 60,
            'tags' => '',
        ]);
        $firstAlert = \DB::table('cms_alerts')->insertGetId([
            'user_id' => $user->id,
            'alert_type' => 'PRESENT',
            'message' => 'First',
            'is_disabled' => false,
            'created_at' => now()->subMinute(),
        ]);
        $secondAlert = \DB::table('cms_alerts')->insertGetId([
            'user_id' => $user->id,
            'alert_type' => 'HC_EXPIRED',
            'message' => 'Second',
            'is_disabled' => false,
            'created_at' => now(),
        ]);
        $disabledAlert = \DB::table('cms_alerts')->insertGetId([
            'user_id' => $user->id,
            'alert_type' => 'PRESENT',
            'message' => 'Disabled',
            'is_disabled' => true,
            'created_at' => now()->addMinute(),
        ]);

        $this->post('/myhabbo/avatarlist/avatarinfo', ['anAccountId' => $friend->id])
            ->assertOk()
            ->assertSee('AvatarTarget', false)
            ->assertSee('hd-999-1', false);

        $this->post('/myhabbo/avatarlist/avatarinfo', ['anAccountId' => $friend->id.'abc'])
            ->assertOk()
            ->assertContent('');

        $this->post('/habblet/ajax/load_events', ['eventTypeId' => 7])
            ->assertOk()
            ->assertSee('Pool Party', false)
            ->assertDontSee('Closed Party', false)
            ->assertSee('room-occupancy-3', false)
            ->assertSee('HabbletUser', false);

        $this->post('/habblet/ajax/load_events', ['eventTypeId' => '7abc'])
            ->assertOk()
            ->assertContent('');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/removeFeedItem', ['feedItemIndex' => 0])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('cms_alerts', ['id' => $disabledAlert]);
        $this->assertDatabaseHas('cms_alerts', ['id' => $secondAlert]);
        $this->assertDatabaseHas('cms_alerts', ['id' => $firstAlert]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/removeFeedItem', ['feedItemIndex' => '0abc'])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_alerts', ['id' => $secondAlert]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/removeFeedItem', ['feedItemIndex' => 0])
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseMissing('cms_alerts', ['id' => $secondAlert]);
        $this->assertDatabaseHas('cms_alerts', ['id' => $firstAlert]);

        $this->post('/habboclub/habboclub_confirm', ['optionNumber' => 1])
            ->assertOk()
            ->assertSee('25', false)
            ->assertSee('31', false)
            ->assertSee('habboclub.showSubscriptionResultWindow(1', false);

        $this->post('/habboclub/habboclub_confirm', ['optionNumber' => 5])
            ->assertOk()
            ->assertContent('');

        $this->post('/habboclub/habboclub_confirm', ['optionNumber' => 4])
            ->assertOk()
            ->assertSee('-1', false)
            ->assertSee('habboclub.showSubscriptionResultWindow(4', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habboclub/habboclub_subscribe', ['optionNumber' => 4])
            ->assertOk()
            ->assertSee('successfully subscribed', false);
        $optionFourUpdatedAt = (int) \DB::table('users_statistics')
            ->where('user_id', $user->id)
            ->value('club_member_time_updated');
        $this->assertGreaterThanOrEqual(time() + 7190, $optionFourUpdatedAt);
        $this->assertLessThanOrEqual(time() + 7210, $optionFourUpdatedAt);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits' => 80,
            'club_expiration' => 0,
        ]);
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'gifts_due' => 0,
        ]);
        $this->assertDatabaseMissing('users_transactions', [
            'user_id' => $user->id,
            'description' => 'Habbo Club purchase',
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habboclub/habboclub_subscribe', ['optionNumber' => 1])
            ->assertOk()
            ->assertSee('successfully subscribed to Habbo Club.', false);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits' => 55,
            'club_subscribed' => 0,
        ]);
        $this->assertGreaterThan(time(), (int) \DB::table('users')->where('id', $user->id)->value('club_expiration'));
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'gifts_due' => 1,
        ]);
        $firstGiftDue = \DB::table('users_statistics')
            ->where('user_id', $user->id)
            ->value('club_gift_due');
        $firstMemberUpdatedAt = (int) \DB::table('users_statistics')
            ->where('user_id', $user->id)
            ->value('club_member_time_updated');
        $this->assertNotNull($firstGiftDue);
        $this->assertGreaterThanOrEqual(time() + 7190, $firstMemberUpdatedAt);
        $this->assertLessThanOrEqual(time() + 7210, $firstMemberUpdatedAt);
        $this->assertDatabaseHas('users_transactions', [
            'user_id' => $user->id,
            'description' => 'Habbo Club purchase',
            'credit_cost' => 25,
        ]);

        $oldGiftDue = now()->subDay();
        \DB::table('users_statistics')->where('user_id', $user->id)->update([
            'gifts_due' => 0,
            'club_gift_due' => $oldGiftDue,
        ]);

        $this->actingAs($user->fresh())
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habboclub/habboclub_subscribe', ['optionNumber' => 1])
            ->assertOk()
            ->assertSee('successfully subscribed', false);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits' => 30,
            'club_subscribed' => 0,
        ]);
        $this->assertDatabaseHas('users_statistics', [
            'user_id' => $user->id,
            'gifts_due' => 1,
        ]);
        $this->assertNotSame(
            $oldGiftDue->toDateTimeString(),
            (string) \DB::table('users_statistics')->where('user_id', $user->id)->value('club_gift_due'),
        );

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/habblet/ajax/habboclub_enddate')
            ->assertOk()
            ->assertSee('Club', false);

        $hcAlert = \DB::table('cms_alerts')->insertGetId([
            'user_id' => $user->id,
            'alert_type' => 'HC_EXPIRED',
            'message' => 'Expired',
            'is_disabled' => false,
            'created_at' => now(),
        ]);
        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habboclub/habboclub_reminder_remove')
            ->assertOk()
            ->assertContent('');
        $this->assertDatabaseHas('cms_alerts', [
            'id' => $hcAlert,
            'is_disabled' => true,
        ]);
    }

    public function test_collectibles_confirm_and_purchase_routes(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'CollectableUser',
            'email' => 'collectable-user@example.test',
        ]);
        $this->insertStatistics($user->id);
        $user->forceFill(['credits' => 20, 'pixels' => 5])->save();

        \DB::table('settings')->insert([
            'setting' => 'collectables.page',
            'value' => '51',
        ]);
        app(HavanaConfig::class)->reload();
        \DB::table('catalogue_collectables')->insert([
            'store_page' => 51,
            'admin_page' => 83,
            'expiry' => time() + 86400,
            'lifetime' => 2678400,
            'current_position' => 0,
            'class_names' => 'rare_dragon,rare_other',
        ]);
        \DB::table('items_definitions')->insert([
            'id' => 900,
            'sprite' => 'rare_dragon',
            'name' => 'Rare Dragon',
            'description' => 'Collectable fire rare',
        ]);
        \DB::table('catalogue_items')->insert([
            'id' => 700,
            'sale_code' => 'rare_dragon',
            'page_id' => '83',
            'order_id' => 5,
            'price_coins' => 7,
            'price_pixels' => 2,
            'amount' => 2,
            'definition_id' => 900,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/collectiblesConfirm')
            ->assertOk()
            ->assertSee('Rare Dragon', false)
            ->assertSee('7', false)
            ->assertSee('collectibles-purchase', false);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/collectiblesPurchase')
            ->assertOk()
            ->assertSee("You've successfully bought a Rare Dragon", false);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits' => 13,
            'pixels' => 3,
        ]);
        $this->assertSame(2, \DB::table('items')->where('user_id', $user->id)->where('definition_id', 900)->count());
        $this->assertDatabaseHas('users_transactions', [
            'user_id' => $user->id,
            'catalogue_id' => '700',
            'amount' => 2,
            'description' => 'Collectable Rare Dragon purchase',
            'credit_cost' => 7,
            'pixel_cost' => 2,
        ]);

        $user->forceFill(['credits' => 20, 'pixels' => 1])->save();
        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/collectiblesPurchase')
            ->assertOk()
            ->assertSee("Purchasing the collectable failed. You don't have enough pixels.", false);
        $this->assertSame(2, \DB::table('items')->where('user_id', $user->id)->where('definition_id', 900)->count());

        $user->forceFill(['credits' => 6, 'pixels' => 5])->save();
        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/collectiblesPurchase')
            ->assertOk()
            ->assertSee("Purchasing the collectable failed. You don't have enough credits.", false);
        $this->assertSame(2, \DB::table('items')->where('user_id', $user->id)->where('definition_id', 900)->count());

        $user->forceFill(['credits' => 20, 'pixels' => 5])->save();
        \DB::table('users_bans')->insert([
            'ban_type' => 'USER_ID',
            'banned_value' => (string) $user->id,
            'message' => 'Collectables purchase ban',
            'banned_until' => now()->addDay(),
            'banned_at' => now(),
            'banned_by' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->post('/habblet/ajax/collectiblesPurchase')
            ->assertRedirect('/account/banned');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'credits' => 20,
            'pixels' => 5,
        ]);
        $this->assertSame(2, \DB::table('items')->where('user_id', $user->id)->where('definition_id', 900)->count());
    }

    public function test_remaining_legacy_public_route_parity_endpoints(): void
    {
        $this->get('/maintenance')
            ->assertOk()
            ->assertSee('maintenance', false);

        $this->get('/credits/club/tryout')
            ->assertOk()
            ->assertSee('habboclub-tryout', false)
            ->assertSee('showClubSelections', false);

        $this->post('/habblet/ajax/habboclub_gift')
            ->assertOk()
            ->assertContent('');

        $this->post('/habblet/ajax/habboclub_gift', [
            'month' => '1',
            'catalogpage' => '0',
        ])
            ->assertOk()
            ->assertSee('hc-catalog-giftPicture', false)
            ->assertSee('club_sofa.png', false);
    }

    public function test_account_banned_route_matches_active_user_bans(): void
    {
        $user = $this->createLegacyUser([
            'username' => 'BannedUser',
            'email' => 'banned-user@example.test',
            'remember_token' => 'remember-me',
        ]);
        $user->forceFill(['machine_id' => 'machine#banned'])->save();
        $this->insertStatistics($user->id);

        $this->get('/account/banned')
            ->assertRedirect('/');

        $this->actingAs($user)
            ->withSession(['authenticated' => true, 'user.id' => $user->id])
            ->get('/account/banned')
            ->assertRedirect('/me');

        \DB::table('users_bans')->insert([
            'ban_type' => 'USER_ID',
            'banned_value' => (string) $user->id,
            'message' => 'Testing route parity',
            'banned_until' => now()->addDay(),
            'banned_at' => now(),
            'banned_by' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                'authenticated' => true,
                'user.id' => $user->id,
                'authenticatedHousekeeping' => true,
                'minimailLabel' => 'inbox',
                'lastBrowsedPage' => '/community',
            ])
            ->get('/account/banned')
            ->assertOk()
            ->assertSee('You have been banned from Habbo.', false)
            ->assertSee('Testing route parity', false)
            ->assertCookie('SECURITY_KEY', 'machinebanned')
            ->assertSessionHas('page', 'banned')
            ->assertSessionMissing('authenticated')
            ->assertSessionMissing('user.id')
            ->assertSessionMissing('authenticatedHousekeeping')
            ->assertSessionMissing('minimailLabel')
            ->assertSessionMissing('lastBrowsedPage');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'remember_token' => null,
        ]);
    }

    public function test_housekeeping_login_dashboard_and_logout_routes(): void
    {
        $regular = $this->createLegacyUser([
            'username' => 'RegularUser',
            'email' => 'regular@example.test',
            'rank' => 1,
        ]);
        $moderator = $this->createLegacyUser([
            'username' => 'ModeratorUser',
            'email' => 'moderator@example.test',
            'rank' => 5,
            'credits' => 0,
        ]);
        $this->insertStatistics($regular->id);
        $this->insertStatistics($moderator->id);

        $this->get('/allseeingeye/hk')
            ->assertOk()
            ->assertSee('Log in', false)
            ->assertSee('hkusername', false);

        $this->post('/allseeingeye/hk/login', [
            'hkusername' => 'RegularUser',
            'hkpassword' => 'secret123',
        ])->assertRedirect('/allseeingeye/hk');

        $this->followRedirects($this->post('/allseeingeye/hk/login', [
            'hkusername' => 'RegularUser',
            'hkpassword' => 'secret123',
        ]))
            ->assertOk()
            ->assertSee("You don't have permission", false);

        $this->post('/allseeingeye/hk/login', [
            'hkusername' => 'ModeratorUser',
            'hkpassword' => 'secret123',
        ])->assertRedirect('/allseeingeye/hk');

        $this->withSession([
            'housekeeping.authenticated' => true,
            'user.id' => $moderator->id,
        ])
            ->get('/allseeingeye/hk?zerocoins&sort=last_online')
            ->assertOk()
            ->assertSee('Logged in as:', false)
            ->assertSee('ModeratorUser', false)
            ->assertSee('Hotel Statistics', false);

        $this->withSession([
            'housekeeping.authenticated' => true,
            'user.id' => $moderator->id,
        ])
            ->get('/allseeingeye/hk/logout')
            ->assertRedirect('/allseeingeye/hk');

        $this->get('/allseeingeye/hk')
            ->assertOk()
            ->assertSee('Log in', false);
    }

    public function test_housekeeping_user_search_edit_create_bans_and_imitate_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'AdminUser',
            'email' => 'admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $target = $this->createLegacyUser([
            'username' => 'TargetUser',
            'email' => 'target@example.test',
            'motto' => 'Target mission',
            'machine_id' => 'machine-abc',
        ]);
        $target->forceFill(['rank' => 1, 'credits' => 12, 'pixels' => 7, 'machine_id' => 'machine-abc'])->save();
        $this->insertStatistics($admin->id);
        $this->insertStatistics($target->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/users/search', [
                'searchField' => 'username',
                'searchType' => 'contains',
                'searchQuery' => 'Target',
            ])
            ->assertOk()
            ->assertSee('Search Results', false)
            ->assertSee('TargetUser', false)
            ->assertSee('Target mission', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/users/search', [
                'searchField' => 'mission',
                'searchType' => 'contains',
                'searchQuery' => 'Target mission',
            ])
            ->assertOk()
            ->assertDontSee('TargetUser', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/users/edit?id='.$target->id, [
                'id' => $target->id,
                'username' => 'IgnoredName',
                'email' => 'updated-target@example.test',
                'figure' => 'hd-190-1',
                'motto' => 'Updated mission',
                'credits' => '34',
                'pixels' => '21',
            ])
            ->assertOk()
            ->assertSee('The user has been successfully saved', false)
            ->assertSee('updated-target@example.test', false);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'username' => 'TargetUser',
            'email' => 'updated-target@example.test',
            'figure' => 'hd-190-1',
            'motto' => 'Updated mission',
            'credits' => 34,
            'pixels' => 21,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/users/edit?id='.$target->id, [
                'id' => $target->id,
                'username' => 'IgnoredName',
                'email' => 'not-an-email',
                'figure' => 'hd-191-1',
                'motto' => 'Invalid email still saves',
                'credits' => '35',
                'pixels' => '22',
            ])
            ->assertOk()
            ->assertSee('The user has been successfully saved', false);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'email' => 'not-an-email',
            'figure' => 'hd-191-1',
            'motto' => 'Invalid email still saves',
            'credits' => 35,
            'pixels' => 22,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/users/edit', [
                'id' => $target->id.'abc',
                'username' => 'IgnoredName',
                'email' => 'malformed-target@example.test',
                'figure' => 'hd-192-1',
                'motto' => 'Malformed mission',
                'credits' => '36',
                'pixels' => '23',
            ])
            ->assertOk()
            ->assertSee('You did not select a user to edit', false);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'email' => 'not-an-email',
            'figure' => 'hd-191-1',
            'motto' => 'Invalid email still saves',
            'credits' => 35,
            'pixels' => 22,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/users/create', [
                'username' => 'CreatedUser',
                'password' => 'secret123',
                'confirmpassword' => 'secret123',
                'email' => 'created@example.test',
                'figure' => 'hd-200-1',
                'mission' => 'Created mission',
            ])
            ->assertOk()
            ->assertSee('The new user has been successfully created.', false);

        $created = User::query()->where('username', 'CreatedUser')->firstOrFail();
        $this->assertTrue(app(LegacyPasswordHasher::class)->check('secret123', (string) $created->password));
        $this->assertDatabaseHas('users_statistics', ['user_id' => $created->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/api/ban?username=TargetUser')
            ->assertOk()
            ->assertSee('User has been banned', false);

        $this->assertDatabaseHas('users_bans', [
            'ban_type' => 'USER_ID',
            'banned_value' => (string) $target->id,
            'message' => 'Banned for breaking the HabboWay',
            'banned_by' => $admin->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('users_bans', [
            'ban_type' => 'MACHINE_ID',
            'banned_value' => 'machine-abc',
            'banned_by' => $admin->id,
            'is_active' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/bans?sort=banned_until')
            ->assertOk()
            ->assertSee('Banned for breaking the HabboWay', false)
            ->assertSee('TargetUser', false)
            ->assertSee('AdminUser', false);

        $this->followingRedirects()
            ->withSession($staffSession)
            ->get('/allseeingeye/hk/users/imitate/TargetUser')
            ->assertOk()
            ->assertSee('You have been banned from', false)
            ->assertSee('Banned for breaking the HabboWay', false);
    }

    public function test_housekeeping_transaction_lookup_and_item_tracking_routes(): void
    {
        $moderator = $this->createLegacyUser([
            'username' => 'TransactionMod',
            'email' => 'transaction-mod@example.test',
        ]);
        $moderator->forceFill(['rank' => 5])->save();
        $target = $this->createLegacyUser([
            'username' => 'TransactionUser',
            'email' => 'transaction-user@example.test',
        ]);
        $this->insertStatistics($moderator->id);
        $this->insertStatistics($target->id);

        \DB::table('users_transactions')->insert([
            [
                'user_id' => $target->id,
                'item_id' => '555,556',
                'catalogue_id' => '88',
                'amount' => 2,
                'description' => 'Rare sofa bundle purchase',
                'credit_cost' => 15,
                'pixel_cost' => 3,
                'created_at' => now(),
                'is_visible' => true,
            ],
            [
                'user_id' => $target->id,
                'item_id' => '555',
                'catalogue_id' => '88',
                'amount' => 1,
                'description' => 'Rare sofa exact purchase',
                'credit_cost' => 10,
                'pixel_cost' => 2,
                'created_at' => now()->subMinute(),
                'is_visible' => true,
            ],
            [
                'user_id' => $target->id,
                'item_id' => '777',
                'catalogue_id' => '99',
                'amount' => 1,
                'description' => 'Hidden moderator transaction',
                'credit_cost' => 0,
                'pixel_cost' => 0,
                'created_at' => now()->subDay(),
                'is_visible' => false,
            ],
            [
                'user_id' => $target->id,
                'item_id' => '999',
                'catalogue_id' => '100',
                'amount' => 1,
                'description' => 'Older transaction',
                'credit_cost' => 1,
                'pixel_cost' => 0,
                'created_at' => now()->subMonthsNoOverflow(2),
                'is_visible' => true,
            ],
        ]);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $moderator->id,
        ];

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/transaction/lookup', [
                'searchQuery' => 'TransactionUser',
            ])
            ->assertOk()
            ->assertSee('Transaction Lookup', false)
            ->assertSee('Rare sofa bundle purchase', false)
            ->assertSee('Rare sofa exact purchase', false)
            ->assertSee('Hidden moderator transaction', false)
            ->assertSee('track_item?id=555', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/transaction/lookup?searchQuery='.$target->id)
            ->assertOk()
            ->assertSee('Rare sofa bundle purchase', false)
            ->assertSee('Rare sofa exact purchase', false)
            ->assertSee('Hidden moderator transaction', false)
            ->assertDontSee('Older transaction', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/transaction/track_item?id=555')
            ->assertOk()
            ->assertSee('Transaction Item Lookup', false)
            ->assertSee('Rare sofa exact purchase', false)
            ->assertDontSee('Rare sofa bundle purchase', false)
            ->assertDontSee('Hidden moderator transaction', false);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/transaction/lookup')
            ->assertRedirect('/allseeingeye/hk');
    }

    public function test_housekeeping_configurations_and_articles_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'SiteAdmin',
            'email' => 'site-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $categoryId = \DB::table('article_categories')->insertGetId([
            'label' => 'News',
            'category_index' => 'news',
        ]);
        \DB::table('settings')->insert([
            'setting' => 'site.name',
            'value' => 'Old Hotel',
        ]);
        app(HavanaConfig::class)->reload();

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        $topStoryRoot = sys_get_temp_dir().'/havana-topstory-'.uniqid();
        mkdir($topStoryRoot.'/c_images/Top_Story_Images', 0777, true);
        file_put_contents($topStoryRoot.'/c_images/Top_Story_Images/zeta.png', '');
        file_put_contents($topStoryRoot.'/c_images/Top_Story_Images/notes.txt', '');
        file_put_contents($topStoryRoot.'/c_images/Top_Story_Images/Alpha.JPG', '');
        file_put_contents($topStoryRoot.'/c_images/Top_Story_Images/middle.gif', '');
        config(['havana.public_path' => $topStoryRoot]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/configurations', [
                'site.name' => 'New Hotel',
                'maintenance' => 'true',
            ])
            ->assertOk()
            ->assertSee('All configuration values have been saved successfully!', false)
            ->assertSee('New Hotel', false);

        $this->assertDatabaseHas('settings', [
            'setting' => 'site.name',
            'value' => 'New Hotel',
        ]);
        $this->assertDatabaseHas('settings', [
            'setting' => 'maintenance',
            'value' => 'true',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/articles/create')
            ->assertOk()
            ->assertSeeInOrder(['Alpha.JPG', 'middle.gif', 'zeta.png'], false)
            ->assertDontSee('notes.txt', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/articles/create', [
                'title' => 'Housekeeping News',
                'categories' => ['NeWs'],
                'shortstory' => 'Short story',
                'fullstory' => "Full <b>story</b>\nline",
                'topstory' => 'attention_topstory.png',
                'topstoryOverride' => '',
                'articleimage' => 'article.png',
                'published' => 'true',
                'futurePublished' => 'true',
                'datePublished' => now()->format('Y-m-d\TH:i'),
                'authorOverride' => 'Staff Override',
            ])
            ->assertRedirect('/allseeingeye/hk/articles');

        $article = \DB::table('site_articles')->where('title', 'Housekeeping News')->first();
        $this->assertNotNull($article);
        $this->assertDatabaseHas('site_articles', [
            'id' => $article->id,
            'author_id' => $admin->id,
            'short_story' => 'Short story',
            'is_published' => true,
            'is_future_published' => true,
        ]);
        $this->assertDatabaseHas('site_articles_categories', [
            'article_id' => $article->id,
            'category_id' => $categoryId,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/articles')
            ->assertOk()
            ->assertSee('Housekeeping News', false)
            ->assertSee('Staff Override', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/articles/edit?id='.$article->id, [
                'title' => 'Updated News',
                'categories' => ['NeWs'],
                'shortstory' => 'Updated short',
                'fullstory' => 'Updated full',
                'topstory' => 'attention_topstory.png',
                'topstoryOverride' => 'https://example.test/top.png',
                'articleimage' => 'updated.png',
                'published' => 'true',
                'datePublished' => now()->format('Y-m-d\TH:i'),
                'authorOverride' => '',
            ])
            ->assertOk()
            ->assertSee('The article was successfully saved', false)
            ->assertSee('Updated News', false);

        $this->assertDatabaseHas('site_articles', [
            'id' => $article->id,
            'title' => 'Updated News',
            'short_story' => 'Updated short',
            'topstory_override' => 'https://example.test/top.png',
            'article_image' => 'updated.png',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/articles/edit?id='.$article->id.'abc', [
                'title' => 'Malformed News',
                'categories' => ['NeWs'],
                'shortstory' => 'Malformed short',
                'fullstory' => 'Malformed full',
                'topstory' => 'attention_topstory.png',
                'articleimage' => 'malformed.png',
                'published' => 'true',
                'datePublished' => now()->format('Y-m-d\TH:i'),
            ])
            ->assertOk()
            ->assertSee('There was no article selected to edit', false);

        $this->assertDatabaseHas('site_articles', [
            'id' => $article->id,
            'title' => 'Updated News',
            'short_story' => 'Updated short',
            'article_image' => 'updated.png',
        ]);

        $this->post('/habblet/ajax/preview_news_article', [
            'body' => "<script>alert(1)</script>\nLine two",
        ])
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('<br>', false)
            ->assertDontSee('<script>', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/articles/delete?id='.$article->id.'abc')
            ->assertOk()
            ->assertSee('There was no article selected to delete', false);
        $this->assertDatabaseHas('site_articles', ['id' => $article->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/articles/delete?id='.$article->id)
            ->assertOk()
            ->assertSee('Successfully deleted the article', false);

        $this->assertDatabaseMissing('site_articles', ['id' => $article->id]);
        $this->assertDatabaseHas('site_articles_categories', ['article_id' => $article->id]);

        $this->withSession([
            'housekeeping.authenticated' => false,
            'user.id' => 0,
        ])
            ->get('/allseeingeye/hk/configurations')
            ->assertRedirect('/allseeingeye/hk');
    }

    public function test_housekeeping_catalogue_frontpage_pages_and_items_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'CatalogueAdmin',
            'email' => 'catalogue-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        \DB::table('settings')->insert([
            ['setting' => 'catalogue.frontpage.input.1', 'value' => 'attention_topstory.png'],
            ['setting' => 'catalogue.frontpage.input.2', 'value' => 'Old header'],
            ['setting' => 'catalogue.frontpage.input.3', 'value' => 'Old subtext'],
            ['setting' => 'catalogue.frontpage.input.4', 'value' => 'https://old.example.test'],
        ]);
        app(HavanaConfig::class)->reload();

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/edit_frontpage', [
                'image' => 'attention_topstory.png',
                'header' => 'New catalogue header',
                'subtext' => 'New catalogue subtext',
                'link' => 'https://new.example.test',
            ])
            ->assertOk()
            ->assertSee('The frontpage has been successfully saved', false)
            ->assertSee('New catalogue header', false);

        $this->assertDatabaseHas('settings', [
            'setting' => 'catalogue.frontpage.input.2',
            'value' => 'New catalogue header',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/pages/edit', [
                'old_id' => 10,
                'parent_id' => -1,
                'order_id' => 3,
                'min_role' => 1,
                'is_navigatable' => 'on',
                'name' => 'Admin Page',
                'icon' => 2,
                'colour' => 4,
                'layout' => 'default_3x3',
                'images' => '["headline"]',
                'texts' => '["body"]',
                'seasonal_start' => '2026-06-30',
                'seasonal_length' => 3600,
            ])
            ->assertRedirect();

        $page = \DB::table('catalogue_pages')->where('name', 'Admin Page')->first();
        $this->assertNotNull($page);
        $this->assertDatabaseHas('catalogue_pages', [
            'id' => $page->id,
            'old_id' => 10,
            'parent_id' => -1,
            'order_id' => 3,
            'min_role' => 1,
            'is_navigatable' => true,
            'is_club_only' => false,
            'layout' => 'default_3x3',
            'images' => '["headline"]',
            'texts' => '["body"]',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/pages')
            ->assertOk()
            ->assertSee('Admin Page', false)
            ->assertSee('default_3x3', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/pages/edit?id='.$page->id, [
                'old_id' => 11,
                'parent_id' => -1,
                'order_id' => 4,
                'min_role' => 6,
                'is_navigatable' => 'on',
                'is_club_only' => 'on',
                'name' => 'Updated Admin Page',
                'icon' => 3,
                'colour' => 5,
                'layout' => 'spaces',
                'images' => '["https://example.test/updated.png"]',
                'texts' => '["Visit https://example.test/catalogue"]',
                'seasonal_start' => '',
                'seasonal_length' => 0,
            ])
            ->assertRedirect('/allseeingeye/hk/catalogue/pages/edit?id='.$page->id);

        $this->assertDatabaseHas('catalogue_pages', [
            'id' => $page->id,
            'name' => 'Updated Admin Page',
            'min_role' => 6,
            'is_club_only' => true,
            'layout' => 'spaces',
            'images' => '["https://example.test/updated.png"]',
            'texts' => '["Visit https://example.test/catalogue"]',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/pages/edit?id='.$page->id.'abc', [
                'old_id' => 12,
                'parent_id' => -1,
                'order_id' => 5,
                'min_role' => 7,
                'name' => 'Malformed Page Update',
                'icon' => 4,
                'colour' => 6,
                'layout' => 'malformed',
                'images' => '["bad"]',
                'texts' => '["bad"]',
                'seasonal_length' => 0,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('catalogue_pages', [
            'id' => $page->id,
            'name' => 'Updated Admin Page',
            'min_role' => 6,
            'layout' => 'spaces',
        ]);
        $this->assertDatabaseHas('catalogue_pages', [
            'name' => 'Malformed Page Update',
            'layout' => 'malformed',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/pages/edit?id='.$page->id, [
                'old_id' => 11,
                'parent_id' => -1,
                'order_id' => 4,
                'min_role' => 6,
                'name' => 'Bad Json Page',
                'icon' => 3,
                'colour' => 5,
                'layout' => 'spaces',
                'images' => 'not-json',
                'texts' => '[]',
                'seasonal_length' => 0,
            ])
            ->assertOk()
            ->assertSee('Images and texts must be valid JSON arrays', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/items/edit', [
                'sale_code' => 'rare_sofa',
                'page_id' => (string) $page->id,
                'order_id' => 2,
                'price_coins' => 15,
                'price_pixels' => 3,
                'seasonal_coins' => 1,
                'seasonal_pixels' => 2,
                'hidden' => 'on',
                'amount' => 4,
                'definition_id' => 900,
                'item_specialspriteid' => 'special',
                'is_package' => 'on',
                'active_at' => 'summer',
            ])
            ->assertRedirect();

        $item = \DB::table('catalogue_items')->where('sale_code', 'rare_sofa')->first();
        $this->assertNotNull($item);
        $this->assertDatabaseHas('catalogue_items', [
            'id' => $item->id,
            'sale_code' => 'rare_sofa',
            'page_id' => (string) $page->id,
            'price_coins' => 15,
            'price_pixels' => 3,
            'hidden' => true,
            'amount' => 4,
            'definition_id' => 900,
            'item_specialspriteid' => 'special',
            'is_package' => true,
            'active_at' => 'summer',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/items/edit?id='.$item->id.'abc', [
                'sale_code' => 'malformed_item_update',
                'page_id' => (string) $page->id,
                'order_id' => 3,
                'price_coins' => 99,
                'price_pixels' => 9,
                'amount' => 1,
                'definition_id' => 901,
                'active_at' => 'fall',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('catalogue_items', [
            'id' => $item->id,
            'sale_code' => 'rare_sofa',
            'price_coins' => 15,
        ]);
        $this->assertDatabaseHas('catalogue_items', [
            'sale_code' => 'malformed_item_update',
            'price_coins' => 99,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/items')
            ->assertOk()
            ->assertSee('rare_sofa', false)
            ->assertSee('15c', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/items/delete?id='.$item->id.'abc')
            ->assertRedirect('/allseeingeye/hk/catalogue/items');
        $this->assertDatabaseHas('catalogue_items', ['id' => $item->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/items/delete?id='.$item->id)
            ->assertRedirect('/allseeingeye/hk/catalogue/items');
        $this->assertDatabaseMissing('catalogue_items', ['id' => $item->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/pages/delete?id='.$page->id.'abc')
            ->assertRedirect('/allseeingeye/hk/catalogue/pages');
        $this->assertDatabaseHas('catalogue_pages', ['id' => $page->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/pages/delete?id='.$page->id)
            ->assertRedirect('/allseeingeye/hk/catalogue/pages');
        $this->assertDatabaseMissing('catalogue_pages', ['id' => $page->id]);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/catalogue/pages')
            ->assertRedirect('/allseeingeye/hk');
    }

    public function test_housekeeping_catalogue_packages_sale_badges_and_collectables_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'CatalogueAdmin2',
            'email' => 'catalogue-admin2@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/packages/edit', [
                'salecode' => 'bundle_sofa',
                'definition_id' => 500,
                'special_sprite_id' => 'bundle',
                'amount' => 3,
            ])
            ->assertRedirect();

        $package = \DB::table('catalogue_packages')->where('salecode', 'bundle_sofa')->first();
        $this->assertNotNull($package);
        $this->assertDatabaseHas('catalogue_packages', [
            'id' => $package->id,
            'salecode' => 'bundle_sofa',
            'definition_id' => 500,
            'special_sprite_id' => 'bundle',
            'amount' => 3,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/packages')
            ->assertOk()
            ->assertSee('bundle_sofa', false)
            ->assertSee('bundle', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/packages/edit?id='.$package->id, [
                'salecode' => 'bundle_sofa_updated',
                'definition_id' => 501,
                'special_sprite_id' => 'bundle2',
                'amount' => 4,
            ])
            ->assertRedirect('/allseeingeye/hk/catalogue/packages/edit?id='.$package->id);

        $this->assertDatabaseHas('catalogue_packages', [
            'id' => $package->id,
            'salecode' => 'bundle_sofa_updated',
            'definition_id' => 501,
            'special_sprite_id' => 'bundle2',
            'amount' => 4,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/packages/edit?id='.$package->id.'abc', [
                'salecode' => 'malformed_package_update',
                'definition_id' => 599,
                'special_sprite_id' => 'badbundle',
                'amount' => 9,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('catalogue_packages', [
            'id' => $package->id,
            'salecode' => 'bundle_sofa_updated',
            'definition_id' => 501,
            'amount' => 4,
        ]);
        $this->assertDatabaseHas('catalogue_packages', [
            'salecode' => 'malformed_package_update',
            'definition_id' => 599,
            'amount' => 9,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/packages/edit?id='.$package->id, [
                'salecode' => '',
                'definition_id' => 501,
                'special_sprite_id' => 'bundle2',
                'amount' => 4,
            ])
            ->assertOk()
            ->assertSee('Sale code cannot be blank', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/sale_badges', [
                'sale_code' => 'bundle_sofa_updated',
                'badge_code' => 'ADM01',
            ])
            ->assertRedirect('/allseeingeye/hk/catalogue/sale_badges');

        $this->assertDatabaseHas('catalogue_sale_badges', [
            'sale_code' => 'bundle_sofa_updated',
            'badge_code' => 'ADM01',
        ]);

        \DB::table('catalogue_sale_badges')->insert([
            'sale_code' => ' spaced_sale',
            'badge_code' => 'SPC01',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/sale_badges')
            ->assertOk()
            ->assertSee('bundle_sofa_updated', false)
            ->assertSee('ADM01', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/collectables/edit', [
                'store_page' => 77,
                'admin_page' => 88,
                'expiry' => 123456,
                'lifetime' => 2678400,
                'current_position' => 2,
                'class_names' => 'rare_dragon,rare_sofa',
            ])
            ->assertRedirect('/allseeingeye/hk/catalogue/collectables/edit?id=77');

        $this->assertDatabaseHas('catalogue_collectables', [
            'store_page' => 77,
            'admin_page' => 88,
            'expiry' => 123456,
            'lifetime' => 2678400,
            'current_position' => 2,
            'class_names' => 'rare_dragon,rare_sofa',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/collectables')
            ->assertOk()
            ->assertSee('rare_dragon,rare_sofa', false)
            ->assertSee('77', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/collectables/edit?id=77', [
                'store_page' => 78,
                'admin_page' => 89,
                'expiry' => 654321,
                'lifetime' => 123,
                'current_position' => 1,
                'class_names' => 'rare_updated',
            ])
            ->assertRedirect('/allseeingeye/hk/catalogue/collectables/edit?id=77');

        $this->assertDatabaseMissing('catalogue_collectables', ['store_page' => 77]);
        $this->assertDatabaseHas('catalogue_collectables', [
            'store_page' => 78,
            'admin_page' => 89,
            'expiry' => 654321,
            'lifetime' => 123,
            'current_position' => 1,
            'class_names' => 'rare_updated',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/catalogue/collectables/edit?id=78abc', [
                'store_page' => 79,
                'admin_page' => 90,
                'expiry' => 111111,
                'lifetime' => 456,
                'current_position' => 3,
                'class_names' => 'rare_malformed',
            ])
            ->assertRedirect('/allseeingeye/hk/catalogue/collectables/edit?id=79');
        $this->assertDatabaseHas('catalogue_collectables', [
            'store_page' => 78,
            'admin_page' => 89,
            'class_names' => 'rare_updated',
        ]);
        $this->assertDatabaseHas('catalogue_collectables', [
            'store_page' => 79,
            'admin_page' => 90,
            'class_names' => 'rare_malformed',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/sale_badges/delete?sale_code=bundle_sofa_updated&badge_code=ADM01')
            ->assertRedirect('/allseeingeye/hk/catalogue/sale_badges');
        $this->assertDatabaseMissing('catalogue_sale_badges', [
            'sale_code' => 'bundle_sofa_updated',
            'badge_code' => 'ADM01',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/sale_badges/delete?sale_code=%20spaced_sale&badge_code=SPC01')
            ->assertRedirect('/allseeingeye/hk/catalogue/sale_badges');
        $this->assertDatabaseMissing('catalogue_sale_badges', [
            'sale_code' => ' spaced_sale',
            'badge_code' => 'SPC01',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/collectables/delete?id=78abc')
            ->assertRedirect('/allseeingeye/hk/catalogue/collectables');
        $this->assertDatabaseHas('catalogue_collectables', ['store_page' => 78]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/collectables/delete?id=78')
            ->assertRedirect('/allseeingeye/hk/catalogue/collectables');
        $this->assertDatabaseMissing('catalogue_collectables', ['store_page' => 78]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/packages/delete?id='.$package->id.'abc')
            ->assertRedirect('/allseeingeye/hk/catalogue/packages');
        $this->assertDatabaseHas('catalogue_packages', ['id' => $package->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/catalogue/packages/delete?id='.$package->id)
            ->assertRedirect('/allseeingeye/hk/catalogue/packages');
        $this->assertDatabaseMissing('catalogue_packages', ['id' => $package->id]);
    }

    public function test_housekeeping_item_definitions_and_vouchers_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'GameDataAdmin',
            'email' => 'game-data-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/item_definitions/edit', [
                'sprite' => 'rare_sofa',
                'name' => 'Rare Sofa',
                'description' => 'A sofa',
                'sprite_id' => 99,
                'length' => 2,
                'width' => 1,
                'top_height' => '1.25',
                'max_status' => '2',
                'behaviour' => 'solid,can_sit_on_top',
                'interactor' => 'chair',
                'is_tradable' => 'on',
                'is_recyclable' => 'on',
                'drink_ids' => '1, 2',
                'rental_time' => -1,
                'allowed_rotations' => '0, 2, 4',
                'heights' => '1.0, 1.25',
            ])
            ->assertRedirect();

        $definition = \DB::table('items_definitions')->where('sprite', 'rare_sofa')->first();
        $this->assertNotNull($definition);
        $this->assertDatabaseHas('items_definitions', [
            'id' => $definition->id,
            'sprite' => 'rare_sofa',
            'name' => 'Rare Sofa',
            'sprite_id' => 99,
            'length' => 2,
            'width' => 1,
            'behaviour' => 'solid,can_sit_on_top',
            'interactor' => 'chair',
            'drink_ids' => '1,2',
            'allowed_rotations' => '0,2,4',
            'heights' => '1.0,1.25',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/item_definitions/edit', [
                'sprite' => 'minimal_item',
                'top_height' => '0',
                'interactor' => 'default',
            ])
            ->assertRedirect();

        $minimalDefinition = \DB::table('items_definitions')->where('sprite', 'minimal_item')->first();
        $this->assertNotNull($minimalDefinition);
        $this->assertDatabaseHas('items_definitions', [
            'id' => $minimalDefinition->id,
            'sprite' => 'minimal_item',
            'name' => '',
            'description' => '',
            'sprite_id' => 0,
            'length' => 0,
            'width' => 0,
            'top_height' => 0,
            'max_status' => '',
            'behaviour' => '',
            'interactor' => 'default',
            'is_tradable' => false,
            'is_recyclable' => false,
            'drink_ids' => '',
            'rental_time' => 0,
            'allowed_rotations' => '',
            'heights' => '',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/item_definitions')
            ->assertOk()
            ->assertSee('rare_sofa', false)
            ->assertSee('Rare Sofa', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/item_definitions/edit?id='.$definition->id, [
                'sprite' => 'rare_sofa_updated',
                'name' => 'Rare Sofa Updated',
                'description' => 'Updated',
                'sprite_id' => 100,
                'length' => 3,
                'width' => 2,
                'top_height' => '1.5',
                'max_status' => '3',
                'behaviour' => 'solid',
                'interactor' => 'default',
                'rental_time' => 60,
                'allowed_rotations' => '0,4',
                'heights' => '1.5',
            ])
            ->assertRedirect('/allseeingeye/hk/item_definitions/edit?id='.$definition->id);

        $this->assertDatabaseHas('items_definitions', [
            'id' => $definition->id,
            'sprite' => 'rare_sofa_updated',
            'name' => 'Rare Sofa Updated',
            'sprite_id' => 100,
            'length' => 3,
            'width' => 2,
            'is_tradable' => false,
            'is_recyclable' => false,
            'rental_time' => 60,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/item_definitions/edit?id='.$definition->id.'abc', [
                'sprite' => 'malformed_definition_update',
                'name' => 'Malformed Definition',
                'description' => 'Should create a separate row',
                'sprite_id' => 101,
                'length' => 4,
                'width' => 3,
                'top_height' => '2',
                'max_status' => '4',
                'behaviour' => 'solid',
                'interactor' => 'default',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('items_definitions', [
            'id' => $definition->id,
            'sprite' => 'rare_sofa_updated',
            'name' => 'Rare Sofa Updated',
            'sprite_id' => 100,
        ]);
        $this->assertDatabaseHas('items_definitions', [
            'sprite' => 'malformed_definition_update',
            'name' => 'Malformed Definition',
            'sprite_id' => 101,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/item_definitions/edit?id='.$definition->id, [
                'sprite' => '',
                'top_height' => '1.5',
                'behaviour' => 'solid',
                'interactor' => 'default',
            ])
            ->assertOk()
            ->assertSee('Sprite cannot be blank', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/item_definitions/edit?id='.$definition->id, [
                'sprite' => 'rare_sofa_updated',
                'top_height' => 'bad',
                'behaviour' => 'solid',
                'interactor' => 'default',
            ])
            ->assertOk()
            ->assertSee('Top height must be a valid number', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/item_definitions/edit?id='.$definition->id, [
                'sprite' => 'rare_sofa_updated',
                'top_height' => '1',
                'behaviour' => 'unknown',
                'interactor' => 'default',
            ])
            ->assertOk()
            ->assertSee('Unknown item behaviour: unknown', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/vouchers/edit', [
                'voucher_code' => 'FREE-SOFA',
                'credits' => 25,
                'expiry_date' => '2026-12-31 00:00:00',
                'is_single_use' => 'on',
                'allow_new_users' => 'on',
                'catalogue_sale_codes' => "rare_sofa_updated, rare_lamp\nrare_lamp\nrare_chair",
            ])
            ->assertRedirect('/allseeingeye/hk/vouchers/edit?code=FREE-SOFA');

        $this->assertDatabaseHas('vouchers', [
            'voucher_code' => 'FREE-SOFA',
            'credits' => 25,
            'expiry_date' => '2026-12-31 00:00:00',
            'is_single_use' => true,
            'allow_new_users' => true,
        ]);
        $this->assertDatabaseHas('vouchers_items', [
            'voucher_code' => 'FREE-SOFA',
            'catalogue_sale_code' => 'rare_sofa_updated',
        ]);
        $this->assertDatabaseHas('vouchers_items', [
            'voucher_code' => 'FREE-SOFA',
            'catalogue_sale_code' => 'rare_lamp',
        ]);
        $this->assertDatabaseHas('vouchers_items', [
            'voucher_code' => 'FREE-SOFA',
            'catalogue_sale_code' => 'rare_chair',
        ]);
        $this->assertSame(3, \DB::table('vouchers_items')->where('voucher_code', 'FREE-SOFA')->count());

        \DB::table('vouchers_history')->insert([
            'voucher_code' => 'FREE-SOFA',
            'user_id' => $admin->id,
            'used_at' => '2026-06-30 00:00:00',
            'credits_redeemed' => 25,
            'items_redeemed' => 'rare_sofa_updated',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/vouchers')
            ->assertOk()
            ->assertSee('FREE-SOFA', false)
            ->assertSee('rare_sofa_updated', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/vouchers/edit?code=FREE-SOFA')
            ->assertOk()
            ->assertSee('rare_lamp', false)
            ->assertSee('rare_sofa_updated', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/vouchers/edit?code=FREE-SOFA', [
                'voucher_code' => 'FREE-SOFA-2',
                'credits' => 30,
                'catalogue_sale_codes' => 'rare_sofa_updated',
            ])
            ->assertRedirect('/allseeingeye/hk/vouchers/edit?code=FREE-SOFA-2');

        $this->assertDatabaseMissing('vouchers', ['voucher_code' => 'FREE-SOFA']);
        $this->assertDatabaseMissing('vouchers_items', ['voucher_code' => 'FREE-SOFA']);
        $this->assertDatabaseHas('vouchers', [
            'voucher_code' => 'FREE-SOFA-2',
            'credits' => 30,
            'is_single_use' => false,
            'allow_new_users' => false,
        ]);
        $this->assertDatabaseHas('vouchers_items', [
            'voucher_code' => 'FREE-SOFA-2',
            'catalogue_sale_code' => 'rare_sofa_updated',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/vouchers/edit?code=%20FREE-SOFA-2')
            ->assertRedirect('/allseeingeye/hk/vouchers');

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/vouchers/edit?code=FREE-SOFA-2', [
                'voucher_code' => '',
                'credits' => 30,
            ])
            ->assertOk()
            ->assertSee('Voucher code cannot be blank', false);

        \DB::table('vouchers')->insert([
            'voucher_code' => ' SPACED-VOUCHER',
            'credits' => 5,
            'expiry_date' => null,
            'is_single_use' => true,
            'allow_new_users' => false,
        ]);
        \DB::table('vouchers_items')->insert([
            'voucher_code' => ' SPACED-VOUCHER',
            'catalogue_sale_code' => 'rare_sofa_updated',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/vouchers/delete?code=%20SPACED-VOUCHER')
            ->assertRedirect('/allseeingeye/hk/vouchers');
        $this->assertDatabaseMissing('vouchers', ['voucher_code' => ' SPACED-VOUCHER']);
        $this->assertDatabaseMissing('vouchers_items', ['voucher_code' => ' SPACED-VOUCHER']);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/vouchers/delete?code=FREE-SOFA-2')
            ->assertRedirect('/allseeingeye/hk/vouchers');
        $this->assertDatabaseMissing('vouchers', ['voucher_code' => 'FREE-SOFA-2']);
        $this->assertDatabaseMissing('vouchers_items', ['voucher_code' => 'FREE-SOFA-2']);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/item_definitions/delete?id='.$definition->id.'abc')
            ->assertRedirect('/allseeingeye/hk/item_definitions');
        $this->assertDatabaseHas('items_definitions', ['id' => $definition->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/item_definitions/delete?id='.$definition->id)
            ->assertRedirect('/allseeingeye/hk/item_definitions');
        $this->assertDatabaseMissing('items_definitions', ['id' => $definition->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/item_definitions/delete?id='.$minimalDefinition->id)
            ->assertRedirect('/allseeingeye/hk/item_definitions');
        $this->assertDatabaseMissing('items_definitions', ['id' => $minimalDefinition->id]);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/item_definitions')
            ->assertRedirect('/allseeingeye/hk');
    }

    public function test_housekeeping_wordfilter_and_recycler_reward_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'FilterAdmin',
            'email' => 'filter-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/wordfilter/edit', [
                'word' => 'blocked',
                'is_bannable' => 'on',
                'is_filterable' => 'on',
            ])
            ->assertRedirect();

        $word = \DB::table('wordfilter')->where('word', 'blocked')->first();
        $this->assertNotNull($word);
        $this->assertDatabaseHas('wordfilter', [
            'id' => $word->id,
            'word' => 'blocked',
            'is_bannable' => true,
            'is_filterable' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/wordfilter')
            ->assertOk()
            ->assertSee('blocked', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/wordfilter/edit?id='.$word->id, [
                'word' => 'filtered',
                'is_filterable' => 'on',
            ])
            ->assertRedirect('/allseeingeye/hk/wordfilter/edit?id='.$word->id);

        $this->assertDatabaseHas('wordfilter', [
            'id' => $word->id,
            'word' => 'filtered',
            'is_bannable' => false,
            'is_filterable' => true,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/wordfilter/edit?id='.$word->id.'abc', [
                'word' => 'malformed-filter',
                'is_bannable' => 'on',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('wordfilter', [
            'id' => $word->id,
            'word' => 'filtered',
            'is_bannable' => false,
            'is_filterable' => true,
        ]);
        $this->assertDatabaseHas('wordfilter', [
            'word' => 'malformed-filter',
            'is_bannable' => true,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/wordfilter/edit?id='.$word->id, [
                'word' => '',
            ])
            ->assertOk()
            ->assertSee('Word cannot be blank', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/recycler_rewards/edit', [
                'sprite' => 'eco_test',
                'order_id' => 7,
                'chance' => 30,
            ])
            ->assertRedirect('/allseeingeye/hk/recycler_rewards/edit?sprite=eco_test');

        $this->assertDatabaseHas('recycler_rewards', [
            'sprite' => 'eco_test',
            'order_id' => 7,
            'chance' => 30,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/recycler_rewards')
            ->assertOk()
            ->assertSee('eco_test', false)
            ->assertSee('30', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/recycler_rewards/edit?sprite=eco_test', [
                'sprite' => 'eco_test_updated',
                'order_id' => 8,
                'chance' => 45,
            ])
            ->assertRedirect('/allseeingeye/hk/recycler_rewards/edit?sprite=eco_test_updated');

        $this->assertDatabaseMissing('recycler_rewards', ['sprite' => 'eco_test']);
        $this->assertDatabaseHas('recycler_rewards', [
            'sprite' => 'eco_test_updated',
            'order_id' => 8,
            'chance' => 45,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/recycler_rewards/edit?sprite=%20eco_test_updated')
            ->assertRedirect('/allseeingeye/hk/recycler_rewards');

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/recycler_rewards/edit?sprite=eco_test_updated', [
                'sprite' => '',
                'order_id' => 8,
                'chance' => 45,
            ])
            ->assertOk()
            ->assertSee('Sprite cannot be blank', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/recycler_rewards/delete?sprite=eco_test_updated')
            ->assertRedirect('/allseeingeye/hk/recycler_rewards');
        $this->assertDatabaseMissing('recycler_rewards', ['sprite' => 'eco_test_updated']);

        \DB::table('recycler_rewards')->insert([
            'sprite' => ' spaced_reward',
            'order_id' => 9,
            'chance' => 50,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/recycler_rewards/delete?sprite=%20spaced_reward')
            ->assertRedirect('/allseeingeye/hk/recycler_rewards');
        $this->assertDatabaseMissing('recycler_rewards', ['sprite' => ' spaced_reward']);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/wordfilter/delete?id='.$word->id.'abc')
            ->assertRedirect('/allseeingeye/hk/wordfilter');
        $this->assertDatabaseHas('wordfilter', ['id' => $word->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/wordfilter/delete?id='.$word->id)
            ->assertRedirect('/allseeingeye/hk/wordfilter');
        $this->assertDatabaseMissing('wordfilter', ['id' => $word->id]);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/wordfilter')
            ->assertRedirect('/allseeingeye/hk');
    }

    public function test_housekeeping_room_categories_and_models_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'RoomDataAdmin',
            'email' => 'room-data-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        \DB::table('rooms_categories')->insert([
            'id' => 2,
            'order_id' => 0,
            'parent_id' => 0,
            'isnode' => true,
            'name' => 'Parent Category',
            'public_spaces' => false,
            'allow_trading' => false,
            'minrole_access' => 1,
            'minrole_setflatcat' => 5,
            'club_only' => false,
            'is_top_priority' => false,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_categories/edit', [
                'order_id' => 10,
                'parent_id' => 2,
                'isnode' => 'on',
                'name' => 'Trade Rooms',
                'public_spaces' => 'on',
                'allow_trading' => 'on',
                'minrole_access' => 1,
                'minrole_setflatcat' => 5,
                'club_only' => 'on',
                'is_top_priority' => 'on',
            ])
            ->assertRedirect();

        $category = \DB::table('rooms_categories')->where('name', 'Trade Rooms')->first();
        $this->assertNotNull($category);
        $this->assertDatabaseHas('rooms_categories', [
            'id' => $category->id,
            'order_id' => 10,
            'parent_id' => 2,
            'isnode' => true,
            'name' => 'Trade Rooms',
            'public_spaces' => true,
            'allow_trading' => true,
            'minrole_access' => 1,
            'minrole_setflatcat' => 5,
            'club_only' => true,
            'is_top_priority' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_categories')
            ->assertOk()
            ->assertSee('Trade Rooms', false)
            ->assertSee('Parent Category', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_categories/edit?id='.$category->id, [
                'order_id' => 11,
                'parent_id' => 0,
                'name' => 'Updated Rooms',
                'minrole_access' => 2,
                'minrole_setflatcat' => 6,
            ])
            ->assertRedirect('/allseeingeye/hk/room_categories/edit?id='.$category->id);

        $this->assertDatabaseHas('rooms_categories', [
            'id' => $category->id,
            'order_id' => 11,
            'parent_id' => 0,
            'isnode' => false,
            'name' => 'Updated Rooms',
            'public_spaces' => false,
            'allow_trading' => false,
            'minrole_access' => 2,
            'minrole_setflatcat' => 6,
            'club_only' => false,
            'is_top_priority' => false,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_categories/edit?id='.$category->id.'abc', [
                'order_id' => 12,
                'parent_id' => 2,
                'name' => 'Malformed Rooms',
                'minrole_access' => 3,
                'minrole_setflatcat' => 7,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('rooms_categories', [
            'id' => $category->id,
            'name' => 'Updated Rooms',
            'order_id' => 11,
        ]);
        $this->assertDatabaseHas('rooms_categories', [
            'name' => 'Malformed Rooms',
            'order_id' => 12,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_categories/edit?id='.$category->id, [
                'name' => '',
            ])
            ->assertOk()
            ->assertSee('Category name cannot be blank', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_models/edit', [
                'model_id' => 'model_test',
                'model_name' => 'Model Test',
                'door_x' => 3,
                'door_y' => 4,
                'door_z' => '1.5',
                'door_dir' => 2,
                'heightmap' => 'xxx|x0x|xxx',
                'trigger_class' => 'flat_trigger',
            ])
            ->assertRedirect();

        $model = \DB::table('rooms_models')->where('model_id', 'model_test')->first();
        $this->assertNotNull($model);
        $this->assertDatabaseHas('rooms_models', [
            'id' => $model->id,
            'model_id' => 'model_test',
            'model_name' => 'Model Test',
            'door_x' => 3,
            'door_y' => 4,
            'door_z' => 1.5,
            'door_dir' => 2,
            'heightmap' => 'xxx|x0x|xxx',
            'trigger_class' => 'flat_trigger',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_models')
            ->assertOk()
            ->assertSee('model_test', false)
            ->assertSee('Model Test', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_models/edit?id='.$model->id, [
                'model_id' => 'model_updated',
                'model_name' => 'Model Updated',
                'door_x' => 5,
                'door_y' => 6,
                'door_z' => '2.5',
                'door_dir' => 4,
                'heightmap' => 'xxx|x1x|xxx',
                'trigger_class' => 'none',
            ])
            ->assertRedirect('/allseeingeye/hk/room_models/edit?id='.$model->id);

        $this->assertDatabaseHas('rooms_models', [
            'id' => $model->id,
            'model_id' => 'model_updated',
            'model_name' => 'Model Updated',
            'door_x' => 5,
            'door_y' => 6,
            'door_z' => 2.5,
            'door_dir' => 4,
            'heightmap' => 'xxx|x1x|xxx',
            'trigger_class' => 'none',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_models/edit?id='.$model->id.'abc', [
                'model_id' => 'model_malformed',
                'model_name' => 'Model Malformed',
                'door_x' => 7,
                'door_y' => 8,
                'door_z' => '3.5',
                'door_dir' => 6,
                'heightmap' => 'xxx|x2x|xxx',
                'trigger_class' => 'none',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('rooms_models', [
            'id' => $model->id,
            'model_id' => 'model_updated',
            'model_name' => 'Model Updated',
        ]);
        $this->assertDatabaseHas('rooms_models', [
            'model_id' => 'model_malformed',
            'model_name' => 'Model Malformed',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_models/edit?id='.$model->id, [
                'model_id' => '',
                'door_z' => '2.5',
                'trigger_class' => 'flat_trigger',
            ])
            ->assertOk()
            ->assertSee('Model ID cannot be blank', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_models/edit?id='.$model->id, [
                'model_id' => 'model_updated',
                'door_z' => 'bad',
                'trigger_class' => 'flat_trigger',
            ])
            ->assertOk()
            ->assertSee('Door Z must be a valid number', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_models/edit?id='.$model->id, [
                'model_id' => 'model_updated',
                'door_z' => '2.5',
                'trigger_class' => 'missing_trigger',
            ])
            ->assertOk()
            ->assertSee('Trigger class must match a known room model trigger', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_models/delete?id='.$model->id.'abc')
            ->assertRedirect('/allseeingeye/hk/room_models');
        $this->assertDatabaseHas('rooms_models', ['id' => $model->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_models/delete?id='.$model->id)
            ->assertRedirect('/allseeingeye/hk/room_models');
        $this->assertDatabaseMissing('rooms_models', ['id' => $model->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_categories/delete?id='.$category->id.'abc')
            ->assertRedirect('/allseeingeye/hk/room_categories');
        $this->assertDatabaseHas('rooms_categories', ['id' => $category->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_categories/delete?id='.$category->id)
            ->assertRedirect('/allseeingeye/hk/room_categories');
        $this->assertDatabaseMissing('rooms_categories', ['id' => $category->id]);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/room_categories')
            ->assertRedirect('/allseeingeye/hk');
    }

    public function test_housekeeping_room_ads_and_entry_badges_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'RoomAssetsAdmin',
            'email' => 'room-assets-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        \DB::table('rooms')->insert([
            'id' => 44,
            'owner_id' => (string) $admin->id,
            'name' => 'Badge Room',
            'description' => 'Room with badge',
            'model' => 'model_s',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_ads/create', [
                'roomid' => '44abc',
                'url' => 'https://example.test/malformed',
                'image' => 'https://example.test/malformed.gif',
                'enabled' => 'on',
            ])
            ->assertOk()
            ->assertSee('Error occurred, make sure the room ID is a valid number', false);
        $this->assertDatabaseMissing('rooms_ads', [
            'url' => 'https://example.test/malformed',
            'image' => 'https://example.test/malformed.gif',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_ads/create', [
                'roomid' => 44,
                'url' => 'https://example.test/ad',
                'image' => 'https://example.test/ad.gif',
                'enabled' => 'on',
                'loading-ad' => 'on',
            ])
            ->assertOk()
            ->assertSee('Room ad has been created successfully', false);

        $ad = \DB::table('rooms_ads')->where('room_id', 44)->first();
        $this->assertNotNull($ad);
        $this->assertDatabaseHas('rooms_ads', [
            'id' => $ad->id,
            'room_id' => 44,
            'url' => 'https://example.test/ad',
            'image' => 'https://example.test/ad.gif',
            'enabled' => true,
            'is_loading_ad' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_ads')
            ->assertOk()
            ->assertSee('https://example.test/ad.gif', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_ads', [
                'roomad-id-'.$ad->id => $ad->id,
                'roomad-'.$ad->id.'-roomid' => 45,
                'roomad-'.$ad->id.'-url' => '',
                'roomad-'.$ad->id.'-image' => 'https://example.test/updated.gif',
            ])
            ->assertOk()
            ->assertSee('All room ads have been saved successfully!', false);

        $this->assertDatabaseHas('rooms_ads', [
            'id' => $ad->id,
            'room_id' => 45,
            'url' => null,
            'image' => 'https://example.test/updated.gif',
            'enabled' => false,
            'is_loading_ad' => false,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_ads', [
                'roomad-id-'.$ad->id => $ad->id.'abc',
                'roomad-'.$ad->id.'-roomid' => 46,
                'roomad-'.$ad->id.'-url' => 'https://example.test/malformed-update',
                'roomad-'.$ad->id.'-image' => 'https://example.test/malformed-update.gif',
            ])
            ->assertOk()
            ->assertSee('All room ads have been saved successfully!', false);
        $this->assertDatabaseHas('rooms_ads', [
            'id' => $ad->id,
            'room_id' => 45,
            'url' => null,
            'image' => 'https://example.test/updated.gif',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_ads', [
                'roomad-id-'.$ad->id => $ad->id,
                'roomad-'.$ad->id.'-roomid' => '46abc',
                'roomad-'.$ad->id.'-url' => 'https://example.test/malformed-update',
                'roomad-'.$ad->id.'-image' => 'https://example.test/malformed-update.gif',
            ])
            ->assertOk()
            ->assertSee('All room ads have been saved successfully!', false);
        $this->assertDatabaseHas('rooms_ads', [
            'id' => $ad->id,
            'room_id' => 45,
            'url' => null,
            'image' => 'https://example.test/updated.gif',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_badges/create', [
                'roomid' => '44abc',
                'badgecode' => 'BAD',
            ])
            ->assertOk()
            ->assertSee('Error occurred, make sure the room ID is a valid number', false);
        $this->assertDatabaseMissing('rooms_entry_badges', [
            'room_id' => 44,
            'badge' => 'BAD',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_badges/create', [
                'roomid' => 44,
                'badgecode' => 'ADM',
            ])
            ->assertRedirect('/allseeingeye/hk/room_badges');

        $this->assertDatabaseHas('rooms_entry_badges', [
            'room_id' => 44,
            'badge' => 'ADM',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_badges', [
                'roombadge-id-44_ADM' => '44_ADM',
                'roomad-44_ADM-roomid' => '44abc',
                'roomad-44_ADM-badge' => 'BROKEN',
            ])
            ->assertOk()
            ->assertSee('Error occurred, make sure the room ID is a valid number', false);
        $this->assertDatabaseHas('rooms_entry_badges', [
            'room_id' => 44,
            'badge' => 'ADM',
        ]);
        $this->assertDatabaseMissing('rooms_entry_badges', [
            'room_id' => 44,
            'badge' => 'BROKEN',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_badges')
            ->assertOk()
            ->assertSee('ADM', false)
            ->assertSee('Badge Room', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/room_badges', [
                'roombadge-id-44_ADM' => '44_ADM',
                'roomad-44_ADM-roomid' => 44,
                'roomad-44_ADM-badge' => 'MOD',
                'roombadge-id-0_EMPTY' => '0_EMPTY',
                'roomad-0_EMPTY-roomid' => 0,
                'roomad-0_EMPTY-badge' => '',
            ])
            ->assertOk()
            ->assertSee('All badge rooms have been saved successfully!', false);

        $this->assertDatabaseMissing('rooms_entry_badges', [
            'room_id' => 44,
            'badge' => 'ADM',
        ]);
        $this->assertDatabaseHas('rooms_entry_badges', [
            'room_id' => 44,
            'badge' => 'MOD',
        ]);
        $this->assertDatabaseHas('rooms_entry_badges', [
            'room_id' => 0,
            'badge' => '',
        ]);

        \DB::table('rooms_entry_badges')->insert([
            'room_id' => 44,
            'badge' => ' LEAD',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_ads/delete?id='.$ad->id.'abc')
            ->assertOk();
        $this->assertDatabaseHas('rooms_ads', ['id' => $ad->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_badges/delete?id=44_MOD')
            ->assertOk()
            ->assertSee('Successfully deleted the badge', false);
        $this->assertDatabaseMissing('rooms_entry_badges', [
            'room_id' => 44,
            'badge' => 'MOD',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_badges/delete?id=44_%20LEAD')
            ->assertOk()
            ->assertSee('Successfully deleted the badge', false);
        $this->assertDatabaseMissing('rooms_entry_badges', [
            'room_id' => 44,
            'badge' => ' LEAD',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/room_ads/delete?id='.$ad->id)
            ->assertOk()
            ->assertSee('Room ad has been deleted successfully', false);
        $this->assertDatabaseMissing('rooms_ads', ['id' => $ad->id]);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/room_ads')
            ->assertRedirect('/allseeingeye/hk');

        $moderator = $this->createLegacyUser([
            'username' => 'RoomBadgeMod',
            'email' => 'room-badge-mod@example.test',
        ]);
        $moderator->forceFill(['rank' => 6])->save();
        $this->insertStatistics($moderator->id);

        $this->withSession([
            'housekeeping.authenticated' => true,
            'user.id' => $moderator->id,
        ])
            ->get('/allseeingeye/hk/room_badges')
            ->assertOk();
    }

    public function test_housekeeping_rooms_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'RoomsAdmin',
            'email' => 'rooms-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $owner = $this->createLegacyUser([
            'username' => 'RoomOwner',
            'email' => 'room-owner@example.test',
        ]);
        $this->insertStatistics($owner->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        \DB::table('rooms_categories')->insert([
            'id' => 7,
            'order_id' => 0,
            'parent_id' => 0,
            'isnode' => false,
            'name' => 'Guest Rooms',
            'public_spaces' => false,
            'allow_trading' => true,
            'minrole_access' => 1,
            'minrole_setflatcat' => 5,
            'club_only' => false,
            'is_top_priority' => false,
        ]);

        \DB::table('rooms_models')->insert([
            'id' => 7,
            'model_id' => 'model_rooms',
            'model_name' => 'Rooms Model',
            'door_x' => 1,
            'door_y' => 2,
            'door_z' => 0,
            'door_dir' => 2,
            'heightmap' => 'xxx|x0x|xxx',
            'trigger_class' => 'flat_trigger',
        ]);

        \DB::table('rooms')->insert([
            'id' => 77,
            'owner_id' => (string) $owner->id,
            'category' => 7,
            'name' => 'Editable Room',
            'description' => 'Original description',
            'model' => 'model_rooms',
            'ccts' => 'old_cct',
            'wallpaper' => 1,
            'floor' => 2,
            'landscape' => '0.0',
            'showname' => true,
            'superusers' => false,
            'accesstype' => 0,
            'password' => '',
            'visitors_now' => 3,
            'visitors_max' => 25,
            'rating' => 4,
            'icon_data' => '0|0|',
            'group_id' => 0,
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('rooms_rights')->insert([
            'room_id' => 77,
            'user_id' => $owner->id,
        ]);
        \DB::table('rooms_bans')->insert([
            'room_id' => 77,
            'user_id' => $owner->id,
            'expire_at' => 123456,
        ]);
        \DB::table('rooms_events')->insert([
            'room_id' => 77,
            'user_id' => $owner->id,
            'category_id' => 1,
            'name' => 'Room Event',
            'description' => 'Event description',
            'expire_time' => 987654,
            'tags' => 'party',
        ]);
        \DB::table('rooms_entry_badges')->insert([
            'room_id' => 77,
            'badge' => 'ADM',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms?query=editable')
            ->assertOk()
            ->assertSee('Editable Room', false)
            ->assertSee('RoomOwner', false)
            ->assertSee('model_rooms', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/edit?id=77')
            ->assertOk()
            ->assertSee('Editable Room', false)
            ->assertSee('Guest Rooms', false)
            ->assertSee('model_rooms', false)
            ->assertSee('Room Event', false)
            ->assertSee('ADM', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/edit?id=77abc')
            ->assertRedirect('/allseeingeye/hk/rooms');

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/rooms/edit?id=77abc', [
                'name' => 'Malformed Room Edit',
                'description' => 'Should not save',
                'owner_id' => $owner->id,
                'category' => 7,
                'model' => 'model_rooms',
                'accesstype' => 0,
                'visitors_max' => 30,
            ])
            ->assertRedirect('/allseeingeye/hk/rooms');
        $this->assertDatabaseHas('rooms', [
            'id' => 77,
            'name' => 'Editable Room',
            'description' => 'Original description',
            'visitors_max' => 25,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/rooms/edit?id=77', [
                'name' => '',
                'model' => 'model_rooms',
                'owner_id' => $owner->id,
                'category' => 7,
                'accesstype' => 0,
                'visitors_max' => 25,
            ])
            ->assertOk()
            ->assertSee('Room name cannot be blank', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/rooms/edit?id=77', [
                'name' => 'Updated Room',
                'description' => 'Updated description',
                'owner_id' => $owner->id,
                'category' => 7,
                'model' => 'model_rooms',
                'accesstype' => 2,
                'password' => 'secret',
                'visitors_max' => 40,
                'rating' => 8,
                'group_id' => 123,
                'wallpaper' => 5,
                'floor' => 6,
                'landscape' => '1.1',
                'ccts' => 'new_cct',
                'icon_data' => '1|2|3',
                'showname' => 'on',
                'superusers' => 'on',
                'is_hidden' => 'on',
            ])
            ->assertRedirect('/allseeingeye/hk/rooms/edit?id=77');

        $this->assertDatabaseHas('rooms', [
            'id' => 77,
            'owner_id' => (string) $owner->id,
            'category' => 7,
            'name' => 'Updated Room',
            'description' => 'Updated description',
            'model' => 'model_rooms',
            'ccts' => 'new_cct',
            'wallpaper' => 5,
            'floor' => 6,
            'landscape' => '1.1',
            'showname' => true,
            'superusers' => true,
            'accesstype' => 2,
            'password' => 'secret',
            'visitors_max' => 40,
            'rating' => 8,
            'icon_data' => '1|2|3',
            'group_id' => 123,
            'is_hidden' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/hide?id=77abc&hidden=false')
            ->assertRedirect('/allseeingeye/hk/rooms');
        $this->assertDatabaseHas('rooms', [
            'id' => 77,
            'is_hidden' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/hide?id=77&hidden=false')
            ->assertRedirect('/allseeingeye/hk/rooms');
        $this->assertDatabaseHas('rooms', [
            'id' => 77,
            'is_hidden' => false,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/staff_pick?id=77abc&enabled=true')
            ->assertRedirect('/allseeingeye/hk/rooms');
        $this->assertDatabaseMissing('cms_recommended', [
            'recommended_id' => 77,
            'type' => 'ROOM',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/staff_pick?id=77&enabled=true')
            ->assertRedirect('/allseeingeye/hk/rooms');
        $this->assertDatabaseHas('cms_recommended', [
            'recommended_id' => 77,
            'type' => 'ROOM',
            'is_staff_pick' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/staff_pick?id=77&enabled=false')
            ->assertRedirect('/allseeingeye/hk/rooms');
        $this->assertDatabaseMissing('cms_recommended', [
            'recommended_id' => 77,
            'type' => 'ROOM',
        ]);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/rooms')
            ->assertRedirect('/allseeingeye/hk');

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/delete?id=77abc')
            ->assertRedirect('/allseeingeye/hk/rooms');
        $this->assertDatabaseHas('rooms', ['id' => 77]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/rooms/delete?id=77')
            ->assertRedirect('/allseeingeye/hk/rooms');
        $this->assertDatabaseMissing('rooms', ['id' => 77]);
        $this->assertDatabaseMissing('cms_recommended', [
            'recommended_id' => 77,
            'type' => 'ROOM',
        ]);
    }

    public function test_housekeeping_groups_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'GroupsAdmin',
            'email' => 'groups-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $owner = $this->createLegacyUser([
            'username' => 'GroupOwner',
            'email' => 'group-owner@example.test',
        ]);
        $member = $this->createLegacyUser([
            'username' => 'GroupMember',
            'email' => 'group-member@example.test',
        ]);
        $this->insertStatistics($owner->id);
        $this->insertStatistics($member->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        \DB::table('rooms')->insert([
            'id' => 88,
            'owner_id' => (string) $owner->id,
            'name' => 'Group Home Room',
            'description' => 'Room for group',
            'model' => 'model_s',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = \DB::table('groups_details')->insertGetId([
            'name' => 'Managed Group',
            'description' => 'Original group description',
            'owner_id' => $owner->id,
            'room_id' => 88,
            'badge' => 'b0503',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 5,
            'topics' => 1,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => 'managed-group',
            'created_at' => now(),
        ]);

        \DB::table('groups_details')->insert([
            'name' => 'Alias Owner',
            'description' => 'Conflict group',
            'owner_id' => $owner->id,
            'room_id' => 0,
            'badge' => 'b0603',
            'alias' => 'taken-alias',
            'created_at' => now(),
        ]);

        \DB::table('groups_memberships')->insert([
            [
                'user_id' => $owner->id,
                'group_id' => $groupId,
                'member_rank' => '3',
                'is_pending' => false,
                'created_at' => now(),
            ],
            [
                'user_id' => $member->id,
                'group_id' => $groupId,
                'member_rank' => '1',
                'is_pending' => true,
                'created_at' => now(),
            ],
        ]);

        $threadId = \DB::table('cms_forum_threads')->insertGetId([
            'topic_title' => 'Original Thread',
            'poster_id' => $owner->id,
            'is_open' => true,
            'is_stickied' => false,
            'views' => 2,
            'group_id' => $groupId,
            'created_at' => now()->subMinute(),
            'modified_at' => now()->subMinute(),
        ]);

        $replyId = \DB::table('cms_forum_replies')->insertGetId([
            'thread_id' => $threadId,
            'message' => 'Visible reply',
            'poster_id' => $member->id,
            'is_edited' => true,
            'is_deleted' => false,
            'created_at' => now(),
            'modified_at' => now(),
        ]);
        \DB::table('cms_forums_read_replies')->insert([
            'user_id' => $owner->id,
            'reply_id' => $replyId,
        ]);
        \DB::table('cms_guestbook_entries')->insert([
            'user_id' => $member->id,
            'group_id' => $groupId,
            'message' => 'Group guestbook entry',
            'created_at' => now(),
        ]);
        \DB::table('groups_edit_sessions')->insert([
            'user_id' => $owner->id,
            'group_id' => $groupId,
            'expire' => time() + 3600,
        ]);
        $owner->forceFill(['favourite_group' => $groupId])->save();

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups?query=managed')
            ->assertOk()
            ->assertSee('Managed Group', false)
            ->assertSee('GroupOwner', false)
            ->assertSee('managed-group', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/edit?id='.$groupId)
            ->assertOk()
            ->assertSee('Managed Group', false)
            ->assertSee('GroupMember', false)
            ->assertSee('Original Thread', false)
            ->assertSee('Visible reply', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/edit?id='.$groupId.'abc')
            ->assertRedirect('/allseeingeye/hk/groups');

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/edit?id='.$groupId.'abc', [
                'name' => 'Malformed Group Edit',
                'description' => 'Should not save',
                'owner_id' => $owner->id,
                'room_id' => 88,
                'forum_type' => 0,
                'forum_premission' => 0,
            ])
            ->assertRedirect('/allseeingeye/hk/groups');
        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'name' => 'Managed Group',
            'description' => 'Original group description',
            'alias' => 'managed-group',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/edit?id='.$groupId, [
                'name' => '',
                'owner_id' => $owner->id,
                'room_id' => 88,
                'forum_type' => 0,
                'forum_premission' => 0,
            ])
            ->assertOk()
            ->assertSee('Group name cannot be blank', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/edit?id='.$groupId, [
                'name' => 'Managed Group',
                'owner_id' => $owner->id,
                'room_id' => 88,
                'alias' => 'taken-alias',
                'forum_type' => 0,
                'forum_premission' => 0,
            ])
            ->assertOk()
            ->assertSee('Group alias is already in use', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/edit?id='.$groupId, [
                'name' => 'Updated Group',
                'description' => 'Updated group description',
                'owner_id' => $owner->id,
                'room_id' => 88,
                'badge' => 'b0703',
                'recommended' => 'on',
                'background' => 'bg_colour_01',
                'views' => 10,
                'topics' => 3,
                'group_type' => 1,
                'forum_type' => 1,
                'forum_premission' => 2,
                'alias' => 'updated-group',
            ])
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);

        $this->assertDatabaseHas('groups_details', [
            'id' => $groupId,
            'name' => 'Updated Group',
            'description' => 'Updated group description',
            'owner_id' => $owner->id,
            'room_id' => 88,
            'badge' => 'b0703',
            'recommended' => 1,
            'background' => 'bg_colour_01',
            'views' => 10,
            'topics' => 3,
            'group_type' => 1,
            'forum_type' => 1,
            'forum_premission' => 2,
            'alias' => 'updated-group',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/staff_pick?id='.$groupId.'abc&enabled=true')
            ->assertRedirect('/allseeingeye/hk/groups');
        $this->assertDatabaseMissing('cms_recommended', [
            'recommended_id' => $groupId,
            'type' => 'GROUP',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/member', [
                'group_id' => $groupId,
                'user_id' => $member->id,
                'member_rank' => 2,
            ])
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('groups_memberships', [
            'group_id' => $groupId,
            'user_id' => $member->id,
            'member_rank' => 2,
            'is_pending' => false,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/member', [
                'group_id' => $groupId.'abc',
                'user_id' => $member->id,
                'member_rank' => 3,
            ])
            ->assertRedirect('/allseeingeye/hk/groups/edit?id=0');
        $this->assertDatabaseHas('groups_memberships', [
            'group_id' => $groupId,
            'user_id' => $member->id,
            'member_rank' => 2,
            'is_pending' => false,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/member', [
                'group_id' => $groupId,
                'user_id' => $member->id.'abc',
                'member_rank' => 3,
            ])
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('groups_memberships', [
            'group_id' => $groupId,
            'user_id' => $member->id,
            'member_rank' => 2,
            'is_pending' => false,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/thread', [
                'group_id' => $groupId,
                'thread_id' => $threadId,
                'topic_title' => 'Updated Thread',
                'is_stickied' => 'on',
            ])
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('cms_forum_threads', [
            'id' => $threadId,
            'topic_title' => 'Updated Thread',
            'is_open' => false,
            'is_stickied' => true,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/thread', [
                'group_id' => $groupId.'abc',
                'thread_id' => $threadId,
                'topic_title' => 'Malformed Thread',
            ])
            ->assertRedirect('/allseeingeye/hk/groups/edit?id=0');
        $this->assertDatabaseHas('cms_forum_threads', [
            'id' => $threadId,
            'topic_title' => 'Updated Thread',
            'is_stickied' => true,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/groups/thread', [
                'group_id' => $groupId,
                'thread_id' => $threadId.'abc',
                'topic_title' => 'Malformed Thread',
            ])
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('cms_forum_threads', [
            'id' => $threadId,
            'topic_title' => 'Updated Thread',
            'is_stickied' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/reply?group_id='.$groupId.'&reply_id='.$replyId.'&deleted=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $replyId,
            'is_deleted' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/reply?group_id='.$groupId.'abc&reply_id='.$replyId.'&deleted=false')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id=0');
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $replyId,
            'is_deleted' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/reply?group_id='.$groupId.'&reply_id='.$replyId.'abc&deleted=false')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('cms_forum_replies', [
            'id' => $replyId,
            'is_deleted' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/staff_pick?id='.$groupId.'&enabled=true')
            ->assertRedirect('/allseeingeye/hk/groups');
        $this->assertDatabaseHas('cms_recommended', [
            'recommended_id' => $groupId,
            'type' => 'GROUP',
            'is_staff_pick' => true,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/staff_pick?id='.$groupId.'&enabled=false')
            ->assertRedirect('/allseeingeye/hk/groups');
        $this->assertDatabaseMissing('cms_recommended', [
            'recommended_id' => $groupId,
            'type' => 'GROUP',
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/member?group_id='.$groupId.'&user_id='.$member->id.'&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseMissing('groups_memberships', [
            'group_id' => $groupId,
            'user_id' => $member->id,
        ]);

        \DB::table('groups_memberships')->insert([
            'user_id' => $member->id,
            'group_id' => $groupId,
            'member_rank' => '1',
            'is_pending' => false,
            'created_at' => now(),
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/member?group_id='.$groupId.'abc&user_id='.$member->id.'&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id=0');
        $this->assertDatabaseHas('groups_memberships', [
            'group_id' => $groupId,
            'user_id' => $member->id,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/member?group_id='.$groupId.'&user_id='.$member->id.'abc&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('groups_memberships', [
            'group_id' => $groupId,
            'user_id' => $member->id,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/reply?group_id='.$groupId.'abc&reply_id='.$replyId.'&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id=0');
        $this->assertDatabaseHas('cms_forum_replies', ['id' => $replyId]);
        $this->assertDatabaseHas('cms_forums_read_replies', ['reply_id' => $replyId]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/reply?group_id='.$groupId.'&reply_id='.$replyId.'abc&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('cms_forum_replies', ['id' => $replyId]);
        $this->assertDatabaseHas('cms_forums_read_replies', ['reply_id' => $replyId]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/reply?group_id='.$groupId.'&reply_id='.$replyId.'&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseMissing('cms_forum_replies', ['id' => $replyId]);
        $this->assertDatabaseMissing('cms_forums_read_replies', ['reply_id' => $replyId]);

        $deleteThreadId = \DB::table('cms_forum_threads')->insertGetId([
            'topic_title' => 'Delete Thread',
            'poster_id' => $owner->id,
            'is_open' => true,
            'is_stickied' => false,
            'views' => 0,
            'group_id' => $groupId,
            'created_at' => now(),
            'modified_at' => now(),
        ]);
        \DB::table('cms_forum_replies')->insert([
            'thread_id' => $deleteThreadId,
            'message' => 'Thread reply',
            'poster_id' => $owner->id,
            'created_at' => now(),
            'modified_at' => now(),
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/thread?group_id='.$groupId.'abc&thread_id='.$deleteThreadId.'&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id=0');
        $this->assertDatabaseHas('cms_forum_threads', ['id' => $deleteThreadId]);
        $this->assertDatabaseHas('cms_forum_replies', ['thread_id' => $deleteThreadId]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/thread?group_id='.$groupId.'&thread_id='.$deleteThreadId.'abc&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseHas('cms_forum_threads', ['id' => $deleteThreadId]);
        $this->assertDatabaseHas('cms_forum_replies', ['thread_id' => $deleteThreadId]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/thread?group_id='.$groupId.'&thread_id='.$deleteThreadId.'&delete=true')
            ->assertRedirect('/allseeingeye/hk/groups/edit?id='.$groupId);
        $this->assertDatabaseMissing('cms_forum_threads', ['id' => $deleteThreadId]);
        $this->assertDatabaseMissing('cms_forum_replies', ['thread_id' => $deleteThreadId]);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/groups')
            ->assertRedirect('/allseeingeye/hk');

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/delete?id='.$groupId.'abc')
            ->assertRedirect('/allseeingeye/hk/groups');
        $this->assertDatabaseHas('groups_details', ['id' => $groupId]);
        $this->assertDatabaseHas('groups_memberships', ['group_id' => $groupId]);
        $this->assertDatabaseHas('cms_guestbook_entries', ['group_id' => $groupId]);
        $this->assertDatabaseHas('groups_edit_sessions', ['group_id' => $groupId]);
        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'favourite_group' => $groupId,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/groups/delete?id='.$groupId)
            ->assertRedirect('/allseeingeye/hk/groups');
        $this->assertDatabaseMissing('groups_details', ['id' => $groupId]);
        $this->assertDatabaseMissing('groups_memberships', ['group_id' => $groupId]);
        $this->assertDatabaseMissing('cms_forum_threads', ['group_id' => $groupId]);
        $this->assertDatabaseMissing('cms_guestbook_entries', ['group_id' => $groupId]);
        $this->assertDatabaseMissing('groups_edit_sessions', ['group_id' => $groupId]);
        $this->assertDatabaseMissing('cms_recommended', [
            'recommended_id' => $groupId,
            'type' => 'GROUP',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'favourite_group' => 0,
        ]);
    }

    public function test_housekeeping_badges_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'BadgesAdmin',
            'email' => 'badges-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $target = $this->createLegacyUser([
            'username' => 'BadgeTarget',
            'email' => 'badge-target@example.test',
        ]);
        $this->insertStatistics($target->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        \DB::table('users_badges')->insert([
            'user_id' => $target->id,
            'badge' => 'OLD01',
            'equipped' => false,
            'slot_id' => 0,
        ]);
        \DB::table('rank_badges')->insert([
            'rank' => 8,
            'badge' => 'ADM',
        ]);
        \DB::table('housekeeping_audit_log')->insert([
            'action' => 'badge_update',
            'user_id' => $admin->id,
            'target_id' => $target->id,
            'message' => 'OLD01',
            'extra_notes' => 'Existing audit',
            'created_at' => now()->subMinute(),
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/badges?query=badge')
            ->assertOk()
            ->assertSee('BadgeTarget', false)
            ->assertSee('OLD01', false)
            ->assertSee('ADM', false)
            ->assertSee('Existing audit', false);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/badges/grant')
            ->assertRedirect('/allseeingeye/hk');
        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/badges/update')
            ->assertRedirect('/allseeingeye/hk');

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/badges/grant')
            ->assertRedirect('/allseeingeye/hk/badges?query=0');
        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/badges/update')
            ->assertRedirect('/allseeingeye/hk/badges?query=0');
        $this->assertDatabaseMissing('users_badges', [
            'user_id' => $target->id,
            'badge' => '',
        ]);
        $this->assertDatabaseMissing('housekeeping_audit_log', [
            'action' => 'badge_grant',
            'target_id' => $target->id,
            'message' => '',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/badges/grant', [
                'user_id' => $target->id,
                'badge' => 'NEW_01',
            ])
            ->assertRedirect('/allseeingeye/hk/badges?query='.$target->id);
        $this->assertDatabaseHas('users_badges', [
            'user_id' => $target->id,
            'badge' => 'NEW_01',
            'equipped' => false,
            'slot_id' => 0,
        ]);
        $this->assertDatabaseHas('housekeeping_audit_log', [
            'action' => 'badge_grant',
            'user_id' => $admin->id,
            'target_id' => $target->id,
            'message' => 'NEW_01',
            'extra_notes' => 'Granted from housekeeping',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/badges/grant', [
                'user_id' => $target->id,
                'badge' => 'bad badge',
            ])
            ->assertRedirect('/allseeingeye/hk/badges?query='.$target->id);
        $this->assertDatabaseMissing('users_badges', [
            'user_id' => $target->id,
            'badge' => 'bad badge',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/badges/grant', [
                'user_id' => $target->id.'abc',
                'badge' => 'MALFORMED_GRANT',
            ])
            ->assertRedirect('/allseeingeye/hk/badges?query=0');
        $this->assertDatabaseMissing('users_badges', [
            'user_id' => $target->id,
            'badge' => 'MALFORMED_GRANT',
        ]);
        $this->assertDatabaseMissing('housekeeping_audit_log', [
            'action' => 'badge_grant',
            'target_id' => $target->id,
            'message' => 'MALFORMED_GRANT',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/badges/grant', [
                'user_id' => $target->id,
                'badge' => 'NEW_01',
            ])
            ->assertRedirect('/allseeingeye/hk/badges?query='.$target->id);
        $this->assertSame(1, \DB::table('users_badges')->where('user_id', $target->id)->where('badge', 'NEW_01')->count());

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/badges/update', [
                'user_id' => $target->id,
                'badge' => 'NEW_01',
                'equipped' => 'on',
                'slot_id' => 3,
            ])
            ->assertRedirect('/allseeingeye/hk/badges?query='.$target->id);
        $this->assertDatabaseHas('users_badges', [
            'user_id' => $target->id,
            'badge' => 'NEW_01',
            'equipped' => true,
            'slot_id' => 3,
        ]);
        $this->assertDatabaseHas('housekeeping_audit_log', [
            'action' => 'badge_update',
            'user_id' => $admin->id,
            'target_id' => $target->id,
            'message' => 'NEW_01',
            'extra_notes' => 'Updated equipped/slot from housekeeping',
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/badges/update', [
                'user_id' => $target->id.'abc',
                'badge' => 'NEW_01',
                'slot_id' => 4,
            ])
            ->assertRedirect('/allseeingeye/hk/badges?query=0');
        $this->assertDatabaseHas('users_badges', [
            'user_id' => $target->id,
            'badge' => 'NEW_01',
            'equipped' => true,
            'slot_id' => 3,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/badges/update', [
                'user_id' => $target->id,
                'badge' => 'NEW_01',
                'slot_id' => '4abc',
            ])
            ->assertRedirect('/allseeingeye/hk/badges?query='.$target->id);
        $this->assertDatabaseHas('users_badges', [
            'user_id' => $target->id,
            'badge' => 'NEW_01',
            'equipped' => true,
            'slot_id' => 3,
        ]);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/badges/update', [
                'user_id' => $target->id,
                'badge' => 'NEW_01',
                'slot_id' => 6,
            ])
            ->assertRedirect('/allseeingeye/hk/badges?query='.$target->id);
        $this->assertDatabaseHas('users_badges', [
            'user_id' => $target->id,
            'badge' => 'NEW_01',
            'slot_id' => 3,
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/badges?query='.$target->id)
            ->assertOk()
            ->assertSee('NEW_01', false)
            ->assertSee('badge_grant', false)
            ->assertSee('badge_update', false)
            ->assertSee('Granted from housekeeping', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/badges/remove?user_id='.$target->id.'abc&badge=NEW_01')
            ->assertRedirect('/allseeingeye/hk/badges?query=0');
        $this->assertDatabaseHas('users_badges', [
            'user_id' => $target->id,
            'badge' => 'NEW_01',
        ]);
        $this->assertSame(0, \DB::table('housekeeping_audit_log')
            ->where('action', 'badge_remove')
            ->where('target_id', $target->id)
            ->where('message', 'NEW_01')
            ->count());

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/badges/remove?user_id='.$target->id.'&badge=NEW_01')
            ->assertRedirect('/allseeingeye/hk/badges?query='.$target->id);
        $this->assertDatabaseMissing('users_badges', [
            'user_id' => $target->id,
            'badge' => 'NEW_01',
        ]);
        $this->assertDatabaseHas('housekeeping_audit_log', [
            'action' => 'badge_remove',
            'user_id' => $admin->id,
            'target_id' => $target->id,
            'message' => 'NEW_01',
            'extra_notes' => 'Removed from housekeeping',
        ]);

        $moderator = $this->createLegacyUser([
            'username' => 'BadgeModerator',
            'email' => 'badge-moderator@example.test',
        ]);
        $moderator->forceFill(['rank' => 6])->save();
        $this->insertStatistics($moderator->id);

        $this->withSession([
            'housekeeping.authenticated' => true,
            'user.id' => $moderator->id,
        ])
            ->get('/allseeingeye/hk/badges')
            ->assertOk();

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/badges')
            ->assertRedirect('/allseeingeye/hk');
    }

    public function test_housekeeping_infobus_poll_routes(): void
    {
        $admin = $this->createLegacyUser([
            'username' => 'InfobusAdmin',
            'email' => 'infobus-admin@example.test',
        ]);
        $admin->forceFill(['rank' => 8])->save();
        $this->insertStatistics($admin->id);

        $staffSession = [
            'housekeeping.authenticated' => true,
            'user.id' => $admin->id,
        ];

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/infobus_polls/create', [
                'question' => 'Best room?',
                'answers' => ['Lido', '', 'Cafe', 'Theatre'],
            ])
            ->assertRedirect('/allseeingeye/hk/infobus_polls');

        $poll = \DB::table('infobus_polls')->where('initiated_by', $admin->id)->first();
        $this->assertNotNull($poll);
        $this->assertSame('Best room?', json_decode($poll->poll_data, true)['question']);
        $this->assertSame(['Lido', '', 'Cafe', 'Theatre'], json_decode($poll->poll_data, true)['answers']);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls')
            ->assertOk()
            ->assertSee('Best room?', false)
            ->assertSee('InfobusAdmin', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/edit?id='.$poll->id)
            ->assertOk()
            ->assertSee('Best room?', false)
            ->assertSee('Lido', false);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/infobus_polls/edit?id='.$poll->id, [
                'question' => 'Best public room?',
                'answers' => ['Lido', '', 'Cafe'],
            ])
            ->assertRedirect('/allseeingeye/hk/infobus_polls');

        $poll = \DB::table('infobus_polls')->where('id', $poll->id)->first();
        $this->assertSame('Best public room?', json_decode($poll->poll_data, true)['question']);
        $this->assertSame(['Lido', '', 'Cafe'], json_decode($poll->poll_data, true)['answers']);

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/infobus_polls/edit?id='.$poll->id.'abc', [
                'question' => 'Malformed edit',
                'answers' => ['No'],
            ])
            ->assertRedirect('/allseeingeye/hk/infobus_polls')
            ->assertSessionHas('alertColour', 'danger')
            ->assertSessionHas('alertMessage', 'The infobus poll does not exist');

        $this->assertSame('Best public room?', json_decode(\DB::table('infobus_polls')->where('id', $poll->id)->value('poll_data'), true)['question']);

        \DB::table('infobus_polls_answers')->insert([
            ['poll_id' => $poll->id, 'answer' => 0, 'user_id' => $admin->id],
            ['poll_id' => $poll->id, 'answer' => 1, 'user_id' => $admin->id + 1],
        ]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/view_results?id='.$poll->id)
            ->assertOk()
            ->assertSee('data:image/png;base64', false);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/view_results')
            ->assertRedirect('/allseeingeye/hk/infobus_polls')
            ->assertSessionHas('alertColour', 'danger')
            ->assertSessionHas('alertMessage', 'There was no infobus poll selected to edit');

        $this->withSession($staffSession)
            ->post('/allseeingeye/hk/infobus_polls/edit?id='.$poll->id, [
                'question' => 'Blocked edit',
                'answers' => ['No'],
            ])
            ->assertRedirect('/allseeingeye/hk/infobus_polls');

        $this->assertSame('Best public room?', json_decode(\DB::table('infobus_polls')->where('id', $poll->id)->value('poll_data'), true)['question']);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/delete?id='.$poll->id)
            ->assertRedirect('/allseeingeye/hk/infobus_polls');
        $this->assertDatabaseHas('infobus_polls', ['id' => $poll->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/clear_results?id='.$poll->id.'abc')
            ->assertRedirect('/allseeingeye/hk/infobus_polls')
            ->assertSessionHas('alertColour', 'danger')
            ->assertSessionHas('alertMessage', 'The infobus poll does not exist');
        $this->assertDatabaseHas('infobus_polls_answers', ['poll_id' => $poll->id, 'answer' => 0]);
        $this->assertDatabaseHas('infobus_polls_answers', ['poll_id' => $poll->id, 'answer' => 1]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/clear_results?id='.$poll->id)
            ->assertRedirect('/allseeingeye/hk/infobus_polls');
        $this->assertDatabaseMissing('infobus_polls_answers', ['poll_id' => $poll->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/clear_results')
            ->assertRedirect('/allseeingeye/hk/infobus_polls')
            ->assertSessionHas('alertColour', 'danger')
            ->assertSessionHas('alertMessage', 'There was no infobus poll selected to edit');

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/send_poll?id='.$poll->id)
            ->assertRedirect('/allseeingeye/hk/infobus_polls');

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/door_status?status=1')
            ->assertRedirect('/allseeingeye/hk/infobus_polls');

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/close_event')
            ->assertRedirect('/allseeingeye/hk/infobus_polls');

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/delete?id='.$poll->id.'abc')
            ->assertRedirect('/allseeingeye/hk/infobus_polls')
            ->assertSessionHas('alertColour', 'danger')
            ->assertSessionHas('alertMessage', 'The infobus poll does not exist');
        $this->assertDatabaseHas('infobus_polls', ['id' => $poll->id]);

        $this->withSession($staffSession)
            ->get('/allseeingeye/hk/infobus_polls/delete?id='.$poll->id)
            ->assertRedirect('/allseeingeye/hk/infobus_polls');
        $this->assertDatabaseMissing('infobus_polls', ['id' => $poll->id]);

        $moderator = $this->createLegacyUser([
            'username' => 'InfobusMod',
            'email' => 'infobus-mod@example.test',
        ]);
        $moderator->forceFill(['rank' => 6])->save();
        $this->insertStatistics($moderator->id);

        \DB::table('infobus_polls')->insert([
            'id' => 99,
            'initiated_by' => $admin->id,
            'poll_data' => json_encode(['question' => 'Other poll', 'answers' => ['A']]),
            'created_at' => now(),
        ]);

        $this->withSession([
            'housekeeping.authenticated' => true,
            'user.id' => $moderator->id,
        ])
            ->get('/allseeingeye/hk/infobus_polls/delete?id=99')
            ->assertRedirect('/allseeingeye/hk');
        $this->assertDatabaseHas('infobus_polls', ['id' => 99]);

        $this->withSession(['housekeeping.authenticated' => false])
            ->get('/allseeingeye/hk/infobus_polls')
            ->assertRedirect('/allseeingeye/hk');
    }

    private function enableEmail(): void
    {
        \DB::table('settings')->insert([
            'setting' => 'email.smtp.enable',
            'value' => 'true',
        ]);
        app(HavanaConfig::class)->reload();
    }

    private function createLegacyUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'username' => 'Alex',
            'password' => app(LegacyPasswordHasher::class)->make('secret123'),
            'figure' => 'hd-180-1',
            'sex' => 'M',
            'email' => 'alex@example.test',
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertStatistics(int $userId, array $overrides = []): void
    {
        \DB::table('users_statistics')->insert(array_merge([
            'user_id' => $userId,
            'activation_code' => null,
            'forgot_password_code' => null,
            'forgot_recovery_requested_time' => null,
        ], $overrides));
    }
}
