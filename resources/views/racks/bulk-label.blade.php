@php
    $cols = $columns ?? 3;
    $colWidth = number_format(100 / $cols, 2);

    $rackFontSize = $cols == 2 ? 30 : ($cols == 3 ? 22 : ($cols == 4 ? 16 : 14));
    $warehouseFontSize = $cols == 2 ? 14 : ($cols == 3 ? 12 : ($cols == 4 ? 10 : 9));
    $nameLimit = $cols <= 3 ? 25 : ($cols == 4 ? 20 : 15);

    // Flatten all labels into a single array
    $allLabels = [];
    foreach ($racksData as $data) {
        $rack = $data['rack'];
        $quantity = $data['quantity'];

        for ($i = 0; $i < $quantity; $i++) {
            $allLabels[] = [
                'name' => $rack->name,
                'warehouse' => $rack->warehouse->name,
            ];
        }
    }
    $totalLabels = count($allLabels);
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
        vertical-align: middle;
        padding: 5px;
    }
    .label-box {
        border: 1px solid #000;
        padding: 12px 8px;
        margin: 3px;
    }
    .rack-name {
        font-size: {{ $rackFontSize }}px;
        font-weight: bold;
    }
    .warehouse-name {
        font-size: {{ $warehouseFontSize }}px;
        color: #555;
        margin-top: 4px;
    }
</style>

<table>
    @for ($i = 0; $i < $totalLabels; $i++)
        @if ($i % $cols == 0)
            <tr>
        @endif
        <td>
            <div class="label-box">
                <div class="rack-name">{{ $allLabels[$i]['name'] }}</div>
                <div class="warehouse-name">{{ \Illuminate\Support\Str::limit($allLabels[$i]['warehouse'], $nameLimit) }}</div>
            </div>
        </td>
        @if ($i % $cols == $cols - 1 || $i == $totalLabels - 1)
            </tr>
        @endif
    @endfor
</table>
