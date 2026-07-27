<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CogsReportExport implements WithMultipleSheets
{
    protected $data;
    protected $summary;
    protected $groupBy;
    protected $orderItems;

    public function __construct(array $data, array $summary, string $groupBy, $orderItems = [])
    {
        $this->data = $data;
        $this->summary = $summary;
        $this->groupBy = $groupBy;
        $this->orderItems = $orderItems;
    }

    public function sheets(): array
    {
        return [
            new CogsReportSummarySheet($this->data, $this->summary, $this->groupBy),
            new CogsReportDetailSheet($this->orderItems),
        ];
    }
}
