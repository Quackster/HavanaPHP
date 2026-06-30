<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyTemplate;
use App\Support\LegacyGroup;
use App\Support\LegacyKeyValue;
use App\Support\LegacyRoom;
use App\Support\LegacyRoomData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HabbletController extends Controller
{
    private const ALLOWED_NAME_CHARS = '1234567890qwertyuiopasdfghjklzxcvbnm-+=?!@:.,$';

    public function nameCheck(Request $request): Response
    {
        $message = match ($this->nameErrorCode((string) $request->input('name', ''))) {
            6 => 'This name is unacceptable to hotel management.',
            5 => 'Your username is invalid or contains invalid characters.',
            4 => 'This name is not allowed.',
            3 => 'The name you have chosen is too long.',
            2 => 'Please enter a username.',
            1 => 'A user with this name already exists.',
            default => '',
        };

        return response('', 200, [
            'X-JSON' => json_encode(['registration_name' => $message], JSON_THROW_ON_ERROR),
        ]);
    }

    public function updateMotto(Request $request, HavanaConfig $config): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        if ($request->session()->has('lastMottoUpdate') && time() < (int) $request->session()->get('lastMottoUpdate')) {
            return response($this->crocoScript($config).e((string) $user->motto));
        }

        $request->session()->put('lastMottoUpdate', time());

        $motto = strip_tags((string) $request->input('motto', ''));
        $motto = substr($motto, 0, 100);
        $responsePrefix = '';

        if (str_replace(' ', '', $motto) === '') {
            $responsePrefix = 'Click to enter your motto/ status';
            $motto = '';
        } elseif (strtolower($motto) === 'crikey') {
            $responsePrefix = $this->crocoScript($config);
        }

        if ((string) $user->motto !== $motto) {
            $user->forceFill(['motto' => $motto])->save();
        }

        return response($responsePrefix.e($motto));
    }

    public function roomSelectionConfirm(LegacyTemplate $template): Response
    {
        return response($template->render('habblet/roomselectionConfirm'));
    }

    public function roomSelectionCreate(Request $request): Response
    {
        $user = $this->currentUser($request);

        if (! $user || ! $request->has('roomType') || ! $this->canSelectRoom($user)) {
            return response('');
        }

        $roomType = (int) $request->input('roomType', -1);

        if ($roomType < 0 || $roomType > 5) {
            return response('');
        }

        return response('');
    }

    public function roomSelectionHide(Request $request): Response
    {
        $user = $this->currentUser($request);

        if (! $user || ! $this->canSelectRoom($user)) {
            return response('');
        }

        $user->forceFill(['selected_room_id' => -1])->save();

        DB::table('users_statistics')->updateOrInsert(
            ['user_id' => $user->id],
            ['newbie_room_layout' => -1],
        );

        return response('');
    }

    public function roomNavigation(): Response
    {
        return response('');
    }

    public function nextGift(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $statistics = $this->statistics($user->id);
        $giftSeconds = 0;

        if ((int) $statistics['newbie_room_layout'] > 0 && (int) $statistics['newbie_gift'] > 0) {
            if ($this->progressBeginnerGift($user, $statistics)) {
                $statistics = $this->statistics($user->id);
            }

            $giftSeconds = max(0, (int) $statistics['newbie_gift_time'] - time());
        }

        return response($template->render('habblet/nextgift', [
            'playerDetails' => $user,
            'newbieRoomLayout' => (int) $statistics['newbie_room_layout'],
            'newbieNextGift' => (int) $statistics['newbie_gift'],
            'newbieGiftSeconds' => $giftSeconds,
        ]));
    }

    public function giftQueueHide(Request $request): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $nextGift = (int) DB::table('users_statistics')
            ->where('user_id', $user->id)
            ->value('newbie_gift');

        if ($nextGift === 3) {
            DB::table('users_statistics')->where('user_id', $user->id)->update([
                'newbie_gift' => 4,
            ]);
        }

        return response('');
    }

    public function addTag(Request $request, HavanaConfig $config): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        if (count($this->userTags($user->id)) >= $config->integer('max.tags.users')) {
            return response('taglimit');
        }

        $tag = $this->validTag((string) $request->input('tagName', ''));

        if ($tag === null) {
            return response('invalidtag');
        }

        DB::table('users_tags')->updateOrInsert([
            'user_id' => $user->id,
            'tag' => $tag,
            'room_id' => '0',
            'group_id' => '0',
        ], [
            'created_at' => now(),
        ]);

        return response('valid');
    }

    public function removeTag(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        DB::table('users_tags')
            ->where('user_id', $user->id)
            ->where('room_id', '0')
            ->where('group_id', '0')
            ->where('tag', (string) $request->input('tagName', ''))
            ->delete();

        return response($template->render('homes/widget/habblet/taglist', [
            'playerDetails' => $user,
            'user' => $user,
            'tags' => $this->userTags($user->id),
        ]));
    }

    public function removeAllTags(Request $request): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('Please login to remove all your tags.');
        }

        $tags = $this->userTags($user->id);

        DB::table('users_tags')
            ->where('user_id', $user->id)
            ->where('room_id', '0')
            ->where('group_id', '0')
            ->delete();

        return response('All tags removed!<br><br>The tags removed: '.implode(', ', $tags));
    }

    public function myTagsList(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        return response($template->render('habblet/myTagList', [
            'tags' => $this->userTags($user->id),
            'tagRandomQuestion' => 'What are you into?',
        ]));
    }

    public function tagFight(Request $request, LegacyTemplate $template): Response
    {
        $firstTag = strip_tags((string) $request->input('tag1', ''));
        $secondTag = strip_tags((string) $request->input('tag2', ''));
        $firstCount = $this->tagCount($firstTag);
        $secondCount = $this->tagCount($secondTag);
        $imageNumber = 0;
        $result = 'Tie!';

        if ($secondCount > $firstCount) {
            $imageNumber = 1;
            $result = 'The winner is:';
        } elseif ($secondCount < $firstCount) {
            $imageNumber = 2;
            $result = 'The winner is:';
        }

        return response($template->render('habblet/tagFightResult', [
            'result' => $result,
            'resultTag1' => $firstTag,
            'resultTag2' => $secondTag,
            'resultHits1' => $firstCount,
            'resultHits2' => $secondCount,
            'tagFightImage' => $imageNumber,
        ]));
    }

    public function tagMatch(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('', 302, ['Location' => '/']);
        }

        $friendId = User::query()
            ->where('username', (string) $request->input('friendName', ''))
            ->value('id');
        $friendExists = $friendId && DB::table('messenger_friends')
            ->where('from_id', $user->id)
            ->where('to_id', (int) $friendId)
            ->exists();

        return response($template->render('habblet/tagMatch', [
            'errorMsg' => $friendExists ? '' : 'Friend not found. Are you sure that they really exist?',
        ]));
    }

    public function redeemVoucher(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $code = (string) $request->input('voucherCode', '');
        $result = DB::transaction(fn (): string => $this->redeemVoucherForUser($code, $user));

        return response($template->render('habblet/redeemvoucher', [
            'playerDetails' => $user->fresh(),
            'voucherResult' => $result,
        ]));
    }

    public function proxy(Request $request, LegacyTemplate $template): Response
    {
        $hid = (string) $request->query('hid', '');

        return match ($hid) {
            'h21' => response($template->render('habblet/staff_pick_rooms', [
                'rooms' => $this->recommendedRooms(true, 5, 0),
            ])),
            'h120' => response($template->render('habblet/showMoreRooms', [
                'highestRatedRooms' => $this->highestRatedRooms(5, 0),
                'highestHiddenRatedRooms' => $this->highestRatedRooms(5, 5),
            ])),
            'h122' => response($template->render('habblet/community_hot_groups', [
                'hotGroups' => $this->hotGroups(app(HavanaConfig::class)->integer('hot.groups.community.limit') ?: 8, 0),
                'hotHiddenGroups' => $this->hotGroups(app(HavanaConfig::class)->integer('hot.groups.community.limit') ?: 8, 8),
            ])),
            'h24' => response($template->render('habblet/tagList', ['tagCloud' => $this->tagCloud(20)])),
            'groups' => response($template->render('habblet/hot_groups', [
                'groups' => $this->hotGroups(app(HavanaConfig::class)->integer('hot.groups.limit') ?: 10, 0),
            ])),
            default => response(''),
        };
    }

    public function clientProxy(Request $request, LegacyTemplate $template, MinimailController $minimail): Response
    {
        if (! $this->currentUser($request)) {
            return response('', 302, ['Location' => '/']);
        }

        if (strtolower((string) $request->query('habbletKey', '')) === 'news') {
            return response($this->clientNewsShell());
        }

        return $minimail->clientHabblet($request, $template);
    }

    private function clientNewsShell(): string
    {
        return <<<'HTML'
<div class="habblet-container ">		
	
	<div id="news-habblet-container">
	
		<div class="title">
		
			<div class="habblet-close"></div>
			
			<div>The shit you don't even wanna know!</div>
			
		</div>
		
		<div class="content-container">
		
			<div id="news-articles">
			
				<ul id="news-articlelist" class="articlelist" style="display: none">

				</ul>
				
			</div>
			
		</div>
		
		<div class="news-footer"></div>
	
	</div>

	<script type="text/javascript">    
		L10N.put("news.promo.readmore", "Read more").put("news.promo.close", "Close article");
		News.init(false);
	</script>

</div>

<!-- dependencies
<link rel="stylesheet" href="http://images.habbo.com/habboweb/%web_build%/web-gallery/v2/styles/news.css" type="text/css" />
<script src="http://images.habbo.com/habboweb/%web_build%/web-gallery/static/js/news.js" type="text/javascript"></script>
-->
HTML;
    }

    public function clearHand(Request $request): Response
    {
        if (! $this->currentUser($request)) {
            return response('');
        }

        $user = $this->currentUser($request);

        if (! $user || ! $this->verifyXssKey($request, '/credits')) {
            return response('Failed to securely verify request');
        }

        DB::table('items')
            ->where('user_id', (int) $user->id)
            ->where('room_id', 0)
            ->where('is_hidden', false)
            ->where('is_trading', false)
            ->delete();

        return response('');
    }

    public function tokenGenerate(Request $request): Response
    {
        if (! $this->currentUser($request)) {
            return response('');
        }

        $token = 'token-'.Str::uuid();
        $request->session()->put('authenticationToken', $token);

        return response($token);
    }

    private function currentUser(Request $request): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user;
        }

        $userId = (int) $request->session()->get('user.id', 0);

        if ($userId > 0 && $request->session()->get('authenticated')) {
            return User::query()->find($userId);
        }

        return null;
    }

    private function verifyXssKey(Request $request, string $expectedRoute): bool
    {
        if (! $request->session()->has('xssKey') || ! $request->session()->has('xssSeed') || ! $request->session()->has('xssRequested')) {
            $this->clearXssKey($request);

            return false;
        }

        if (strcasecmp($expectedRoute, (string) $request->session()->get('xssRequested')) !== 0) {
            $this->clearXssKey($request);

            return false;
        }

        $expectedKey = $this->javaRandomNextInt((int) $request->session()->get('xssSeed'));
        $providedKey = (int) $request->session()->get('xssKey');
        $this->clearXssKey($request);

        return $providedKey === $expectedKey;
    }

    private function clearXssKey(Request $request): void
    {
        $request->session()->forget(['xssKey', 'xssSeed', 'xssRequested']);
    }

    private function javaRandomNextInt(int $seed): int
    {
        $multiplier = 0x5DEECE66D;
        $mask = (1 << 48) - 1;
        $state = (($seed & 0xFFFFFFFF) ^ $multiplier) & $mask;
        $state = $this->javaRandomNextState($state);
        $value = $state >> 16;

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private function javaRandomNextState(int $state): int
    {
        $multiplier = 0x5DEECE66D;
        $mask16 = 0xFFFF;
        $a0 = $state & $mask16;
        $a1 = ($state >> 16) & $mask16;
        $a2 = ($state >> 32) & $mask16;
        $b0 = $multiplier & $mask16;
        $b1 = ($multiplier >> 16) & $mask16;
        $b2 = ($multiplier >> 32) & $mask16;

        $c0 = $a0 * $b0 + 0xB;
        $d0 = $c0 & $mask16;
        $carry = $c0 >> 16;

        $c1 = $a0 * $b1 + $a1 * $b0 + $carry;
        $d1 = $c1 & $mask16;
        $carry = $c1 >> 16;

        $c2 = $a0 * $b2 + $a1 * $b1 + $a2 * $b0 + $carry;
        $d2 = $c2 & $mask16;

        return $d0 | ($d1 << 16) | ($d2 << 32);
    }

    private function canSelectRoom(User $user): bool
    {
        return (int) $user->selected_room_id === 0;
    }

    /** @return array<string, mixed> */
    private function statistics(int $userId): array
    {
        $row = DB::table('users_statistics')->where('user_id', $userId)->first();

        if (! $row) {
            return [
                'newbie_room_layout' => 0,
                'newbie_gift' => 0,
                'newbie_gift_time' => 0,
            ];
        }

        return (array) $row;
    }

    /** @param array<string, mixed> $statistics */
    private function progressBeginnerGift(User $user, array $statistics): bool
    {
        $gift = (int) $statistics['newbie_gift'];

        if ($gift > 2 || (int) $statistics['newbie_gift_time'] > time()) {
            return false;
        }

        $sprite = match ($gift) {
            1 => 'noob_stool*'.(int) $statistics['newbie_room_layout'],
            2 => 'noob_plant',
            default => null,
        };

        if (! $sprite) {
            return false;
        }

        $giftDefinition = DB::table('items_definitions')->where('sprite', $sprite)->first(['id', 'name']);
        $presentDefinitionId = DB::table('items_definitions')
            ->whereIn('sprite', ['present_gen', 'present_gen1', 'present_gen2', 'present_gen3', 'present_gen4', 'present_gen5', 'present_gen6'])
            ->orderBy('sprite')
            ->value('id');

        if (! $giftDefinition || ! $presentDefinitionId) {
            return false;
        }

        $message = str_replace(
            '%item_name%',
            (string) $giftDefinition->name,
            (string) config('havana.alerts.gift.message', 'A new gift has arrived. This time you received a %item_name%.'),
        );
        $now = time();

        DB::table('cms_alerts')->insert([
            'user_id' => (int) $user->id,
            'alert_type' => 'PRESENT',
            'message' => $message,
            'is_disabled' => false,
            'created_at' => now(),
        ]);

        DB::table('items')->insert([
            'order_id' => -1,
            'user_id' => (int) $user->id,
            'room_id' => 0,
            'definition_id' => (int) $presentDefinitionId,
            'x' => 0,
            'y' => 0,
            'z' => '0',
            'wall_position' => '',
            'rotation' => 0,
            'custom_data' => '0'.chr(9).(string) $user->username.chr(9).$message.chr(9).chr(9).$now,
            'is_hidden' => false,
            'is_trading' => false,
            'expire_time' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nextGift = $gift + 1;
        DB::table('users_statistics')->where('user_id', (int) $user->id)->update([
            'newbie_gift' => $nextGift < 3 ? $nextGift : 3,
            'newbie_gift_time' => $nextGift < 3 ? $now + 86400 : 0,
        ]);

        return true;
    }

    private function nameErrorCode(string $username): int
    {
        if (! $this->hasAllowedCharacters(strtolower($username))) {
            return 5;
        }

        $lower = strtolower($username);

        if (in_array($lower, ['admin', 'mod', 'staff', 'moderator', 'vip'], true)
            || preg_match('/^(admin|mod|bot|vip)[-=]/', $lower)) {
            return 4;
        }

        if (strlen($username) > 24) {
            return 3;
        }

        if (strlen($username) < 1) {
            return 2;
        }

        if (User::query()->where('username', $username)->exists()) {
            return 1;
        }

        return 0;
    }

    private function hasAllowedCharacters(string $username): bool
    {
        if ($username === '') {
            return true;
        }

        for ($index = 0, $length = strlen($username); $index < $length; $index++) {
            if (! str_contains(self::ALLOWED_NAME_CHARS, $username[$index])) {
                return false;
            }
        }

        return true;
    }

    private function validTag(string $tag): ?string
    {
        $tag = strtolower(trim(strip_tags($tag)));
        $tag = preg_replace('/\s+/', '', $tag) ?? '';

        if ($tag === '' || strlen($tag) > 20 || ! preg_match('/^[a-z0-9]+$/', $tag)) {
            return null;
        }

        return $tag;
    }

    /** @return list<string> */
    private function userTags(int $userId): array
    {
        return DB::table('users_tags')
            ->where('user_id', $userId)
            ->where('room_id', '0')
            ->where('group_id', '0')
            ->orderBy('created_at')
            ->pluck('tag')
            ->map(fn ($tag): string => (string) $tag)
            ->all();
    }

    private function tagCount(string $tag): int
    {
        return DB::table('users_tags')->where('tag', $tag)->count();
    }

    /** @return list<LegacyKeyValue> */
    private function tagCloud(int $limit): array
    {
        return DB::table('users_tags')
            ->select('tag', DB::raw('count(*) as total'))
            ->groupBy('tag')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): LegacyKeyValue => new LegacyKeyValue((string) $row->tag, 10 + min(10, (int) $row->total)))
            ->all();
    }

    /** @return list<LegacyRoom> */
    private function recommendedRooms(bool $staffPick, int $limit, int $offset): array
    {
        $roomIds = DB::table('cms_recommended')
            ->where('type', 'ROOM')
            ->where('is_staff_pick', $staffPick)
            ->offset($offset)
            ->limit($limit)
            ->pluck('recommended_id');

        return $this->roomsByIds($roomIds->map(fn ($id): int => (int) $id)->all());
    }

    /** @return list<LegacyRoom> */
    private function highestRatedRooms(int $limit, int $offset): array
    {
        $ids = DB::table('rooms')
            ->where('is_hidden', false)
            ->orderByDesc('rating')
            ->offset($offset)
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->roomsByIds($ids);
    }

    /** @param list<int> $ids @return list<LegacyRoom> */
    private function roomsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $owners = User::query()->get()->keyBy('id');

        return DB::table('rooms')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($row): int => array_search((int) $row->id, $ids, true))
            ->map(function ($row) use ($owners): LegacyRoom {
                $ownerId = (int) $row->owner_id;
                $owner = $owners->get($ownerId);
                $ownerName = $owner instanceof User ? (string) $owner->username : 'Habbo';

                return new LegacyRoom(new LegacyRoomData(
                    (int) $row->id,
                    (string) $row->name,
                    (string) $row->description,
                    $ownerName,
                    (int) $row->visitors_now,
                    (int) $row->visitors_max,
                ));
            })
            ->values()
            ->all();
    }

    /** @return list<LegacyGroup> */
    private function hotGroups(int $limit, int $offset): array
    {
        return DB::table('groups_details')
            ->leftJoin('groups_memberships', 'groups_details.id', '=', 'groups_memberships.group_id')
            ->select('groups_details.id', 'groups_details.name', 'groups_details.description', 'groups_details.badge', 'groups_details.alias', DB::raw('count(groups_memberships.user_id) as members'))
            ->groupBy('groups_details.id', 'groups_details.name', 'groups_details.description', 'groups_details.badge', 'groups_details.alias')
            ->orderByDesc('members')
            ->orderByDesc('groups_details.views')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row): LegacyGroup => new LegacyGroup(
                (int) $row->id,
                (string) $row->name,
                (string) $row->description,
                (string) $row->badge,
                $row->alias !== null ? (string) $row->alias : null,
            ))
            ->all();
    }

    private function voucherUsed(string $code, int $userId): bool
    {
        return DB::table('vouchers_history')
            ->where('voucher_code', $code)
            ->where('user_id', $userId)
            ->exists();
    }

    private function voucherExpired(mixed $expiryDate): bool
    {
        if ($expiryDate === null || $expiryDate === '') {
            return false;
        }

        return strtotime((string) $expiryDate) < time();
    }

    private function redeemVoucherForUser(string $code, User $user): string
    {
        $voucher = DB::table('vouchers')
            ->where('voucher_code', $code)
            ->first();

        if (! $voucher || $this->voucherExpired($voucher->expiry_date ?? null) || $this->voucherUsed($code, (int) $user->id)) {
            return 'error';
        }

        $catalogueItems = $this->voucherCatalogueItems($code);

        if ((bool) $voucher->is_single_use) {
            DB::table('vouchers')->where('voucher_code', $code)->delete();
            DB::table('vouchers_items')->where('voucher_code', $code)->delete();
        }

        if (! (bool) $voucher->allow_new_users && $this->voucherOnlineHours((int) $user->id) < 1) {
            return 'too_new';
        }

        $credits = (int) $voucher->credits;

        if ($credits > 0) {
            DB::table('users')->where('id', (int) $user->id)->increment('credits', $credits);
        }

        $this->grantVoucherItems((int) $user->id, $catalogueItems);

        DB::table('vouchers_history')->insert([
            'voucher_code' => $code,
            'user_id' => (int) $user->id,
            'credits_redeemed' => $credits > 0 ? $credits : null,
            'items_redeemed' => $this->voucherItemsHistory($catalogueItems),
        ]);

        return 'success';
    }

    private function voucherOnlineHours(int $userId): int
    {
        $onlineTime = (int) DB::table('users_statistics')
            ->where('user_id', $userId)
            ->value('online_time');

        return (int) floor($onlineTime / 3600);
    }

    /** @return array<int, object> */
    private function voucherCatalogueItems(string $code): array
    {
        return DB::table('vouchers_items')
            ->join('catalogue_items', 'catalogue_items.sale_code', '=', 'vouchers_items.catalogue_sale_code')
            ->where('vouchers_items.voucher_code', $code)
            ->select('catalogue_items.id', 'catalogue_items.sale_code', 'catalogue_items.order_id', 'catalogue_items.amount', 'catalogue_items.definition_id')
            ->get()
            ->all();
    }

    /** @param array<int, object> $catalogueItems */
    private function grantVoucherItems(int $userId, array $catalogueItems): void
    {
        foreach ($catalogueItems as $catalogueItem) {
            $amount = max(1, (int) $catalogueItem->amount);

            for ($i = 0; $i < $amount; $i++) {
                DB::table('items')->insert([
                    'order_id' => (int) $catalogueItem->order_id,
                    'user_id' => $userId,
                    'room_id' => 0,
                    'definition_id' => (int) $catalogueItem->definition_id,
                    'x' => 0,
                    'y' => 0,
                    'z' => '0',
                    'wall_position' => '',
                    'rotation' => 0,
                    'custom_data' => '',
                    'is_hidden' => false,
                    'is_trading' => false,
                    'expire_time' => -1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** @param array<int, object> $catalogueItems */
    private function voucherItemsHistory(array $catalogueItems): ?string
    {
        if ($catalogueItems === []) {
            return null;
        }

        $counts = [];

        foreach ($catalogueItems as $catalogueItem) {
            $saleCode = (string) $catalogueItem->sale_code;
            $counts[$saleCode] = ($counts[$saleCode] ?? 0) + 1;
        }

        return collect($counts)
            ->map(fn (int $count, string $saleCode): string => $count.','.$saleCode)
            ->implode('|');
    }

    private function crocoScript(HavanaConfig $config): string
    {
        return '<script>document.getElementById("habbo-plate").innerHTML = "<img src=\''.
            $config->string('site.path').
            '/web-gallery/images/sticker_croco.gif\' style=\'margin-top: 57px\'>";</script>';
    }
}
