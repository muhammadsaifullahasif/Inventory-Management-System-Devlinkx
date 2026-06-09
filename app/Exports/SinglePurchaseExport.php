<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SinglePurchaseExport implements WithMultipleSheets
{
    protected $purchase;

    public function __construct($purchase)
    {
        $this->purchase = $purchase;
    }

    public function sheets(): array
    {
        return [
            new PurchaseDetailSheet($this->purchase),
        ];
    }
}
