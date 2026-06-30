<?php

namespace App\Support;

class HousekeepingInfobusPollDataView
{
    /** @param list<string> $answers */
    public function __construct(
        private readonly string $question,
        private readonly array $answers,
    ) {}

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswers(): LegacyListView
    {
        return new LegacyListView($this->answers);
    }
}
