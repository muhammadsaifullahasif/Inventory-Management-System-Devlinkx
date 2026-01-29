<style>
    @page {
        size: A4 portrait;
        margin: 0;
    }
    body {
        margin: 0;
        padding: 0;
    }
</style>

<div style="width: 100%; display: flex; flex-wrap: wrap; justify-content: space-evenly; align-items: center;">
    @for ($i = 0; $i < ( isset($_GET['number_of_barcode']) ? $_GET['number_of_barcode'] : 42 ); $i++)
        <div style="margin: 10px; text-align: center; border: 1px solid #000; padding: 10px; max-width: 30%; width: 100%;">
            <div style="max-width: 100%;">
                @php
                    // Make Barcode object of Code128 encoding.
                    $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode);
                    $renderer = new Picqer\Barcode\Renderers\PngRenderer();
                    $renderer->setForegroundColor([0, 0, 0]); // Black bars
                    $pngData = $renderer->render($barcode, 200, 80);
                    $base64 = base64_encode($pngData);
                @endphp
                <img src="data:image/png;base64,{{ $base64 }}" style="max-width: 100%; height: auto;" />
            </div>
            <div style="margin-top: 5px; font-size: 12px;">{{ $product->barcode }}</div>
        </div>
    @endfor
</div>