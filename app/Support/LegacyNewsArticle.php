<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

class LegacyNewsArticle
{
    public readonly string $title;

    public readonly string $shortstory;

    public readonly string $articleImage;

    public readonly int $views;

    /** @param list<LegacyNewsCategory> $categories */
    public function __construct(
        private readonly int $id,
        string $title,
        private readonly int $authorId,
        private readonly string $authorOverride,
        string $shortstory,
        private readonly string $fullstory,
        private readonly Carbon $createdAt,
        private readonly string $topstory,
        private readonly string $topstoryOverride,
        string $articleImage,
        private readonly bool $published,
        private readonly array $categories = [],
        private readonly bool $futurePublished = false,
        private readonly int $viewsCount = 0,
    ) {
        $this->title = $title;
        $this->shortstory = $shortstory;
        $this->articleImage = $articleImage;
        $this->views = $viewsCount;
    }

    public static function placeholder(): self
    {
        return new self(
            0,
            'No news',
            0,
            'Hotel Staff',
            'There is no news.',
            'There is no news.',
            now(),
            'attention_topstory.png',
            '',
            '',
            true,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        if ($this->id === 0) {
            return '0-no-news';
        }

        return $this->id.'-'.Str::slug($this->title);
    }

    public function getDate(): string
    {
        return $this->createdAt->format('D d M, Y');
    }

    public function getDateForInput(): string
    {
        return $this->createdAt->format('Y-m-d\TH:i');
    }

    public function getLiveTopStory(): string
    {
        if ($this->topstoryOverride !== '') {
            return $this->topstoryOverride;
        }

        return rtrim((string) config('havana.settings_defaults.static.content.path', 'http://localhost'), '/')
            .'/c_images/Top_Story_Images/'.$this->topstory;
    }

    public function getEscapedStory(): string
    {
        return nl2br(e($this->fullstory), false);
    }

    public function getFullStory(): string
    {
        return $this->fullstory;
    }

    public function getAuthor(): string
    {
        if ($this->authorOverride !== '') {
            return $this->authorOverride;
        }

        return 'Hotel Staff';
    }

    /** @return list<LegacyNewsCategory> */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function isFuturePublished(): bool
    {
        return $this->futurePublished;
    }

    public function hasCategory(int $id): bool
    {
        foreach ($this->categories as $category) {
            if ($category->getId() === $id) {
                return true;
            }
        }

        return false;
    }

    public function getAuthorId(): int
    {
        return $this->authorId;
    }

    public function getAuthorOverride(): string
    {
        return $this->authorOverride;
    }

    public function getTopStory(): string
    {
        return $this->topstory;
    }

    public function getTopstoryOverride(): string
    {
        return $this->topstoryOverride;
    }

    public function getArticleImage(): string
    {
        return $this->articleImage;
    }

    public function getViews(): int
    {
        return $this->views;
    }
}
