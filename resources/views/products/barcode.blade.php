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
    $numberOfBarcodes = $quantity ?? 21;
    $barcodeData = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode ?? $product->sku);
    $renderer = new Picqer\Barcode\Renderers\PngRenderer();
    $renderer->setForegroundColor([0, 0, 0]);
    $pngData = $renderer->render($barcodeData, 150, 40);
    $base64 = base64_encode($pngData);
@endphp

<table>
    @for ($i = 0; $i < $numberOfBarcodes; $i++)
        @if ($i % 3 == 0)
            <tr>
        @endif
        <td>
            <div class="barcode-box">
                <img src="data:image/png;base64,{{ $base64 }}" style="width: 100%; max-width: 150px;" />
                <div class="barcode-text">{{ $product->barcode ?? $product->sku }}</div>
                <div class="product-name">{{ \Illuminate\Support\Str::limit($product->name, 25) }}</div>
            </div>
        </td>
        @if ($i % 3 == 2 || $i == $numberOfBarcodes - 1)
            </tr>
        @endif
    @endfor
</table>