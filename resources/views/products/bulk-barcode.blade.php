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
        width: 33.33%;
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
        font-size: 10px;
    }
    .product-name {
        font-size: 8px;
        color: #666;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
</style>

@php
    // Flatten all barcodes into a single array
    $allBarcodes = [];
    foreach ($productsData as $data) {
        $product = $data['product'];
        $quantity = $data['quantity'];

        // Generate barcode image once per product
        $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode);
        $renderer = new Picqer\Barcode\Renderers\PngRenderer();
        $renderer->setForegroundColor([0, 0, 0]);
        $pngData = $renderer->render($barcode, 150, 40);
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

<table>
    @for ($i = 0; $i < $totalBarcodes; $i++)
        @if ($i % 3 == 0)
            <tr>
        @endif
        <td>
            <div class="barcode-box">
                <img src="data:image/png;base64,{{ $allBarcodes[$i]['base64'] }}" style="width: 100%; max-width: 150px;" />
                <div class="barcode-text">{{ $allBarcodes[$i]['barcode'] }}</div>
                <div class="product-name">{{ \Illuminate\Support\Str::limit($allBarcodes[$i]['name'], 25) }}</div>
            </div>
        </td>
        @if ($i % 3 == 2 || $i == $totalBarcodes - 1)
            </tr>
        @endif
    @endfor
</table>
