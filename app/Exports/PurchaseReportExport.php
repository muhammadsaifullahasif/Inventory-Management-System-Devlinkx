<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PurchaseReportExport implements WithMultipleSheets
{
    protected $groupedData;
    protected $purchases;
    protected $summary;

    public function __construct($groupedData, $purchases, array $summary)
    {
        $this->groupedData = $groupedData;
        $this->purchases = $purchases;
        $this->summary = $summary;
    }

    public function sheets(): array
    {
        $sheets = [];

        // First sheet: Purchases by Supplier summary
        $sheets[] = new PurchasesBySupplierSheet($this->groupedData, $this->summary);

        // Individual sheets for each purchase
        foreach ($this->purchases as $purchase) {
            $sheets[] = new PurchaseDetailSheet($purchase);
        }

        return $sheets;
    }
}
