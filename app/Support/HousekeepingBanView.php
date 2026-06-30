<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class HousekeepingBanView
{
    private HousekeepingBanTypeView $banType;

    public function __construct(private readonly object $row)
    {
        $this->banType = new HousekeepingBanTypeView((string) $row->ban_type);
    }

    public function getBanType(): HousekeepingBanTypeView
    {
        return $this->banType;
    }

    public function getName(): string
    {
        if ($this->getBanType()->name() === 'MACHINE_ID') {
            return (string) (DB::table('users')->where('machine_id', $this->getValue())->value('username') ?? '');
        }

        if ($this->getBanType()->name() === 'USER_ID') {
            return (string) (DB::table('users')->where('id', (int) $this->getValue())->value('username') ?? '');
        }

        return '';
    }

    public function getValue(): string
    {
        return (string) $this->row->banned_value;
    }

    public function getMessage(): string
    {
        return (string) $this->row->message;
    }

    public function getBannedUtil(): string
    {
        return $this->formatDate($this->row->banned_until);
    }

    public function getBannedAt(): string
    {
        return $this->formatDate($this->row->banned_at);
    }

    public function getBannedBy(): string
    {
        $bannedBy = (int) $this->row->banned_by;

        if ($bannedBy === -1) {
            return 'Triggered spam filter';
        }

        if ($bannedBy > 0) {
            return (string) (DB::table('users')->where('id', $bannedBy)->value('username') ?? '');
        }

        return 'Legacy Banned';
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d-m-Y H:i:s');
        }

        if ($value === null || $value === '') {
            return '';
        }

        return date('d-m-Y H:i:s', strtotime((string) $value) ?: 0);
    }
}
