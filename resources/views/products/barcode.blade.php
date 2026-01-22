<div style="width: 100%; display: flex; flex-wrap: wrap; justify-content: space-evenly; align-items: center;">
    {{-- @if ( $_GET['number_of_barcode'] != '' ) --}}
        @for ($i = 0; $i < ( isset($_GET['number_of_barcode']) ? $_GET['number_of_barcode'] : 42 ); $i++)
            <div style="margin: 10px; text-align: center; border: 1px solid #000; padding: 10px; max-width: 30%; width: 100%;">
                <div style="max-width: 100%;">
                    @php
                        // Make Barcode object of Code128 encoding.
                        $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode);
                        $renderer = new Picqer\Barcode\Renderers\SvgRenderer();
                        // $renderer->setSvgType($renderer::TYPE_SVG_INLINE); // Changes the output to be used inline inside HTML documents, instead of a standalone SVG image (default)
                        $renderer->setSvgType($renderer::TYPE_SVG_STANDALONE); // If you want to force the default, create a stand alone SVG image
                        // echo $renderer->render($barcode, 80, 40);
                    @endphp
                    {!! $renderer->render($barcode, 100, 40) !!}
                </div>
                <div>{{ $product->barcode }}</div>
            </div>
        @endfor
    {{-- @else --}}
        {{-- <p>No barcodes to display.</p> --}}
    {{-- @endif --}}
</div>