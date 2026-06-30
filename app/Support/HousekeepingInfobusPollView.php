<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HousekeepingInfobusPollView
{
    public function __construct(private readonly object $row) {}

    public function __get(string $name): mixed
    {
        return $name === 'id' ? $this->getId() : null;
    }

    public function getId(): int
    {
        return (int) $this->row->id;
    }

    public function getInitiatedBy(): int
    {
        return (int) $this->row->initiated_by;
    }

    public function getCreator(): string
    {
        return (string) (DB::table('users')->where('id', $this->getInitiatedBy())->value('username') ?? '');
    }

    public function getPollData(): HousekeepingInfobusPollDataView
    {
        $data = json_decode((string) $this->row->poll_data, true);
        $question = is_array($data) ? (string) ($data['question'] ?? '') : '';
        $answers = is_array($data) && is_array($data['answers'] ?? null) ? array_values(array_map('strval', $data['answers'])) : [];

        return new HousekeepingInfobusPollDataView($question, $answers);
    }

    public function getCreatedAt(): int
    {
        return Carbon::parse($this->row->created_at)->timestamp;
    }

    public function getCreatedAtFormatted(): string
    {
        return Carbon::parse($this->row->created_at)->format('M j, Y g:i A');
    }
}
