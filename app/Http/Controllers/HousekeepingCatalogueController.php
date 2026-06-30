<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingCatalogueCollectableView;
use App\Support\HousekeepingCatalogueItemView;
use App\Support\HousekeepingCataloguePackageView;
use App\Support\HousekeepingCataloguePageView;
use App\Support\HousekeepingCatalogueSaleBadgeView;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use App\Support\HousekeepingRank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HousekeepingCatalogueController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function frontpage(Request $request, LegacyTemplate $template, HavanaConfig $config): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/edit_frontpage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            if (trim((string) $request->input('header', '')) === '') {
                $this->alert($request, 'Header cannot be blank', 'danger');
            } elseif (trim((string) $request->input('subtext', '')) === '') {
                $this->alert($request, 'The subtext cannot be blank', 'danger');
            } else {
                foreach ([
                    'catalogue.frontpage.input.1' => (string) $request->input('image', ''),
                    'catalogue.frontpage.input.2' => (string) $request->input('header', ''),
                    'catalogue.frontpage.input.3' => (string) $request->input('subtext', ''),
                    'catalogue.frontpage.input.4' => (string) $request->input('link', ''),
                ] as $setting => $value) {
                    DB::table('settings')->updateOrInsert(['setting' => $setting], ['value' => $value]);
                }

                $config->reload();
                $this->alert($request, 'The frontpage has been successfully saved', 'success');
            }
        }

        return $this->render($template, 'housekeeping/catalogue_frontpage', $staff, [
            'pageName' => 'Edit Catalogue Frontpage',
            'images' => $this->topStoryImages(),
            'frontpageText1' => $config->string('catalogue.frontpage.input.1', 'attention_topstory.png'),
            'frontpageText2' => $config->string('catalogue.frontpage.input.2', ''),
            'frontpageText3' => $config->string('catalogue.frontpage.input.3', ''),
            'frontpageText4' => $config->string('catalogue.frontpage.input.4', ''),
        ]);
    }

    public function pages(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/catalogue_pages', $staff, [
            'pageName' => 'Catalogue Pages',
            'pages' => $this->pagesList(),
        ]);
    }

    public function editPage(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $page = $id > 0 ? $this->page($id) : null;

        if ($id > 0 && $page === null) {
            $this->alert($request, 'Catalogue page does not exist', 'danger');

            return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/pages');
        }

        if ($request->isMethod('post')) {
            $name = trim((string) $request->input('name', ''));
            $images = $this->normaliseJsonList((string) $request->input('images', '[]'));
            $texts = $this->normaliseJsonList((string) $request->input('texts', '[]'));

            if ($name === '') {
                $this->alert($request, 'Page name cannot be blank', 'danger');
            } elseif ($images === null || $texts === null) {
                $this->alert($request, 'Images and texts must be valid JSON arrays', 'danger');
            } else {
                $savedId = $this->savePage($id, $name, $images, $texts, $request);
                $this->alert($request, 'Catalogue page saved successfully', 'success');

                return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/pages/edit?id='.$savedId);
            }
        }

        return $this->render($template, 'housekeeping/catalogue_page_edit', $staff, [
            'pageName' => $id > 0 ? 'Edit Catalogue Page' : 'Create Catalogue Page',
            'page' => $page,
            'pages' => $this->pagesList(),
            'ranks' => $this->ranks(),
        ]);
    }

    public function deletePage(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('catalogue_pages')->where('id', $id)->delete();
            $this->alert($request, 'Catalogue page deleted successfully', 'success');
        }

        return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/pages');
    }

    public function items(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/catalogue_items', $staff, [
            'pageName' => 'Catalogue Items',
            'items' => $this->itemsList(),
        ]);
    }

    public function editItem(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $item = $id > 0 ? $this->item($id) : null;

        if ($id > 0 && $item === null) {
            $this->alert($request, 'Catalogue item does not exist', 'danger');

            return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/items');
        }

        if ($request->isMethod('post')) {
            $saleCode = trim((string) $request->input('sale_code', ''));
            $pageId = trim((string) $request->input('page_id', ''));

            if ($saleCode === '') {
                $this->alert($request, 'Sale code cannot be blank', 'danger');
            } elseif (! preg_match('/^[0-9,]+$/', $pageId)) {
                $this->alert($request, 'Page assignment must be a comma-separated list of page IDs', 'danger');
            } else {
                $savedId = $this->saveItem($id, $saleCode, $pageId, $request);
                $this->alert($request, 'Catalogue item saved successfully', 'success');

                return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/items/edit?id='.$savedId);
            }
        }

        return $this->render($template, 'housekeeping/catalogue_item_edit', $staff, [
            'pageName' => $id > 0 ? 'Edit Catalogue Item' : 'Create Catalogue Item',
            'item' => $item,
            'pages' => $this->pagesList(),
        ]);
    }

    public function deleteItem(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('catalogue_items')->where('id', $id)->delete();
            $this->alert($request, 'Catalogue item deleted successfully', 'success');
        }

        return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/items');
    }

    public function packages(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/catalogue_packages', $staff, [
            'pageName' => 'Catalogue Packages',
            'packages' => $this->packagesList(),
        ]);
    }

    public function editPackage(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $cataloguePackage = $id > 0 ? $this->package($id) : null;

        if ($id > 0 && $cataloguePackage === null) {
            $this->alert($request, 'Catalogue package row does not exist', 'danger');

            return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/packages');
        }

        if ($request->isMethod('post')) {
            $saleCode = trim((string) $request->input('salecode', ''));

            if ($saleCode === '') {
                $this->alert($request, 'Sale code cannot be blank', 'danger');
            } else {
                $savedId = $this->savePackage($id, $saleCode, $request);
                $this->alert($request, 'Catalogue package row saved successfully', 'success');

                return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/packages/edit?id='.$savedId);
            }
        }

        return $this->render($template, 'housekeeping/catalogue_package_edit', $staff, [
            'pageName' => $id > 0 ? 'Edit Catalogue Package Row' : 'Create Catalogue Package Row',
            'cataloguePackage' => $cataloguePackage,
        ]);
    }

    public function deletePackage(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('catalogue_packages')->where('id', $id)->delete();
            $this->alert($request, 'Catalogue package row deleted successfully', 'success');
        }

        return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/packages');
    }

    public function saleBadges(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            $saleCode = trim((string) $request->input('sale_code', ''));
            $badgeCode = trim((string) $request->input('badge_code', ''));

            if ($saleCode === '' || $badgeCode === '') {
                $this->alert($request, 'Sale code and badge code are required', 'danger');
            } else {
                DB::table('catalogue_sale_badges')->insert([
                    'sale_code' => $saleCode,
                    'badge_code' => $badgeCode,
                ]);
                $this->alert($request, 'Catalogue badge reward saved successfully', 'success');

                return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/sale_badges');
            }
        }

        return $this->render($template, 'housekeeping/catalogue_sale_badges', $staff, [
            'pageName' => 'Catalogue Sale Badges',
            'saleBadges' => $this->saleBadgesList(),
        ]);
    }

    public function deleteSaleBadge(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $saleCode = $this->rawQueryValue($request, 'sale_code');
        $badgeCode = $this->rawQueryValue($request, 'badge_code');

        if ($saleCode !== null && $badgeCode !== null) {
            DB::table('catalogue_sale_badges')
                ->where('sale_code', $saleCode)
                ->where('badge_code', $badgeCode)
                ->delete();
            $this->alert($request, 'Catalogue badge reward deleted successfully', 'success');
        }

        return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/sale_badges');
    }

    public function collectables(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/catalogue_collectables', $staff, [
            'pageName' => 'Catalogue Collectables',
            'collectables' => $this->collectablesList(),
        ]);
    }

    public function editCollectable(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $collectable = $id > 0 ? $this->collectable($id) : null;

        if ($id > 0 && $collectable === null) {
            $this->alert($request, 'Collectable cycle does not exist', 'danger');

            return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/collectables');
        }

        if ($request->isMethod('post')) {
            $classNames = trim((string) $request->input('class_names', ''));

            if ($classNames === '') {
                $this->alert($request, 'Class names cannot be blank', 'danger');
            } else {
                $savedId = $this->saveCollectable($id, $classNames, $request);
                $this->alert($request, 'Collectable cycle saved successfully', 'success');

                return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/collectables/edit?id='.$savedId);
            }
        }

        return $this->render($template, 'housekeeping/catalogue_collectable_edit', $staff, [
            'pageName' => $id > 0 ? 'Edit Catalogue Collectable' : 'Create Catalogue Collectable',
            'collectable' => $collectable,
        ]);
    }

    public function deleteCollectable(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'catalogue/manage');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('catalogue_collectables')->where('store_page', $id)->delete();
            $this->alert($request, 'Collectable cycle deleted successfully', 'success');
        }

        return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/catalogue/collectables');
    }

    private function requirePermission(Request $request, string $permission): User|RedirectResponse
    {
        $user = $this->currentHousekeepingUser($request);

        if ($user === null || ! (new HousekeepingManagerView)->hasPermission((int) $user->rank, $permission)) {
            return redirect('/'.trim((string) config('havana.housekeeping_path'), '/'));
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

    private function alert(Request $request, string $message, string $colour): void
    {
        $request->session()->put('alertColour', $colour);
        $request->session()->put('alertMessage', $message);
    }

    /** @return list<HousekeepingCataloguePageView> */
    private function pagesList(): array
    {
        return DB::table('catalogue_pages')
            ->orderBy('parent_id')
            ->orderBy('order_id')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingCataloguePageView => new HousekeepingCataloguePageView($row))
            ->all();
    }

    private function page(int $id): ?HousekeepingCataloguePageView
    {
        $row = DB::table('catalogue_pages')->where('id', $id)->first();

        return $row ? new HousekeepingCataloguePageView($row) : null;
    }

    /** @return list<HousekeepingCatalogueItemView> */
    private function itemsList(): array
    {
        return DB::table('catalogue_items')
            ->orderBy('page_id')
            ->orderBy('order_id')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingCatalogueItemView => new HousekeepingCatalogueItemView($row))
            ->all();
    }

    private function item(int $id): ?HousekeepingCatalogueItemView
    {
        $row = DB::table('catalogue_items')->where('id', $id)->first();

        return $row ? new HousekeepingCatalogueItemView($row) : null;
    }

    /** @return list<HousekeepingCataloguePackageView> */
    private function packagesList(): array
    {
        return DB::table('catalogue_packages')
            ->orderBy('salecode')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingCataloguePackageView => new HousekeepingCataloguePackageView($row))
            ->all();
    }

    private function package(int $id): ?HousekeepingCataloguePackageView
    {
        $row = DB::table('catalogue_packages')->where('id', $id)->first();

        return $row ? new HousekeepingCataloguePackageView($row) : null;
    }

    /** @return list<HousekeepingCatalogueSaleBadgeView> */
    private function saleBadgesList(): array
    {
        return DB::table('catalogue_sale_badges')
            ->orderBy('sale_code')
            ->orderBy('badge_code')
            ->get()
            ->map(fn (object $row): HousekeepingCatalogueSaleBadgeView => new HousekeepingCatalogueSaleBadgeView($row))
            ->all();
    }

    /** @return list<HousekeepingCatalogueCollectableView> */
    private function collectablesList(): array
    {
        return DB::table('catalogue_collectables')
            ->orderBy('store_page')
            ->get()
            ->map(fn (object $row): HousekeepingCatalogueCollectableView => new HousekeepingCatalogueCollectableView($row))
            ->all();
    }

    private function collectable(int $id): ?HousekeepingCatalogueCollectableView
    {
        $row = DB::table('catalogue_collectables')->where('store_page', $id)->first();

        return $row ? new HousekeepingCatalogueCollectableView($row) : null;
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

    /** @return list<HousekeepingRank> */
    private function ranks(): array
    {
        return array_map(fn (int $rank): HousekeepingRank => new HousekeepingRank($rank), range(0, 8));
    }

    private function savePage(int $id, string $name, string $images, string $texts, Request $request): int
    {
        $payload = [
            'old_id' => (int) $request->input('old_id', 0),
            'parent_id' => (int) $request->input('parent_id', -1),
            'order_id' => (int) $request->input('order_id', 1),
            'min_role' => (int) $request->input('min_role', 1),
            'is_navigatable' => $request->has('is_navigatable'),
            'is_club_only' => $request->has('is_club_only'),
            'name' => $name,
            'icon' => (int) $request->input('icon', 0),
            'colour' => (int) $request->input('colour', 0),
            'layout' => trim((string) $request->input('layout', '')),
            'images' => $images,
            'texts' => $texts,
            'seasonal_start' => trim((string) $request->input('seasonal_start', '')) ?: null,
            'seasonal_length' => (int) $request->input('seasonal_length', 0),
        ];

        if ($id > 0) {
            DB::table('catalogue_pages')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('catalogue_pages')->insertGetId($payload);
    }

    private function saveItem(int $id, string $saleCode, string $pageId, Request $request): int
    {
        $payload = [
            'sale_code' => $saleCode,
            'page_id' => $pageId,
            'order_id' => (int) $request->input('order_id', 0),
            'price_coins' => (int) $request->input('price_coins', 3),
            'price_pixels' => (int) $request->input('price_pixels', 0),
            'seasonal_coins' => (int) $request->input('seasonal_coins', 0),
            'seasonal_pixels' => (int) $request->input('seasonal_pixels', 0),
            'hidden' => $request->has('hidden'),
            'amount' => (int) $request->input('amount', 1),
            'definition_id' => (int) $request->input('definition_id', 0),
            'item_specialspriteid' => trim((string) $request->input('item_specialspriteid', '')),
            'is_package' => $request->has('is_package'),
            'active_at' => trim((string) $request->input('active_at', '')) ?: null,
        ];

        if ($id > 0) {
            DB::table('catalogue_items')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('catalogue_items')->insertGetId($payload);
    }

    private function savePackage(int $id, string $saleCode, Request $request): int
    {
        $payload = [
            'salecode' => $saleCode,
            'definition_id' => (int) $request->input('definition_id', 0),
            'special_sprite_id' => (string) $request->input('special_sprite_id', ''),
            'amount' => (int) $request->input('amount', 1),
        ];

        if ($id > 0) {
            DB::table('catalogue_packages')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('catalogue_packages')->insertGetId($payload);
    }

    private function saveCollectable(int $id, string $classNames, Request $request): int
    {
        $payload = [
            'store_page' => (int) $request->input('store_page', 0),
            'admin_page' => (int) $request->input('admin_page', 0),
            'expiry' => (int) $request->input('expiry', -1),
            'lifetime' => (int) $request->input('lifetime', 2678400),
            'current_position' => (int) $request->input('current_position', 0),
            'class_names' => $classNames,
        ];

        if ($id > 0) {
            DB::table('catalogue_collectables')->where('store_page', $id)->update($payload);

            return $id;
        }

        DB::table('catalogue_collectables')->insert($payload);

        return $payload['store_page'];
    }

    private function normaliseJsonList(string $value): ?string
    {
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return null;
        }

        return json_encode(array_values($decoded), JSON_UNESCAPED_SLASHES);
    }

    /** @return list<string> */
    private function topStoryImages(): array
    {
        $path = rtrim((string) config('havana.public_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'c_images'.DIRECTORY_SEPARATOR.'Top_Story_Images';

        if (is_dir($path)) {
            $images = collect(scandir($path) ?: [])
                ->filter(fn (string $file): bool => ! str_starts_with($file, '.') && is_file($path.DIRECTORY_SEPARATOR.$file))
                ->values()
                ->all();

            if ($images !== []) {
                return $images;
            }
        }

        return ['attention_topstory.png'];
    }
}
