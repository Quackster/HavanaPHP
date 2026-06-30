<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingItemDefinitionView;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use App\Support\HousekeepingRank;
use App\Support\HousekeepingRecyclerRewardView;
use App\Support\HousekeepingRoomCategoryView;
use App\Support\HousekeepingRoomModelView;
use App\Support\HousekeepingVoucherHistoryView;
use App\Support\HousekeepingVoucherItemView;
use App\Support\HousekeepingVoucherView;
use App\Support\HousekeepingWordfilterView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HousekeepingGameDataController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    /** @var list<string> */
    private array $behaviours = [
        'solid',
        'solid_single_tile',
        'can_stack_on_top',
        'can_not_stack_on_top',
        'can_sit_on_top',
        'can_stand_on_top',
        'can_lay_on_top',
        'custom_data_numeric_on_off',
        'requires_touching_for_interaction',
        'custom_data_true_false',
        'public_space_object',
        'extra_parameter',
        'dice',
        'custom_data_on_off',
        'custom_data_numeric_state',
        'teleporter',
        'door_teleporter',
        'requires_rights_for_interaction',
        'gate',
        'one_way_gate',
        'prize_trophy',
        'roller',
        'redeemable',
        'sound_machine',
        'sound_machine_sample_set',
        'jukebox',
        'wall_item',
        'post_it',
        'decoration',
        'wheel_of_fortune',
        'roomdimmer',
        'present',
        'photo',
        'place_roller_on_top',
        'invisible',
        'effect',
        'song_disk',
        'private_furniture',
        'redirect_rotation_0',
        'redirect_rotation_2',
        'redirect_rotation_4',
        'no_head_turn',
        'eco_box',
        'pet_water_bowl',
        'pet_food',
        'pet_cat_food',
        'pet_dog_food',
        'pet_croc_food',
        'pet_toy',
    ];

    /** @var list<string> */
    private array $interactors = [
        'default',
        'bed',
        'chair',
        'teleport',
        'room_hire',
        'vending_machine',
        'lert',
        'scoreboard',
        'fortune',
        'pet_nest',
        'pet_food',
        'pet_water_bowl',
        'pet_toy',
        'pool_booth',
        'pool_ladder',
        'pool_exit',
        'pool_lift',
        'pool_queue',
        'game_tic_tac_toe',
        'game_chess',
        'game_battleships',
        'game_poker',
        'totem_leg',
        'totem_head',
        'totem_planet',
        'ws_join_queue',
        'ws_queue_tile',
        'ws_tile_start',
        'idol_vote_chair',
        'idol_scoreboard',
        'step_light',
        'love_randomizer',
        'multi_height',
    ];

    /** @var list<string> */
    private array $roomModelTriggers = [
        'flat_trigger',
        'battleball_lobby_trigger',
        'snowstorm_lobby_trigger',
        'space_cafe_trigger',
        'habbo_lido_trigger',
        'rooftop_rumble_trigger',
        'diving_deck_trigger',
        'infobus_park',
        'infobus_poll',
        'none',
    ];

    public function itemDefinitions(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'item_definitions/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/item_definitions', $staff, [
            'pageName' => 'Item Definitions',
            'definitions' => $this->itemDefinitionsList(),
        ]);
    }

    public function editItemDefinition(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'item_definitions/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $definition = $id > 0 ? $this->itemDefinition($id) : null;

        if ($id > 0 && $definition === null) {
            $this->alert($request, 'Item definition does not exist', 'danger');

            return redirect($this->housekeepingUrl('/item_definitions'));
        }

        if ($request->isMethod('post')) {
            $sprite = trim((string) $request->input('sprite', ''));
            $behaviour = $this->normaliseCsv((string) $request->input('behaviour', ''));
            $interactor = trim((string) $request->input('interactor', ''));
            $topHeight = filter_var($request->input('top_height'), FILTER_VALIDATE_FLOAT);
            $behaviourError = $this->validateBehaviours($behaviour);

            if ($sprite === '') {
                $this->alert($request, 'Sprite cannot be blank', 'danger');
            } elseif ($topHeight === false) {
                $this->alert($request, 'Top height must be a valid number', 'danger');
            } elseif ($interactor === '' || ! in_array($interactor, $this->interactors, true)) {
                $this->alert($request, 'Interactor must match a known interaction type', 'danger');
            } elseif ($behaviourError !== null) {
                $this->alert($request, $behaviourError, 'danger');
            } else {
                $savedId = $this->saveItemDefinition($id, $sprite, (float) $topHeight, $behaviour, $request);
                $this->alert($request, 'Item definition saved successfully', 'success');

                return redirect($this->housekeepingUrl('/item_definitions/edit?id='.$savedId));
            }
        }

        return $this->render($template, 'housekeeping/item_definition_edit', $staff, [
            'pageName' => $id > 0 ? 'Edit Item Definition' : 'Create Item Definition',
            'definition' => $definition,
            'behaviours' => $this->behaviours,
            'interactors' => $this->interactors,
        ]);
    }

    public function deleteItemDefinition(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'item_definitions/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('items_definitions')->where('id', $id)->delete();
            $this->alert($request, 'Item definition deleted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/item_definitions'));
    }

    public function vouchers(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'vouchers/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/vouchers', $staff, [
            'pageName' => 'Vouchers',
            'vouchers' => $this->vouchersList(),
            'history' => $this->voucherHistory(null),
        ]);
    }

    public function editVoucher(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'vouchers/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $code = $this->rawQueryValue($request, 'code') ?? '';
        $hasCode = trim($code) !== '';
        $voucher = $hasCode ? $this->voucher($code) : null;

        if ($hasCode && $voucher === null) {
            $this->alert($request, 'Voucher does not exist', 'danger');

            return redirect($this->housekeepingUrl('/vouchers'));
        }

        if ($request->isMethod('post')) {
            $voucherCode = trim((string) $request->input('voucher_code', ''));

            if ($voucherCode === '') {
                $this->alert($request, 'Voucher code cannot be blank', 'danger');
            } else {
                $this->saveVoucher($voucherCode, $hasCode ? $code : null, $request);
                $this->alert($request, 'Voucher saved successfully', 'success');

                return redirect($this->housekeepingUrl('/vouchers/edit?code='.$voucherCode));
            }
        }

        return $this->render($template, 'housekeeping/voucher_edit', $staff, [
            'pageName' => $hasCode ? 'Edit Voucher' : 'Create Voucher',
            'voucher' => $voucher,
            'voucherItems' => $hasCode ? $this->voucherItems($code) : [],
            'history' => $hasCode ? $this->voucherHistory($code) : [],
        ]);
    }

    public function deleteVoucher(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'vouchers/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $code = $this->rawQueryValue($request, 'code');

        if ($code !== null) {
            DB::table('vouchers')->where('voucher_code', $code)->delete();
            DB::table('vouchers_items')->where('voucher_code', $code)->delete();
            $this->alert($request, 'Voucher deleted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/vouchers'));
    }

    public function wordfilter(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'wordfilter/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/wordfilter', $staff, [
            'pageName' => 'Wordfilter',
            'words' => $this->wordfilterList(),
        ]);
    }

    public function editWordfilter(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'wordfilter/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $word = $id > 0 ? $this->wordfilterEntry($id) : null;

        if ($id > 0 && $word === null) {
            $this->alert($request, 'Wordfilter entry does not exist', 'danger');

            return redirect($this->housekeepingUrl('/wordfilter'));
        }

        if ($request->isMethod('post')) {
            $value = trim((string) $request->input('word', ''));

            if ($value === '') {
                $this->alert($request, 'Word cannot be blank', 'danger');
            } else {
                $savedId = $this->saveWordfilterEntry($id, $value, $request);
                $this->alert($request, 'Wordfilter entry saved successfully', 'success');

                return redirect($this->housekeepingUrl('/wordfilter/edit?id='.$savedId));
            }
        }

        return $this->render($template, 'housekeeping/wordfilter_edit', $staff, [
            'pageName' => $id > 0 ? 'Edit Wordfilter Entry' : 'Create Wordfilter Entry',
            'word' => $word,
        ]);
    }

    public function deleteWordfilter(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'wordfilter/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('wordfilter')->where('id', $id)->delete();
            $this->alert($request, 'Wordfilter entry deleted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/wordfilter'));
    }

    public function recyclerRewards(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'recycler/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/recycler_rewards', $staff, [
            'pageName' => 'Recycler Rewards',
            'rewards' => $this->recyclerRewardsList(),
        ]);
    }

    public function editRecyclerReward(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'recycler/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $sprite = $this->rawQueryValue($request, 'sprite') ?? '';
        $hasSprite = trim($sprite) !== '';
        $reward = $hasSprite ? $this->recyclerReward($sprite) : null;

        if ($hasSprite && $reward === null) {
            $this->alert($request, 'Recycler reward does not exist', 'danger');

            return redirect($this->housekeepingUrl('/recycler_rewards'));
        }

        if ($request->isMethod('post')) {
            $rewardSprite = trim((string) $request->input('sprite', ''));

            if ($rewardSprite === '') {
                $this->alert($request, 'Sprite cannot be blank', 'danger');
            } else {
                $this->saveRecyclerReward($rewardSprite, $hasSprite ? $sprite : null, $request);
                $this->alert($request, 'Recycler reward saved successfully', 'success');

                return redirect($this->housekeepingUrl('/recycler_rewards/edit?sprite='.$rewardSprite));
            }
        }

        return $this->render($template, 'housekeeping/recycler_reward_edit', $staff, [
            'pageName' => $hasSprite ? 'Edit Recycler Reward' : 'Create Recycler Reward',
            'reward' => $reward,
        ]);
    }

    public function deleteRecyclerReward(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'recycler/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $sprite = $this->rawQueryValue($request, 'sprite');

        if ($sprite !== null) {
            DB::table('recycler_rewards')->where('sprite', $sprite)->delete();
            $this->alert($request, 'Recycler reward deleted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/recycler_rewards'));
    }

    public function roomCategories(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_categories/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/room_categories', $staff, [
            'pageName' => 'Room Categories',
            'categories' => $this->roomCategoriesList(),
        ]);
    }

    public function editRoomCategory(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_categories/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $category = $id > 0 ? $this->roomCategory($id) : null;

        if ($id > 0 && $category === null) {
            $this->alert($request, 'Room category does not exist', 'danger');

            return redirect($this->housekeepingUrl('/room_categories'));
        }

        if ($request->isMethod('post')) {
            $name = trim((string) $request->input('name', ''));

            if ($name === '') {
                $this->alert($request, 'Category name cannot be blank', 'danger');
            } else {
                $savedId = $this->saveRoomCategory($id, $name, $request);
                $this->alert($request, 'Room category saved successfully', 'success');

                return redirect($this->housekeepingUrl('/room_categories/edit?id='.$savedId));
            }
        }

        return $this->render($template, 'housekeeping/room_category_edit', $staff, [
            'pageName' => $id > 0 ? 'Edit Room Category' : 'Create Room Category',
            'category' => $category,
            'categories' => $this->roomCategoriesList(),
            'ranks' => $this->ranks(),
        ]);
    }

    public function deleteRoomCategory(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'room_categories/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('rooms_categories')->where('id', $id)->delete();
            $this->alert($request, 'Room category deleted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/room_categories'));
    }

    public function roomModels(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_models/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/room_models', $staff, [
            'pageName' => 'Room Models',
            'models' => $this->roomModelsList(),
        ]);
    }

    public function editRoomModel(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_models/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $model = $id > 0 ? $this->roomModel($id) : null;

        if ($id > 0 && $model === null) {
            $this->alert($request, 'Room model does not exist', 'danger');

            return redirect($this->housekeepingUrl('/room_models'));
        }

        if ($request->isMethod('post')) {
            $modelId = trim((string) $request->input('model_id', ''));
            $triggerClass = trim((string) $request->input('trigger_class', ''));
            $doorZ = filter_var($request->input('door_z'), FILTER_VALIDATE_FLOAT);

            if ($modelId === '') {
                $this->alert($request, 'Model ID cannot be blank', 'danger');
            } elseif ($doorZ === false) {
                $this->alert($request, 'Door Z must be a valid number', 'danger');
            } elseif (! in_array($triggerClass, $this->roomModelTriggers, true)) {
                $this->alert($request, 'Trigger class must match a known room model trigger', 'danger');
            } else {
                $savedId = $this->saveRoomModel($id, $modelId, (float) $doorZ, $triggerClass, $request);
                $this->alert($request, 'Room model saved successfully', 'success');

                return redirect($this->housekeepingUrl('/room_models/edit?id='.$savedId));
            }
        }

        return $this->render($template, 'housekeeping/room_model_edit', $staff, [
            'pageName' => $id > 0 ? 'Edit Room Model' : 'Create Room Model',
            'model' => $model,
            'triggers' => $this->roomModelTriggers,
        ]);
    }

    public function deleteRoomModel(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'room_models/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('rooms_models')->where('id', $id)->delete();
            $this->alert($request, 'Room model deleted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/room_models'));
    }

    private function requirePermission(Request $request, string $permission): User|RedirectResponse
    {
        $user = $this->currentHousekeepingUser($request);

        if ($user === null || ! (new HousekeepingManagerView)->hasPermission((int) $user->rank, $permission)) {
            return $this->redirectToHousekeeping();
        }

        return $user;
    }

    private function currentHousekeepingUser(Request $request): ?User
    {
        if (! $request->session()->get(self::SESSION_KEY, false)) {
            return null;
        }

        $userId = (int) $request->session()->get(self::USER_ID_KEY, 0);

        return $userId > 0 ? User::query()->find($userId) : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function render(LegacyTemplate $template, string $view, User $staff, array $context): Response
    {
        $html = $template->render($view, array_merge([
            'housekeepingManager' => new HousekeepingManagerView,
            'playerDetails' => new HousekeepingPlayerView($staff),
        ], $context));

        request()->session()->forget('alertMessage');

        return response($html);
    }

    private function redirectToHousekeeping(): RedirectResponse
    {
        return redirect($this->housekeepingUrl());
    }

    private function housekeepingUrl(string $suffix = ''): string
    {
        return '/'.trim((string) config('havana.housekeeping_path'), '/').$suffix;
    }

    private function alert(Request $request, string $message, string $colour): void
    {
        $request->session()->put('alertColour', $colour);
        $request->session()->put('alertMessage', $message);
    }

    private function integerQuery(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /** @return list<HousekeepingItemDefinitionView> */
    private function itemDefinitionsList(): array
    {
        return DB::table('items_definitions')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingItemDefinitionView => new HousekeepingItemDefinitionView($row))
            ->all();
    }

    private function itemDefinition(int $id): ?HousekeepingItemDefinitionView
    {
        $row = DB::table('items_definitions')->where('id', $id)->first();

        return $row !== null ? new HousekeepingItemDefinitionView($row) : null;
    }

    private function saveItemDefinition(int $id, string $sprite, float $topHeight, string $behaviour, Request $request): int
    {
        $payload = [
            'sprite' => $sprite,
            'name' => trim((string) $request->input('name', '')),
            'description' => trim((string) $request->input('description', '')),
            'sprite_id' => (int) $request->input('sprite_id', 0),
            'length' => (int) $request->input('length', 0),
            'width' => (int) $request->input('width', 0),
            'top_height' => $topHeight,
            'max_status' => trim((string) $request->input('max_status', '')),
            'behaviour' => $behaviour,
            'interactor' => trim((string) $request->input('interactor', 'default')),
            'is_tradable' => $request->boolean('is_tradable'),
            'is_recyclable' => $request->boolean('is_recyclable'),
            'drink_ids' => $this->normaliseCsv((string) $request->input('drink_ids', '')),
            'rental_time' => (int) $request->input('rental_time', 0),
            'allowed_rotations' => $this->normaliseCsv((string) $request->input('allowed_rotations', '')),
            'heights' => $this->normaliseCsv((string) $request->input('heights', '')),
        ];

        if ($id > 0) {
            DB::table('items_definitions')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('items_definitions')->insertGetId($payload);
    }

    private function normaliseCsv(string $value): string
    {
        return implode(',', array_values(array_filter(array_map(
            fn (string $part): string => trim($part),
            explode(',', $value)
        ), fn (string $part): bool => $part !== '')));
    }

    private function validateBehaviours(string $behaviour): ?string
    {
        if ($behaviour === '') {
            return null;
        }

        foreach (explode(',', $behaviour) as $entry) {
            if (! in_array($entry, $this->behaviours, true)) {
                return 'Unknown item behaviour: '.$entry;
            }
        }

        return null;
    }

    /** @return list<HousekeepingVoucherView> */
    private function vouchersList(): array
    {
        return DB::table('vouchers')
            ->select('voucher_code', 'credits', 'expiry_date', 'is_single_use', 'allow_new_users')
            ->orderBy('voucher_code')
            ->get()
            ->map(fn (object $row): HousekeepingVoucherView => new HousekeepingVoucherView($row))
            ->all();
    }

    private function voucher(string $code): ?HousekeepingVoucherView
    {
        $row = DB::table('vouchers')
            ->select('voucher_code', 'credits', 'expiry_date', 'is_single_use', 'allow_new_users')
            ->where('voucher_code', $code)
            ->first();

        return $row !== null ? new HousekeepingVoucherView($row) : null;
    }

    private function saveVoucher(string $voucherCode, ?string $originalVoucherCode, Request $request): void
    {
        if ($originalVoucherCode !== null && $originalVoucherCode !== $voucherCode) {
            DB::table('vouchers')->where('voucher_code', $originalVoucherCode)->delete();
            DB::table('vouchers_items')->where('voucher_code', $originalVoucherCode)->delete();
        }

        DB::table('vouchers')->updateOrInsert(
            ['voucher_code' => $voucherCode],
            [
                'credits' => (int) $request->input('credits', 0),
                'expiry_date' => trim((string) $request->input('expiry_date', '')) !== '' ? trim((string) $request->input('expiry_date')) : null,
                'is_single_use' => $request->boolean('is_single_use'),
                'allow_new_users' => $request->boolean('allow_new_users'),
            ]
        );

        DB::table('vouchers_items')->where('voucher_code', $voucherCode)->delete();

        foreach ($this->parseLines((string) $request->input('catalogue_sale_codes', '')) as $saleCode) {
            DB::table('vouchers_items')->insert([
                'voucher_code' => $voucherCode,
                'catalogue_sale_code' => $saleCode,
            ]);
        }
    }

    /** @return list<string> */
    private function parseLines(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $line): string => trim($line),
            preg_split('/\R|,/', $value) ?: []
        ), fn (string $line): bool => $line !== '')));
    }

    /** @return list<HousekeepingVoucherItemView> */
    private function voucherItems(string $code): array
    {
        return DB::table('vouchers_items')
            ->where('voucher_code', $code)
            ->orderBy('catalogue_sale_code')
            ->get()
            ->map(fn (object $row): HousekeepingVoucherItemView => new HousekeepingVoucherItemView($row))
            ->all();
    }

    /** @return list<HousekeepingVoucherHistoryView> */
    private function voucherHistory(?string $code): array
    {
        $query = DB::table('vouchers_history')
            ->select('voucher_code', 'user_id', 'used_at', 'credits_redeemed', 'items_redeemed')
            ->orderByDesc('used_at')
            ->limit(50);

        if ($code !== null && $code !== '') {
            $query->where('voucher_code', $code);
        }

        return $query
            ->get()
            ->map(fn (object $row): HousekeepingVoucherHistoryView => new HousekeepingVoucherHistoryView($row))
            ->all();
    }

    /** @return list<HousekeepingWordfilterView> */
    private function wordfilterList(): array
    {
        return DB::table('wordfilter')
            ->orderBy('word')
            ->get()
            ->map(fn (object $row): HousekeepingWordfilterView => new HousekeepingWordfilterView($row))
            ->all();
    }

    private function wordfilterEntry(int $id): ?HousekeepingWordfilterView
    {
        $row = DB::table('wordfilter')->where('id', $id)->first();

        return $row !== null ? new HousekeepingWordfilterView($row) : null;
    }

    private function saveWordfilterEntry(int $id, string $word, Request $request): int
    {
        $payload = [
            'word' => $word,
            'is_bannable' => $request->boolean('is_bannable'),
            'is_filterable' => $request->boolean('is_filterable'),
        ];

        if ($id > 0) {
            DB::table('wordfilter')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('wordfilter')->insertGetId($payload);
    }

    /** @return list<HousekeepingRecyclerRewardView> */
    private function recyclerRewardsList(): array
    {
        return DB::table('recycler_rewards')
            ->orderBy('order_id')
            ->orderBy('sprite')
            ->get()
            ->map(fn (object $row): HousekeepingRecyclerRewardView => new HousekeepingRecyclerRewardView($row))
            ->all();
    }

    private function recyclerReward(string $sprite): ?HousekeepingRecyclerRewardView
    {
        $row = DB::table('recycler_rewards')->where('sprite', $sprite)->first();

        return $row !== null ? new HousekeepingRecyclerRewardView($row) : null;
    }

    private function saveRecyclerReward(string $sprite, ?string $originalSprite, Request $request): void
    {
        if ($originalSprite !== null && $originalSprite !== $sprite) {
            DB::table('recycler_rewards')->where('sprite', $originalSprite)->delete();
        }

        DB::table('recycler_rewards')->updateOrInsert(
            ['sprite' => $sprite],
            [
                'order_id' => (int) $request->input('order_id', 0),
                'chance' => (int) $request->input('chance', 5),
            ]
        );
    }

    private function rawQueryValue(Request $request, string $key): ?string
    {
        $query = (string) $request->server('QUERY_STRING', '');

        if ($query === '') {
            return null;
        }

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if (urldecode($name) === $key) {
                return urldecode($value);
            }
        }

        return null;
    }

    /** @return list<HousekeepingRoomCategoryView> */
    private function roomCategoriesList(): array
    {
        return DB::table('rooms_categories')
            ->orderBy('parent_id')
            ->orderBy('order_id')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingRoomCategoryView => new HousekeepingRoomCategoryView($row))
            ->all();
    }

    private function roomCategory(int $id): ?HousekeepingRoomCategoryView
    {
        $row = DB::table('rooms_categories')->where('id', $id)->first();

        return $row !== null ? new HousekeepingRoomCategoryView($row) : null;
    }

    /** @return list<HousekeepingRank> */
    private function ranks(): array
    {
        return array_map(fn (int $rank): HousekeepingRank => new HousekeepingRank($rank), range(0, 8));
    }

    private function saveRoomCategory(int $id, string $name, Request $request): int
    {
        $payload = [
            'order_id' => (int) $request->input('order_id', 0),
            'parent_id' => (int) $request->input('parent_id', 0),
            'isnode' => $request->boolean('isnode'),
            'name' => $name,
            'public_spaces' => $request->boolean('public_spaces'),
            'allow_trading' => $request->boolean('allow_trading'),
            'minrole_access' => (int) $request->input('minrole_access', 1),
            'minrole_setflatcat' => (int) $request->input('minrole_setflatcat', 1),
            'club_only' => $request->boolean('club_only'),
            'is_top_priority' => $request->boolean('is_top_priority'),
        ];

        if ($id > 0) {
            DB::table('rooms_categories')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('rooms_categories')->insertGetId($payload);
    }

    /** @return list<HousekeepingRoomModelView> */
    private function roomModelsList(): array
    {
        return DB::table('rooms_models')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingRoomModelView => new HousekeepingRoomModelView($row))
            ->all();
    }

    private function roomModel(int $id): ?HousekeepingRoomModelView
    {
        $row = DB::table('rooms_models')->where('id', $id)->first();

        return $row !== null ? new HousekeepingRoomModelView($row) : null;
    }

    private function saveRoomModel(int $id, string $modelId, float $doorZ, string $triggerClass, Request $request): int
    {
        $payload = [
            'model_id' => $modelId,
            'model_name' => trim((string) $request->input('model_name', '')),
            'door_x' => (int) $request->input('door_x', 0),
            'door_y' => (int) $request->input('door_y', 0),
            'door_z' => $doorZ,
            'door_dir' => (int) $request->input('door_dir', 0),
            'heightmap' => trim((string) $request->input('heightmap', '')),
            'trigger_class' => $triggerClass,
        ];

        if ($id > 0) {
            DB::table('rooms_models')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('rooms_models')->insertGetId($payload);
    }
}
