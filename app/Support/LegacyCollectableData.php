<?php

namespace App\Support;

class LegacyCollectableData
{
    /**
     * @param  array<int, LegacyCollectableEntry>  $showroom
     */
    public function __construct(
        public readonly object $activeItem,
        public readonly array $showroom,
        public readonly int $expiry,
    ) {}
}
