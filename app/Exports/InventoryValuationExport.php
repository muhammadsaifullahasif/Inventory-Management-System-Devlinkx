<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InventoryValuationExport implements WithMultipleSheets
{
    protected $groupedData;
    protected $inventoryItems;
    protected $summary;
    protected $groupBy;

    public function __construct(array $groupedData, array $inventoryItems, array $summary, string $groupBy)
    {
        $this->groupedData = $groupedData;
        $this->inventoryItems = $inventoryItems;
        $this->summary = $summary;
        $this->groupBy = $groupBy;
    }

    public function sheets(): array
    {
        return [
            new SummarySheet($this->summary),
            new InventoryByProductSheet($this->groupedData, $this->summary, $this->groupBy),
            new DetailedInventorySheet($this->inventoryItems, $this->summary),
        ];
    }
}
