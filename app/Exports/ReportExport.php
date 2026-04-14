<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
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

    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $index => $item) {
            // Ensure item is an array
            if (is_object($item)) {
                $item = (array) $item;
            }

            $row = [];
            foreach ($this->visibleColumns as $columnKey) {
                if (isset($this->columns[$columnKey])) {
                    $row[] = $this->formatValue($columnKey, $item, $index);
                }
            }

            if (!empty($row)) {
                $rows[] = $row;
            }
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
            $hasImage = isset($item['product_image']) && !empty($item['product_image']);
            return $hasImage ? 'Yes' : 'No';
        }

        // Get the value - try multiple ways
        $value = '';

        // Try direct field access
        if (isset($item[$field])) {
            $value = $item[$field];
        }
        // Try column key as field
        elseif (isset($item[$columnKey])) {
            $value = $item[$columnKey];
        }

        // Handle null values
        if ($value === null) {
            $value = '';
        }

        // Handle Carbon objects or date strings
        if ($value instanceof \Carbon\Carbon) {
            $value = $value->format('Y-m-d');
        }

        // Apply formatting if specified
        if (isset($column['format']) && $value !== '') {
            switch ($column['format']) {
                case 'number':
                    return is_numeric($value) ? number_format((float) $value) : (string) $value;
                case 'decimal':
                    return is_numeric($value) ? number_format((float) $value, 2) : (string) $value;
                case 'date':
                    if ($value && !empty($value)) {
                        try {
                            if ($value instanceof \Carbon\Carbon) {
                                return $value->format('M d, Y');
                            }
                            return \Carbon\Carbon::parse($value)->format('M d, Y');
                        } catch (\Exception $e) {
                            return (string) $value;
                        }
                    }
                    return 'N/A';
                case 'currency':
                    return is_numeric($value) ? '$' . number_format((float) $value, 2) : (string) $value;
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
