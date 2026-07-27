@php
    $cols = $columns ?? 3;
    $colWidth = number_format(100 / $cols, 2);

    // Adjust barcode size based on columns
    $barcodeWidth = $cols <= 3 ? 150 : ($cols == 4 ? 120 : 100);
    $barcodeHeight = $cols <= 3 ? 40 : 35;
    $fontSize = $cols <= 3 ? 10 : ($cols == 4 ? 9 : 8);
    $nameFontSize = $cols <= 3 ? 8 : 7;
    $nameLimit = $cols <= 3 ? 25 : ($cols == 4 ? 20 : 15);

    // Flatten all barcodes into a single array
    $allBarcodes = [];
    foreach ($productsData as $data) {
        $product = $data['product'];
        $quantity = $data['quantity'];

        // Generate barcode image once per product
        $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode);
        $renderer = new Picqer\Barcode\Renderers\PngRenderer();
        $renderer->setForegroundColor([0, 0, 0]);
        $pngData = $renderer->render($barcode, $barcodeWidth, $barcodeHeight);
        $base64 = base64_encode($pngData);

        for ($i = 0; $i < $quantity; $i++) {
            $allBarcodes[] = [
                'name' => $product->name,
                'barcode' => $product->barcode,
                'base64' => $base64,
            ];
        }
    }
    $totalBarcodes = count($allBarcodes);
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
    @for ($i = 0; $i < $totalBarcodes; $i++)
        @if ($i % $cols == 0)
            <tr>
        @endif
        <td>
            <div class="barcode-box">
                <img src="data:image/png;base64,{{ $allBarcodes[$i]['base64'] }}" style="width: 100%; max-width: {{ $barcodeWidth }}px;" />
                <div class="barcode-text">{{ $allBarcodes[$i]['barcode'] }}</div>
                <div class="product-name">{{ \Illuminate\Support\Str::limit($allBarcodes[$i]['name'], $nameLimit) }}</div>
            </div>
        </td>
        @if ($i % $cols == $cols - 1 || $i == $totalBarcodes - 1)
            </tr>
        @endif
    @endfor
</table>
