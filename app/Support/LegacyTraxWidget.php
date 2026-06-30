<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class LegacyTraxWidget
{
    public function __construct(private readonly object $row) {}

    public function getId(): int
    {
        return (int) $this->row->id;
    }

    public function getX(): int
    {
        return (int) $this->row->x;
    }

    public function getY(): int
    {
        return (int) $this->row->y;
    }

    public function getZ(): int
    {
        return (int) $this->row->z;
    }

    public function getSkin(): string
    {
        return LegacyNoteWidget::skinName((int) $this->row->skin_id);
    }

    public function getUserId(): int
    {
        return (int) $this->row->user_id;
    }

    public function getGroupId(): int
    {
        return (int) $this->row->group_id;
    }

    public function hasSong(): bool
    {
        return $this->getSong() !== null;
    }

    public function getSong(): ?LegacySong
    {
        $songId = (int) $this->row->extra_data;

        if ($songId <= 0) {
            return null;
        }

        $row = DB::table('soundmachine_songs')->where('id', $songId)->first();

        return $row ? new LegacySong($row) : null;
    }

    /** @return list<LegacySong> */
    public function getSongs(): array
    {
        $ownerId = $this->ownerId();
        $songs = DB::table('soundmachine_songs')
            ->where('user_id', $ownerId)
            ->orderBy('title')
            ->get()
            ->map(fn (object $row): LegacySong => new LegacySong($row))
            ->all();

        $diskSongs = DB::table('items')
            ->join('items_definitions', 'items_definitions.id', '=', 'items.definition_id')
            ->join('soundmachine_disks', 'soundmachine_disks.item_id', '=', 'items.id')
            ->join('soundmachine_songs', 'soundmachine_songs.id', '=', 'soundmachine_disks.song_id')
            ->where('items.user_id', $ownerId)
            ->where('items_definitions.sprite', 'song_disk')
            ->get('soundmachine_songs.*');

        foreach ($diskSongs as $row) {
            $song = new LegacySong($row);

            if (! collect($songs)->contains(fn (LegacySong $existing): bool => $existing->getId() === $song->getId())) {
                $songs[] = $song;
            }
        }

        return $songs;
    }

    public function ownerId(): int
    {
        if ($this->getGroupId() > 0) {
            return (int) DB::table('groups_details')->where('id', $this->getGroupId())->value('owner_id');
        }

        return $this->getUserId();
    }
}
