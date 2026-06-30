<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class LegacyRatingWidget
{
    public function __construct(
        private readonly int $id,
        private readonly int $homeId,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getAverageRating(): int
    {
        $average = DB::table('homes_ratings')->where('home_id', $this->homeId)->avg('rating');

        return (int) ($average ?? 0);
    }

    public function getRatingPixels(): int
    {
        $rating = $this->getAverageRating();

        if ($rating <= 0) {
            $rating = 1;
        }

        return (int) round($rating * 150 / 5);
    }

    public function getVoteCount(): int
    {
        return DB::table('homes_ratings')->where('home_id', $this->homeId)->count();
    }

    public function getHighVoteCount(): int
    {
        return DB::table('homes_ratings')
            ->where('home_id', $this->homeId)
            ->where('rating', '>=', 4)
            ->count();
    }
}
