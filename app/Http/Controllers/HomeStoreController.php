<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyStickerCategory;
use App\Support\LegacyStickerProduct;
use App\Support\LegacyUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeStoreController extends Controller
{
    public function main(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $stickerCategories = $this->categories((int) $user->rank, 1);
        $backgroundCategories = $this->categories((int) $user->rank, 2);
        $firstStickerCategory = $stickerCategories[0] ?? null;
        $firstBackgroundCategory = $backgroundCategories[0] ?? null;
        $categoryId = $firstStickerCategory?->getId() ?? ($firstBackgroundCategory?->getId() ?? 0);
        $products = $categoryId > 0 ? $this->productsByCategory($categoryId) : [];
        $product = $products[0] ?? LegacyStickerProduct::fromRow((object) [
            'id' => 0,
            'name' => '',
            'description' => '',
            'type' => 1,
            'data' => '',
            'price' => 0,
            'amount' => 0,
            'category_id' => 0,
            'widget_type' => 0,
        ], 0);
        $json = $product->getId() > 0
            ? '[["Inventory","Web Store"],[{"itemCount":'.$product->getAmount().',"previewCssClass":"'.$product->getCssClass().'","titleKey":""}]]'
            : '[["Inventory","Web Store"],[{"itemCount":0,"titleKey":""}]]';

        return response($template->render('homes/store/main', [
            'stickerCategories' => $stickerCategories,
            'backgroundCategories' => $backgroundCategories,
            'products' => $products,
            'product' => $product,
            'emptyBoxes' => $this->emptyBoxes(count($products), 5),
            'playerDetails' => new LegacyUserData($user),
        ]))->header('X-JSON', $json);
    }

    public function items(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        $categoryId = $this->integerInput($request, 'subCategoryId');

        if ($categoryId === null || ! DB::table('cms_stickers_categories')->where('id', $categoryId)->exists()) {
            return response('', 404);
        }

        $products = $this->productsByCategory($categoryId);

        return response($template->render('homes/store/items', [
            'products' => $products,
            'emptyProducts' => count($products) > 20 ? (int) (ceil(count($products) / 5.0) * 5) : 20 - count($products),
        ]));
    }

    public function preview(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $productId = $this->integerInput($request, 'productId');
        $product = $productId !== null ? $this->product($productId) : null;

        if (! $product) {
            return response('', 404);
        }

        $response = response($template->render('homes/store/preview', [
            'product' => $product,
            'playerDetails' => new LegacyUserData($user),
        ]));

        if (in_array($product->getTypeId(), [1, 3], true)) {
            return $response->header('X-JSON', '[{"itemCount":'.$product->getAmount().',"previewCssClass":"'.$product->getCssClass().'","titleKey":"'.$product->getName().'"}]');
        }

        if ($product->getTypeId() === 4) {
            return $response->header('X-JSON', '[{"bgCssClass":"b_'.$product->getData().'","itemCount":'.$product->getAmount().',"previewCssClass":"'.$product->getCssClass().'","titleKey":"'.$product->getName().'"}]');
        }

        return $response;
    }

    public function purchaseConfirm(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $productId = $this->integerInput($request, 'productId');
        $product = $productId !== null ? $this->product($productId) : null;

        if (! $product) {
            return response('');
        }

        return response($template->render('homes/store/purchase_confirm', [
            'product' => $product,
            'noCredits' => (int) $user->credits < $product->getPrice(),
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function backgroundWarning(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('homes/store/background_warning'));
    }

    public function purchaseStickers(Request $request): Response|RedirectResponse
    {
        return $this->purchase($request, 1);
    }

    public function purchaseBackgrounds(Request $request): Response|RedirectResponse
    {
        return $this->purchase($request, 4);
    }

    public function purchaseStickieNotes(Request $request): Response|RedirectResponse
    {
        return $this->purchase($request, 3);
    }

    private function purchase(Request $request, int $expectedType): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $selectedId = $this->integerInput($request, 'selectedId');
        $product = $selectedId !== null ? $this->product($selectedId) : null;

        if (! $product || $product->getTypeId() !== $expectedType) {
            return response('', 404);
        }

        if ((int) $user->credits < $product->getPrice()) {
            return response('');
        }

        for ($i = 0; $i < $product->getAmount(); $i++) {
            DB::table('cms_stickers')->insert([
                'user_id' => (int) $user->id,
                'x' => '0',
                'y' => '0',
                'z' => '0',
                'sticker_id' => $product->getId(),
                'skin_id' => 0,
                'group_id' => 0,
                'text' => '',
                'is_placed' => false,
                'extra_data' => '',
            ]);
        }

        DB::table('users')->where('id', (int) $user->id)->decrement('credits', $product->getPrice());

        return response('OK');
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

    /** @return list<LegacyStickerProduct> */
    private function productsByCategory(int $categoryId): array
    {
        return DB::table('cms_stickers_catalogue')
            ->where('category_id', $categoryId)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): LegacyStickerProduct => LegacyStickerProduct::fromRow($row, (int) $row->id))
            ->all();
    }

    private function product(int $productId): ?LegacyStickerProduct
    {
        $row = DB::table('cms_stickers_catalogue')->where('id', $productId)->first();

        return $row ? LegacyStickerProduct::fromRow($row, (int) $row->id) : null;
    }

    /** @return list<null> */
    private function emptyBoxes(int $count, int $columns): array
    {
        $empty = $count > 20 ? (int) (ceil($count / (float) $columns) * $columns) : 20 - $count;

        return array_fill(0, max(0, $empty), null);
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
