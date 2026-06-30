<?php

namespace App\Support;

class LegacyAlert
{
    public function __construct(
        private readonly string $alertType,
        private readonly string $message,
    ) {}

    public function getAlertType(): string
    {
        return $this->alertType;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
