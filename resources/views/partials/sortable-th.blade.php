{{--
    Server-side sortable column header. Clicking reloads the page with sort/direction
    query params applied (full backend re-sort, not a client-side reshuffle).

    Required: $column (whitelisted key the controller understands), $label
    Optional: $class, $sortParam (default 'sort'), $dirParam (default 'direction'),
              $pageParam (paginator page name to reset on sort, default 'page'),
              $extraParams (array merged into the link's query string),
              $dataColumn (adds a data-column attribute, for the column-toggle partial),
              $style (raw style attribute passthrough)
--}}
@php
    $sortParam = $sortParam ?? 'sort';
    $dirParam = $dirParam ?? 'direction';
    $pageParam = $pageParam ?? 'page';
    $extraParams = $extraParams ?? [];

    $currentSort = request($sortParam);
    $currentDir = request($dirParam, 'asc');
    $isActive = $currentSort === $column;
    $newDir = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';

    $queryParams = array_merge(
        request()->except([$sortParam, $dirParam, $pageParam]),
        $extraParams,
        [$sortParam => $column, $dirParam => $newDir]
    );
@endphp
<th class="{{ $class ?? '' }}" @if(!empty($dataColumn)) data-column="{{ $dataColumn }}" @endif @if(!empty($style)) style="{{ $style }}" @endif>
    <a href="{{ request()->url() . '?' . http_build_query($queryParams) }}" class="text-reset text-decoration-none d-inline-flex align-items-center">
        {{ $label }}
        <span class="ms-1" style="font-size: 10px; opacity: {{ $isActive ? '1' : '0.35' }};">
            @if ($isActive)
                {{ $currentDir === 'asc' ? '▲' : '▼' }}
            @else
                ↕
            @endif
        </span>
    </a>
</th>
