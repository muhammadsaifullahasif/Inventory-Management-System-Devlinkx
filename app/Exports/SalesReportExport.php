<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesReportExport implements WithMultipleSheets
{
    protected $groupedData;
    protected $summary;

    public function __construct($groupedData, array $summary)
    {
        $this->groupedData = $groupedData;
        $this->summary = $summary;
    }

    public function sheets(): array
    {
        $sheets = [];

        // First sheet: Sales by Channel summary
        $sheets[] = new SalesByChannelSheet($this->groupedData, $this->summary);

        // Individual sheets for each sales channel
        foreach ($this->groupedData as $channel) {
            if (!empty($channel['orders'])) {
                $sheets[] = new SalesChannelDetailSheet($channel);
            }
        }

        return $sheets;
    }
}
