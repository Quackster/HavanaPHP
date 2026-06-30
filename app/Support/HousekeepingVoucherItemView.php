<?php

namespace App\Support;

class HousekeepingVoucherItemView
{
    public function __construct(private readonly object $row) {}

    public function voucherCode(): string
    {
        return (string) $this->row->voucher_code;
    }

    public function catalogueSaleCode(): string
    {
        return (string) $this->row->catalogue_sale_code;
    }
}
