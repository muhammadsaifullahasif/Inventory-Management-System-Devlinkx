<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithDrawings, WithEvents
{
    protected $data;
    protected $columns;
    protected $visibleColumns;
    protected $reportTitle;
    protected $preparedDrawings;
    protected $rowsWithImages = [];
    protected $imageColumnIndex;
    protected $temporaryFiles = [];

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
            return '';
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

    public function drawings(): array
    {
        return $this->prepareDrawings();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $drawings = $this->prepareDrawings();

                if (empty($drawings) || $this->imageColumnIndex === null) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $columnLetter = Coordinate::stringFromColumnIndex($this->imageColumnIndex);

                $sheet->getColumnDimension($columnLetter)->setWidth(14);

                foreach ($this->rowsWithImages as $rowNumber) {
                    $sheet->getRowDimension($rowNumber)->setRowHeight(42);
                    $sheet->getStyle($columnLetter . $rowNumber)
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }

    protected function prepareDrawings(): array
    {
        if ($this->preparedDrawings !== null) {
            return $this->preparedDrawings;
        }

        $this->preparedDrawings = [];
        $this->rowsWithImages = [];
        $this->imageColumnIndex = $this->findImageColumnIndex();

        if ($this->imageColumnIndex === null || !$this->shouldIncludeRow()) {
            return $this->preparedDrawings;
        }

        $columnLetter = Coordinate::stringFromColumnIndex($this->imageColumnIndex);
        $rowNumber = 2;

        foreach ($this->data as $item) {
            $normalizedItem = $this->normalizeItem($item);
            $imageValue = $this->extractImageValue($normalizedItem);
            $imagePath = $this->resolveImagePath($imageValue);

            if ($imagePath !== null) {
                $drawing = new Drawing();
                $drawing->setName('Image');
                $drawing->setDescription('Report item image');
                $drawing->setPath($imagePath);
                $drawing->setHeight(36);
                $drawing->setCoordinates($columnLetter . $rowNumber);
                $drawing->setOffsetX(4);
                $drawing->setOffsetY(4);

                $this->preparedDrawings[] = $drawing;
                $this->rowsWithImages[] = $rowNumber;
            }

            $rowNumber++;
        }

        return $this->preparedDrawings;
    }

    protected function findImageColumnIndex(): ?int
    {
        foreach ($this->visibleColumns as $index => $columnKey) {
            if (!isset($this->columns[$columnKey])) {
                continue;
            }

            $column = $this->columns[$columnKey];
            $field = (string) ($column['field'] ?? $columnKey);
            $label = (string) ($column['label'] ?? $columnKey);

            if ($this->isImageColumn($columnKey, $field, $label)) {
                return $index + 1;
            }
        }

        return null;
    }

    protected function extractImageValue(array $item): ?string
    {
        foreach ($this->visibleColumns as $columnKey) {
            if (!isset($this->columns[$columnKey])) {
                continue;
            }

            $column = $this->columns[$columnKey];
            $field = (string) ($column['field'] ?? $columnKey);
            $label = (string) ($column['label'] ?? '');

            if (!$this->isImageColumn($columnKey, $field, $label)) {
                continue;
            }

            $value = $item[$field] ?? $item[$columnKey] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach (['product_image', 'image_url', 'image'] as $fallbackKey) {
            $value = $item[$fallbackKey] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function resolveImagePath(?string $imageValue): ?string
    {
        if ($imageValue === null || $imageValue === '') {
            return null;
        }

        if (str_starts_with($imageValue, 'data:image')) {
            return $this->storeDataUriImage($imageValue);
        }

        if (filter_var($imageValue, FILTER_VALIDATE_URL)) {
            $localPath = $this->resolveLocalPathFromUrl($imageValue);
            if ($localPath !== null) {
                return $localPath;
            }

            return $this->downloadImageToTempFile($imageValue);
        }

        $normalizedPath = ltrim(str_replace('\\', '/', $imageValue), '/');
        $candidates = [
            $imageValue,
            public_path($normalizedPath),
            storage_path('app/public/' . $normalizedPath),
            public_path('uploads/' . basename($normalizedPath)),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveLocalPathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $decodedPath = ltrim(urldecode($path), '/');
        $candidates = [public_path($decodedPath)];
        $segments = explode('/', $decodedPath);

        $uploadsIndex = array_search('uploads', $segments, true);
        if ($uploadsIndex !== false) {
            $candidates[] = public_path(implode('/', array_slice($segments, $uploadsIndex)));
        }

        $storageIndex = array_search('storage', $segments, true);
        if ($storageIndex !== false) {
            $candidates[] = public_path(implode('/', array_slice($segments, $storageIndex)));
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function downloadImageToTempFile(string $url): ?string
    {
        $context = stream_context_create([
            'http' => ['timeout' => 5],
            'https' => ['timeout' => 5],
        ]);

        $binary = @file_get_contents($url, false, $context);
        if ($binary === false) {
            return null;
        }

        return $this->saveBinaryImageToTempFile($binary);
    }

    protected function storeDataUriImage(string $dataUri): ?string
    {
        $parts = explode(',', $dataUri, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $binary = base64_decode($parts[1], true);
        if ($binary === false) {
            return null;
        }

        return $this->saveBinaryImageToTempFile($binary);
    }

    protected function saveBinaryImageToTempFile(string $binary): ?string
    {
        if (@getimagesizefromstring($binary) === false) {
            return null;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'excel_img_');
        if ($tempFile === false) {
            return null;
        }

        if (file_put_contents($tempFile, $binary) === false) {
            @unlink($tempFile);
            return null;
        }

        $this->temporaryFiles[] = $tempFile;

        return $tempFile;
    }

    protected function shouldIncludeRow(): bool
    {
        foreach ($this->visibleColumns as $columnKey) {
            if (isset($this->columns[$columnKey])) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeItem($item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (is_object($item) && method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        return is_object($item) ? (array) $item : [];
    }

    protected function isImageColumn(string $columnKey, string $field, string $label): bool
    {
        $haystack = strtolower($columnKey . ' ' . $field . ' ' . $label);
        return str_contains($haystack, 'image');
    }

    public function __destruct()
    {
        foreach ($this->temporaryFiles as $tempFile) {
            if (is_string($tempFile) && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
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
