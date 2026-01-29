@php
    $numberOfBarcodes = $quantity ?? 21;
    $cols = $columns ?? 3;
    $colWidth = number_format(100 / $cols, 2);

    // Adjust barcode size based on columns
    $barcodeWidth = $cols <= 3 ? 150 : ($cols == 4 ? 120 : 100);
    $barcodeHeight = $cols <= 3 ? 40 : 35;
    $fontSize = $cols <= 3 ? 10 : ($cols == 4 ? 9 : 8);
    $nameFontSize = $cols <= 3 ? 8 : 7;
    $nameLimit = $cols <= 3 ? 25 : ($cols == 4 ? 20 : 15);

    $barcodeData = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode ?? $product->sku);
    $renderer = new Picqer\Barcode\Renderers\PngRenderer();
    $renderer->setForegroundColor([0, 0, 0]);
    $pngData = $renderer->render($barcodeData, $barcodeWidth, $barcodeHeight);
    $base64 = base64_encode($pngData);
@endphp

<style>
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
    body {
        margin: 0;
        padding: 0;
        font-family: sans-serif;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    td {
        width: {{ $colWidth }}%;
        text-align: center;
        vertical-align: top;
        padding: 5px;
    }
    .barcode-box {
        border: 1px solid #000;
        padding: 8px;
        margin: 3px;
    }
    .barcode-text {
        margin-top: 3px;
        font-size: {{ $fontSize }}px;
    }
    .product-name {
        font-size: {{ $nameFontSize }}px;
        color: #666;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
</style>

<table>
    @for ($i = 0; $i < $numberOfBarcodes; $i++)
        @if ($i % $cols == 0)
            <tr>
        @endif
        <td>
            <div class="barcode-box">
                <img src="data:image/png;base64,{{ $base64 }}" style="width: 100%; max-width: {{ $barcodeWidth }}px;" />
                <div class="barcode-text">{{ $product->barcode ?? $product->sku }}</div>
                <div class="product-name">{{ \Illuminate\Support\Str::limit($product->name, $nameLimit) }}</div>
            </div>
        </td>
        @if ($i % $cols == $cols - 1 || $i == $numberOfBarcodes - 1)
            </tr>
        @endif
    @endfor
</table>