<?php

namespace App\Support;

class LegacyStickerProduct
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $description,
        private readonly int $type,
        private readonly string $data,
        private readonly int $price = 0,
        private readonly int $amount = 1,
        private readonly int $categoryId = 0,
        private readonly int $widgetType = 0,
    ) {}

    public static function fromRow(?object $row, int $stickerId): self
    {
        if (! $row) {
            return new self($stickerId, 'Item', '', 1, 'unknown', 1, 0);
        }

        return new self(
            (int) $row->id,
            (string) $row->name,
            (string) $row->description,
            (int) $row->type,
            (string) $row->data,
            (int) $row->price,
            (int) $row->amount,
            (int) $row->category_id,
            (int) $row->widget_type,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getTypeId(): int
    {
        return $this->type;
    }

    public function getCssClass(): string
    {
        return match ($this->type) {
            1 => 's_'.$this->data.'_pre',
            3 => 'commodity_'.$this->data.'_pre',
            4 => 'b_'.$this->data.'_pre',
            2, 5 => 'w_'.$this->data.'_pre',
            default => '',
        };
    }

    public function isGroupWidget(): bool
    {
        return $this->widgetType === -1;
    }

    public function isHomeWidget(): bool
    {
        return $this->widgetType === 1;
    }
}
