<style>
    @page {
        size: A4 portrait;
        margin: 0;
    }
    body {
        margin: 0;
        padding: 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    td {
        width: 33.33%;
        text-align: center;
        vertical-align: top;
        padding: 10px;
    }
    .barcode-box {
        border: 1px solid #000;
        padding: 10px;
        margin: 5px;
    }
    .barcode-text {
        margin-top: 5px;
        font-size: 12px;
    }
</style>

@php
    $numberOfBarcodes = isset($_GET['number_of_barcode']) ? $_GET['number_of_barcode'] : 42;
    $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode);
    $renderer = new Picqer\Barcode\Renderers\PngRenderer();
    $renderer->setForegroundColor([0, 0, 0]);
    $pngData = $renderer->render($barcode, 180, 60);
    $base64 = base64_encode($pngData);
@endphp

<table>
    @for ($i = 0; $i < $numberOfBarcodes; $i++)
        @if ($i % 3 == 0)
            <tr>
        @endif
        <td>
            <div class="barcode-box">
                <img src="data:image/png;base64,{{ $base64 }}" style="width: 100%; max-width: 180px;" />
                <div class="barcode-text">{{ $product->barcode }}</div>
            </div>
        </td>
        @if ($i % 3 == 2 || $i == $numberOfBarcodes - 1)
            </tr>
        @endif
    @endfor
</table>