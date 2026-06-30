<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyInventoryWidget;
use App\Support\LegacyStickerCategory;
use App\Support\LegacyStickerProduct;
use App\Support\LegacyUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomeInventoryController extends Controller
{
    public function inventory(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $widgets = $this->collapsedInventoryWidgets((int) $user->id, 1);
        $first = $widgets[0] ?? null;
        $json = $first
            ? '[["Inventory","Web Store"],["'.$first->getProduct()->getCssClass().'","'.$first->getProduct()->getData().'","'.$first->getProduct()->getName().'","Stickers",null,1]]'
            : '[["Inventory","Web Store"],["","","","Stickers",null,1]]';

        return response($template->render('homes/inventory/inventory', [
            'stickerCategories' => $this->categories((int) $user->rank, 1),
            'backgroundCategories' => $this->categories((int) $user->rank, 2),
            'emptyBoxes' => $this->emptyBoxes($this->inventoryCount((int) $user->id, 1)),
            'widgets' => $widgets,
            'playerDetails' => new LegacyUserData($user),
        ]))->header('X-JSON', $json);
    }

    public function inventoryItems(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $type = strtolower((string) $request->input('type', 'stickers'));
        $widgetMode = $type === 'widgets';

        if ($widgetMode) {
            $widgets = $this->widgetsForPlacement($request, (int) $user->id);

            return response($template->render('homes/inventory/inventory_items', [
                'widgets' => $widgets,
                'widgetMode' => true,
                'emptyBoxes' => [],
                'playerDetails' => new LegacyUserData($user),
            ]));
        }

        $typeId = $this->typeId($type);
        $widgets = $this->collapsedInventoryWidgets((int) $user->id, $typeId);

        return response($template->render('homes/inventory/inventory_items', [
            'widgets' => $widgets,
            'widgetMode' => false,
            'emptyBoxes' => $this->emptyBoxes($this->inventoryCount((int) $user->id, $typeId)),
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function inventoryPreview(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $widget = $this->findPreviewWidget($request, (int) $user->id);
        $json = '["","","","Sticker",null,1]';

        if ($widget) {
            $product = $widget->getProduct();
            $json = match ($product->getTypeId()) {
                1 => '["'.$product->getCssClass().'","'.$product->getData().'","'.$product->getName().'","Sticker",null,1]',
                3 => '["commodity_stickienote_pre",null,"Notes","WebCommodity",null,1]',
                4 => '["'.$product->getCssClass().'","b_'.$product->getData().'","'.$product->getName().'","Background",null,1]',
                2, 5 => '["'.$product->getCssClass().'",null,"","Widget","true",1]',
                default => $json,
            };
        }

        return response('')->header('X-JSON', $json);
    }

    public function placeSticker(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->editableGroupId($request, (int) $user->id);

        if ($groupId === null && ! $this->hasHomeEditSession($request, (int) $user->id)) {
            return response('');
        }

        $selectedStickerId = $this->integerInput($request, 'selectedStickerId');
        $row = $selectedStickerId !== null
            ? DB::table('cms_stickers')
                ->where('id', $selectedStickerId)
                ->where('user_id', (int) $user->id)
                ->where('is_placed', false)
                ->first()
            : null;

        if (! $row) {
            return response('');
        }

        $z = $this->zIndex((int) $request->input('zindex', 0));
        DB::table('cms_stickers')->where('id', (int) $row->id)->update([
            'x' => '20',
            'y' => '30',
            'z' => (string) $z,
            'group_id' => $groupId ?? 0,
            'is_placed' => true,
        ]);
        $widget = $this->widgetById((int) $row->id);

        return response($template->render($this->templateFor($widget), $this->templateContext($widget, $user, $request)))
            ->header('X-JSON', '["'.$widget->getId().'"]');
    }

    public function removeSticker(Request $request): Response|RedirectResponse
    {
        return $this->removePlaced($request, 'stickerId', true);
    }

    public function placeWidget(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->editableGroupId($request, (int) $user->id);

        if ($groupId === null && ! $this->hasHomeEditSession($request, (int) $user->id)) {
            return response('');
        }

        $widgetId = $this->integerInput($request, 'widgetId');
        $row = $widgetId !== null ? $this->placedWidgetRow($request, (int) $user->id, $widgetId, $groupId) : null;

        if (! $row) {
            return response('');
        }

        $z = $this->zIndex((int) $request->input('zindex', 0));
        DB::table('cms_stickers')->where('id', (int) $row->id)->update([
            'x' => '10',
            'y' => '10',
            'z' => (string) $z,
            'group_id' => $groupId ?? (int) $row->group_id,
            'is_placed' => true,
        ]);
        $widget = $this->widgetById((int) $row->id);

        return response($template->render($this->templateFor($widget), $this->templateContext($widget, $user, $request)))
            ->header('X-JSON', '["'.$widget->getId().'"]');
    }

    public function removeWidget(Request $request): Response|RedirectResponse
    {
        return $this->removePlaced($request, 'widgetId', false);
    }

    public function editWidget(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->editableGroupId($request, (int) $user->id);

        if ($groupId === null && ! $this->hasHomeEditSession($request, (int) $user->id)) {
            return response('');
        }

        $widgetId = $this->integerInput($request, 'widgetId');
        $row = $widgetId !== null ? $this->placedWidgetRow($request, (int) $user->id, $widgetId, $groupId) : null;

        if (! $row) {
            return response('');
        }

        $skin = $this->allowedSkin((int) $request->input('skinId', 1), $user);
        DB::table('cms_stickers')->where('id', (int) $row->id)->update(['skin_id' => $skin]);
        $widget = $this->widgetById((int) $row->id);
        $json = '{"id":"'.$widget->getId().'","cssClass":"w_skin_'.$widget->getSkin().'","type":"widget"}';

        return response($template->render($this->templateFor($widget), $this->templateContext($widget, $user, $request)))
            ->header('X-JSON', $json);
    }

    private function removePlaced(Request $request, string $input, bool $resetGroup): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->editableGroupId($request, (int) $user->id);

        if ($groupId === null && ! $this->hasHomeEditSession($request, (int) $user->id)) {
            return response('');
        }

        $stickerId = $this->integerInput($request, $input);
        $query = $stickerId !== null ? DB::table('cms_stickers')->where('id', $stickerId) : null;

        if (! $query) {
            return response('');
        }

        if ($groupId !== null) {
            $query->where('group_id', $groupId);
        } else {
            $query->where('user_id', (int) $user->id)->where('group_id', 0);
        }

        $row = $query->first();

        if (! $row) {
            return response('');
        }

        if (! $resetGroup && in_array($this->productFor((int) $row->sticker_id)->getData(), ['groupinfowidget', 'profilewidget'], true)) {
            return response('');
        }

        DB::table('cms_stickers')->where('id', (int) $row->id)->update([
            'x' => '0',
            'y' => '0',
            'z' => '0',
            'group_id' => $resetGroup ? 0 : (int) $row->group_id,
            'is_placed' => false,
        ]);

        return response('SUCCESS');
    }

    /** @return list<LegacyInventoryWidget> */
    private function collapsedInventoryWidgets(int $userId, int $typeId): array
    {
        $widgets = $this->inventoryRows($userId, $typeId)
            ->map(fn (object $row): LegacyInventoryWidget => $this->makeWidget($row))
            ->values();
        $collapsed = [];

        foreach ($widgets as $widget) {
            $key = $widget->getStickerId();
            if (isset($collapsed[$key])) {
                $collapsed[$key]->setAmount($collapsed[$key]->getAmount() + 1);
            } else {
                $collapsed[$key] = $widget;
            }
        }

        return array_values($collapsed);
    }

    private function inventoryCount(int $userId, int $typeId): int
    {
        return $this->inventoryRows($userId, $typeId)->count();
    }

    private function inventoryRows(int $userId, int $typeId): Collection
    {
        return DB::table('cms_stickers')
            ->join('cms_stickers_catalogue', 'cms_stickers_catalogue.id', '=', 'cms_stickers.sticker_id')
            ->where('cms_stickers.user_id', $userId)
            ->where('cms_stickers.group_id', 0)
            ->where('cms_stickers.is_placed', false)
            ->where('cms_stickers_catalogue.type', (string) $typeId)
            ->orderByDesc('cms_stickers.id')
            ->get(['cms_stickers.*']);
    }

    /** @return list<LegacyInventoryWidget> */
    private function widgetsForPlacement(Request $request, int $userId): array
    {
        $groupId = (int) $request->session()->get('groupEditSession', 0);
        $typeId = $groupId > 0 ? 5 : 2;
        $query = DB::table('cms_stickers')
            ->join('cms_stickers_catalogue', 'cms_stickers_catalogue.id', '=', 'cms_stickers.sticker_id')
            ->where('cms_stickers_catalogue.type', (string) $typeId);

        if ($groupId > 0) {
            $query->where('cms_stickers.group_id', $groupId);
        } else {
            $query->where('cms_stickers.user_id', $userId)
                ->where('cms_stickers.group_id', 0)
                ->where('cms_stickers_catalogue.data', '<>', 'profilewidget');
        }

        return $query->orderByDesc('cms_stickers.id')->get(['cms_stickers.*'])->map(fn (object $row): LegacyInventoryWidget => $this->makeWidget($row))->all();
    }

    private function findPreviewWidget(Request $request, int $userId): ?LegacyInventoryWidget
    {
        $itemId = $this->integerInput($request, 'itemId');

        if ($itemId === null) {
            return null;
        }

        $type = strtolower((string) $request->input('type', 'stickers'));

        if ($type === 'widgets') {
            $rows = $this->widgetsForPlacement($request, $userId);
            foreach ($rows as $widget) {
                if ($widget->getId() === $itemId) {
                    return $widget;
                }
            }

            return null;
        }

        $typeId = $this->typeId($type);
        $row = $this->inventoryRows($userId, $typeId)->firstWhere('id', $itemId);

        return $row ? $this->makeWidget($row) : null;
    }

    private function widgetById(int $widgetId): LegacyInventoryWidget
    {
        return $this->makeWidget(DB::table('cms_stickers')->where('id', $widgetId)->first());
    }

    private function makeWidget(object $row): LegacyInventoryWidget
    {
        return new LegacyInventoryWidget($row, $this->productFor((int) $row->sticker_id));
    }

    private function productFor(int $stickerId): LegacyStickerProduct
    {
        $row = Schema::hasTable('cms_stickers_catalogue')
            ? DB::table('cms_stickers_catalogue')->where('id', $stickerId)->first()
            : null;

        return LegacyStickerProduct::fromRow($row, $stickerId);
    }

    private function typeId(string $type): int
    {
        return match ($type) {
            'backgrounds' => 4,
            'notes' => 3,
            default => 1,
        };
    }

    /** @return list<LegacyStickerCategory> */
    private function categories(int $rank, int $type): array
    {
        return DB::table('cms_stickers_categories')
            ->where('min_rank', '<=', $rank)
            ->where('category_type', $type)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (object $row): LegacyStickerCategory => new LegacyStickerCategory((int) $row->id, (string) $row->name))
            ->all();
    }

    /** @return list<null> */
    private function emptyBoxes(int $count): array
    {
        $empty = $count > 20 ? (int) (ceil($count / 4.0) * 4) : 20 - $count;

        return array_fill(0, max(0, $empty), null);
    }

    private function placedWidgetRow(Request $request, int $userId, int $widgetId, ?int $groupId): ?object
    {
        $query = DB::table('cms_stickers')->where('id', $widgetId);

        if ($groupId !== null) {
            $query->where('group_id', $groupId);
        } else {
            $query->where('user_id', $userId)->where('group_id', 0);
        }

        return $query->first();
    }

    private function templateFor(LegacyInventoryWidget $widget): string
    {
        return match ($widget->getProduct()->getData()) {
            'stickienote' => 'homes/widget/note',
            'ratingwidget' => 'homes/widget/rating_widget',
            default => $widget->getProduct()->getTypeId() === 1 ? 'homes/widget/sticker' : 'homes/widget/rating_widget',
        };
    }

    /** @return array<string, mixed> */
    private function templateContext(LegacyInventoryWidget $widget, User $user, Request $request): array
    {
        return [
            'sticker' => $widget,
            'editMode' => true,
            'playerDetails' => new LegacyUserData($user),
            'user' => $user,
        ];
    }

    private function zIndex(int $z): int
    {
        return $z < 0 || $z > 100 ? 0 : $z;
    }

    private function allowedSkin(int $skinId, User $user): int
    {
        if (($skinId === 7 || $skinId === 8) && (int) $user->club_expiration <= time()) {
            return 1;
        }

        if ($skinId === 9 && (int) $user->rank < 5) {
            return 1;
        }

        return $skinId;
    }

    private function integerInput(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function editableGroupId(Request $request, int $userId): ?int
    {
        $groupId = (int) $request->session()->get('groupEditSession', 0);

        if ($groupId <= 0) {
            return null;
        }

        return DB::table('groups_edit_sessions')
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->where('expire', '>', time())
            ->exists() ? $groupId : null;
    }

    private function hasHomeEditSession(Request $request, int $userId): bool
    {
        return (int) $request->session()->get('homeEditSession', 0) === $userId
            && DB::table('homes_edit_sessions')->where('user_id', $userId)->where('expire', '>', time())->exists();
    }

    private function currentUser(Request $request): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return User::query()->find((int) $user->id);
        }

        $userId = (int) $request->session()->get('user.id', 0);

        if ($userId > 0 && $request->session()->get('authenticated')) {
            return User::query()->find($userId);
        }

        return null;
    }
}
