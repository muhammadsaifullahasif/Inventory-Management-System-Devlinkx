<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $data;
    protected $columns;
    protected $visibleColumns;
    protected $reportTitle;

    /**
     * @param array $data - The report data
     * @param array $columns - All available columns with their keys and labels
     * @param array $visibleColumns - Array of visible column keys
     * @param string $reportTitle - Title for the report
     */
    public function __construct(array $data, array $columns, array $visibleColumns, string $reportTitle = 'Report')
    {
        $this->data = $data;
        $this->columns = $columns;
        $this->visibleColumns = $visibleColumns;
        $this->reportTitle = $reportTitle;
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->data as $index => $item) {
            $row = [];
            foreach ($this->visibleColumns as $columnKey) {
                if (isset($this->columns[$columnKey])) {
                    $row[] = $this->formatValue($columnKey, $item, $index);
                }
            }
            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        $headings = [];
        foreach ($this->visibleColumns as $columnKey) {
            if (isset($this->columns[$columnKey])) {
                $headings[] = $this->columns[$columnKey]['label'];
            }
        }
        return $headings;
    }

    protected function formatValue(string $columnKey, array $item, int $index): string
    {
        $column = $this->columns[$columnKey];
        $field = $column['field'] ?? $columnKey;

        // Handle special cases
        if ($columnKey === 'id' || $field === '#') {
            return (string) ($index + 1);
        }

        if ($columnKey === 'image') {
            return $item['product_image'] ? 'Yes' : 'No';
        }

        // Get the value
        $value = $item[$field] ?? '';

        // Apply formatting if specified
        if (isset($column['format'])) {
            switch ($column['format']) {
                case 'number':
                    return is_numeric($value) ? number_format((float) $value) : $value;
                case 'decimal':
                    return is_numeric($value) ? number_format((float) $value, 2) : $value;
                case 'date':
                    if ($value && !empty($value)) {
                        try {
                            return \Carbon\Carbon::parse($value)->format('M d, Y');
                        } catch (\Exception $e) {
                            return $value;
                        }
                    }
                    return 'N/A';
                case 'currency':
                    return is_numeric($value) ? '$' . number_format((float) $value, 2) : $value;
            }
        }

        return (string) $value;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF']
                ]
            ],
        ];
    }
}
