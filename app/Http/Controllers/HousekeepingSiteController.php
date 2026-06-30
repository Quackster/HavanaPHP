<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use App\Support\LegacyConfigEntry;
use App\Support\LegacyNewsArticle;
use App\Support\LegacyNewsCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HousekeepingSiteController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function configurations(Request $request, LegacyTemplate $template, HavanaConfig $config): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'configuration');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            foreach ($request->except('_token') as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['setting' => (string) $key],
                    ['value' => (string) $value]
                );
            }

            $config->reload();
            $this->alert($request, 'All configuration values have been saved successfully! It will take effect within 30 seconds.', 'success');
        }

        return $this->render($template, 'housekeeping/configurations', $staff, [
            'pageName' => 'Configurations',
            'configs' => $this->configs(),
        ]);
    }

    public function articles(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'articles/create');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        return $this->render($template, 'housekeeping/articles', $staff, [
            'pageName' => 'View News',
            'articles' => $this->articlesList(),
        ]);
    }

    public function createArticle(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'articles/create');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            $articleId = DB::table('site_articles')->insertGetId($this->articlePayload($request, $staff));
            $this->syncArticleCategories($articleId, $request->input('categories', $request->input('categories', [])));
            $this->alert($request, 'The submission of the news article was successful', 'success');

            return redirect('/'.trim((string) config('havana.housekeeping_path'), '/').'/articles');
        }

        $images = $this->topStoryImages();

        return $this->render($template, 'housekeeping/articles_create', $staff, [
            'pageName' => 'Create News',
            'images' => $images,
            'randomImage' => $images[0] ?? '',
            'currentDate' => now()->format('Y-m-d\TH:i'),
            'categories' => $this->categories(),
        ]);
    }

    public function editArticle(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'articles/edit_own');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $article = $this->article($id);

        if (! $article) {
            $this->alert($request, $id > 0 ? 'The article does not exist' : 'There was no article selected to edit', 'danger');

            return $this->render($template, 'housekeeping/articles_edit', $staff, [
                'pageName' => 'Edit News',
                'images' => $this->topStoryImages(),
                'categories' => $this->categories(),
            ]);
        }

        if ($article->getAuthorId() !== (int) $staff->id && ! (new HousekeepingManagerView)->hasPermission((int) $staff->rank, 'articles/edit_any')) {
            return $this->redirectToHousekeeping();
        }

        if ($request->isMethod('post')) {
            DB::table('site_articles')->where('id', $article->getId())->update($this->articlePayload($request, $staff, false));
            $this->syncArticleCategories($article->getId(), $request->input('categories', []));
            $this->alert($request, 'The article was successfully saved', 'success');
            $article = $this->article($article->getId());
        }

        return $this->render($template, 'housekeeping/articles_edit', $staff, [
            'pageName' => 'Edit News',
            'images' => $this->topStoryImages(),
            'currentDate' => $article?->getDateForInput() ?? now()->format('Y-m-d\TH:i'),
            'article' => $article,
            'categories' => $this->categories(),
        ]);
    }

    public function deleteArticle(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'articles/delete_own');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id') ?? 0;
        $article = $this->article($id);

        if (! $article) {
            $this->alert($request, $id > 0 ? 'The article does not exist' : 'There was no article selected to delete', 'danger');
        } elseif ($article->getAuthorId() !== (int) $staff->id && ! (new HousekeepingManagerView)->hasPermission((int) $staff->rank, 'articles/delete_any')) {
            return $this->redirectToHousekeeping();
        } else {
            DB::table('site_articles')->where('id', $article->getId())->delete();
            $this->alert($request, 'Successfully deleted the article', 'success');
        }

        return $this->render($template, 'housekeeping/articles', $staff, [
            'pageName' => 'Delete News',
            'articles' => $this->articlesList(),
        ]);
    }

    public function previewNewsArticle(Request $request): Response
    {
        $body = (string) $request->input('body', '');

        if ($body === '') {
            return response('');
        }

        return response($this->formatNewsStory($body));
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
        return redirect('/'.trim((string) config('havana.housekeeping_path'), '/'));
    }

    private function alert(Request $request, string $message, string $colour): void
    {
        $request->session()->put('alertColour', $colour);
        $request->session()->put('alertMessage', $message);
    }

    /** @return list<LegacyConfigEntry> */
    private function configs(): array
    {
        $defaults = config('havana.settings_defaults', []);
        $settings = Schema::hasTable('settings')
            ? DB::table('settings')->pluck('value', 'setting')->all()
            : [];
        $merged = array_merge($defaults, $settings);
        ksort($merged);

        return collect($merged)
            ->map(fn ($value, $key): LegacyConfigEntry => new LegacyConfigEntry((string) $key, (string) $value))
            ->values()
            ->all();
    }

    /** @return list<LegacyNewsArticle> */
    private function articlesList(): array
    {
        return DB::table('site_articles')
            ->leftJoin('users', 'site_articles.author_id', '=', 'users.id')
            ->orderByDesc('site_articles.created_at')
            ->limit(250)
            ->get(['site_articles.*', 'users.username as author_name'])
            ->map(fn (object $row): LegacyNewsArticle => $this->articleFromRow($row))
            ->all();
    }

    private function article(int $id): ?LegacyNewsArticle
    {
        if ($id <= 0) {
            return null;
        }

        $row = DB::table('site_articles')
            ->leftJoin('users', 'site_articles.author_id', '=', 'users.id')
            ->where('site_articles.id', $id)
            ->first(['site_articles.*', 'users.username as author_name']);

        return $row ? $this->articleFromRow($row) : null;
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

    private function articleFromRow(object $row): LegacyNewsArticle
    {
        $categories = $this->articleCategories((int) $row->id);

        return new LegacyNewsArticle(
            (int) $row->id,
            (string) $row->title,
            (int) $row->author_id,
            (string) ($row->author_override ?: ($row->author_name ?? 'Hotel Staff')),
            (string) $row->short_story,
            (string) $row->full_story,
            Carbon::parse($row->created_at),
            (string) $row->topstory,
            (string) $row->topstory_override,
            (string) $row->article_image,
            (bool) $row->is_published,
            $categories,
            (bool) $row->is_future_published,
            (int) $row->views,
        );
    }

    /** @return list<LegacyNewsCategory> */
    private function articleCategories(int $articleId): array
    {
        return DB::table('site_articles_categories')
            ->join('article_categories', 'article_categories.id', '=', 'site_articles_categories.category_id')
            ->where('site_articles_categories.article_id', $articleId)
            ->get(['article_categories.id', 'article_categories.label', 'article_categories.category_index'])
            ->map(fn (object $row): LegacyNewsCategory => new LegacyNewsCategory((int) $row->id, (string) $row->label, (string) $row->category_index))
            ->all();
    }

    /** @return list<LegacyNewsCategory> */
    private function categories(): array
    {
        return DB::table('article_categories')
            ->orderBy('label')
            ->get(['id', 'label', 'category_index'])
            ->map(fn (object $row): LegacyNewsCategory => new LegacyNewsCategory((int) $row->id, (string) $row->label, (string) $row->category_index))
            ->all();
    }

    /** @return array<string, mixed> */
    private function articlePayload(Request $request, User $staff, bool $includeAuthor = true): array
    {
        $createdAt = Carbon::createFromFormat('Y-m-d\TH:i', (string) $request->input('datePublished', now()->format('Y-m-d\TH:i'))) ?: now();

        $payload = [
            'title' => (string) $request->input('title', ''),
            'short_story' => (string) $request->input('shortstory', ''),
            'full_story' => (string) $request->input('fullstory', ''),
            'topstory' => (string) $request->input('topstory', 'attention_topstory.png'),
            'topstory_override' => (string) $request->input('topstoryOverride', ''),
            'author_override' => (string) $request->input('authorOverride', ''),
            'article_image' => (string) $request->input('articleimage', ''),
            'is_published' => $request->input('published') === 'true',
            'is_future_published' => $request->input('futurePublished') === 'true',
            'created_at' => $createdAt,
        ];

        if ($includeAuthor) {
            $payload['author_id'] = (int) $staff->id;
        }

        return $payload;
    }

    private function syncArticleCategories(int $articleId, mixed $categoryIndexes): void
    {
        $indexes = collect(is_array($categoryIndexes) ? $categoryIndexes : [$categoryIndexes])
            ->map(fn ($index): string => strtolower((string) $index))
            ->filter(fn (string $index): bool => $index !== '')
            ->values()
            ->all();

        $categoryIds = DB::table('article_categories')
            ->whereIn(DB::raw('LOWER(category_index)'), $indexes)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        DB::table('site_articles_categories')->where('article_id', $articleId)->delete();

        foreach ($categoryIds as $categoryId) {
            DB::table('site_articles_categories')->insert([
                'article_id' => $articleId,
                'category_id' => $categoryId,
            ]);
        }
    }

    /** @return list<string> */
    private function topStoryImages(): array
    {
        $paths = [
            rtrim((string) config('havana.public_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'c_images'.DIRECTORY_SEPARATOR.'Top_Story_Images',
            DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'www'.DIRECTORY_SEPARATOR.'html'.DIRECTORY_SEPARATOR.'c_images'.DIRECTORY_SEPARATOR.'Top_Story_Images',
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $images = collect(scandir($path) ?: [])
                ->filter(function (string $file) use ($path): bool {
                    if (str_starts_with($file, '.') || ! is_file($path.DIRECTORY_SEPARATOR.$file)) {
                        return false;
                    }

                    return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['gif', 'png', 'jpg', 'jpeg'], true);
                })
                ->sort()
                ->values()
                ->all();

            if ($images !== []) {
                return $images;
            }
        }

        return [];
    }

    private function formatNewsStory(string $body): string
    {
        $escaped = e(str_replace(["\r\n", "\r"], "\n", $body));

        return nl2br($escaped, false);
    }
}
