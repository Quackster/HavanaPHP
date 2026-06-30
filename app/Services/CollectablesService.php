<?php

namespace App\Services;

use App\Support\LegacyCollectableData;
use App\Support\LegacyCollectableEntry;
use Illuminate\Support\Facades\DB;

class CollectablesService
{
    public function __construct(private readonly HavanaConfig $config) {}

    public function active(?int $pageId = null): ?LegacyCollectableData
    {
        $pageId ??= $this->configuredPageId();
        $row = DB::table('catalogue_collectables')
            ->where(function ($query) use ($pageId): void {
                $query->where('store_page', $pageId)
                    ->orWhere('admin_page', $pageId);
            })
            ->first();

        if (! $row) {
            return null;
        }

        $sprites = $this->sprites((string) $row->class_names);

        if ($sprites === []) {
            return null;
        }

        $row = $this->cycleIfExpired($row, $sprites);
        $position = max(0, min((int) $row->current_position, count($sprites) - 1));
        $activeSprite = $sprites[$position] ?? null;

        if (! $activeSprite) {
            return null;
        }

        $definitions = DB::table('items_definitions')
            ->whereIn('sprite', $sprites)
            ->get(['id', 'sprite', 'name', 'description'])
            ->keyBy('sprite');
        $definition = $definitions->get($activeSprite);

        if (! $definition) {
            return null;
        }

        $activeItem = DB::table('catalogue_items')
            ->where('page_id', (string) $row->admin_page)
            ->where('definition_id', (int) $definition->id)
            ->where('hidden', false)
            ->first([
                'id',
                'order_id',
                'price_coins',
                'price_pixels',
                'amount',
                'definition_id',
            ]);

        if (! $activeItem) {
            return null;
        }

        $activeItem->sprite = (string) $definition->sprite;
        $activeItem->name = (string) $definition->name;
        $activeItem->description = (string) $definition->description;

        $showroom = [];
        foreach ($sprites as $sprite) {
            $entryDefinition = $definitions->get($sprite);

            if ($entryDefinition) {
                $showroom[] = new LegacyCollectableEntry(
                    (string) $entryDefinition->sprite,
                    (string) $entryDefinition->name,
                    (string) $entryDefinition->description,
                );
            }
        }

        return new LegacyCollectableData($activeItem, $showroom, (int) $row->expiry);
    }

    private function configuredPageId(): int
    {
        $pageId = $this->config->integer('collectables.page');

        return $pageId > 0 ? $pageId : 51;
    }

    /** @return array<int, string> */
    private function sprites(string $classNames): array
    {
        return array_values(array_filter(array_map(
            static fn (string $sprite): string => trim($sprite),
            preg_split('/[\r\n,]+/', $classNames) ?: []
        )));
    }

    /**
     * @param  array<int, string>  $sprites
     */
    private function cycleIfExpired(object $row, array $sprites): object
    {
        if ((int) $row->expiry >= time()) {
            return $row;
        }

        $position = (int) $row->current_position + 1;

        if ($position >= count($sprites)) {
            $position = 0;
        }

        $expiry = time() + max(1, (int) $row->lifetime);
        DB::table('catalogue_collectables')->where('store_page', (int) $row->store_page)->update([
            'current_position' => $position,
            'expiry' => $expiry,
        ]);

        $row->current_position = $position;
        $row->expiry = $expiry;

        return $row;
    }
}
