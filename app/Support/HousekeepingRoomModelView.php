<?php

namespace App\Support;

class HousekeepingRoomModelView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function modelId(): string
    {
        return (string) $this->row->model_id;
    }

    public function modelName(): string
    {
        return (string) $this->row->model_name;
    }

    public function doorX(): int
    {
        return (int) $this->row->door_x;
    }

    public function doorY(): int
    {
        return (int) $this->row->door_y;
    }

    public function doorZ(): float
    {
        return (float) $this->row->door_z;
    }

    public function doorDir(): int
    {
        return (int) $this->row->door_dir;
    }

    public function heightmap(): string
    {
        return (string) $this->row->heightmap;
    }

    public function triggerClass(): string
    {
        return (string) $this->row->trigger_class;
    }
}
