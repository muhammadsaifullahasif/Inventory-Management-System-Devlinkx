{{--
    Generic checkbox multi-select dropdown filter.
    Params:
      $name        - form field base name, submitted as {name}[] (e.g. 'category_id')
      $label       - visible label (e.g. 'Category')
      $items       - collection of objects with ->id and ->name
      $selectedIds - array of currently selected ids
      $idPrefix    - unique string per page+field, avoids DOM id collisions (e.g. 'sales_report_category')
      $defaultText - text shown when nothing selected (default: "All {$label}s")
      $colClass    - wrapper column class (default: 'col-md-2')
--}}
@php
    $defaultText = $defaultText ?? "All {$label}s";
    $colClass = $colClass ?? 'col-md-2';
@endphp
<div class="{{ $colClass }}">
    <label class="form-label d-block">{{ $label }}</label>
    <div class="dropdown filter-multiselect" style="position: relative;">
        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start filter-multiselect-toggle">
            <span class="filter-multiselect-label" data-default-text="{{ $defaultText }}">
                @if(empty($selectedIds))
                    {{ $defaultText }}
                @else
                    {{ count($selectedIds) }} Selected
                @endif
            </span>
        </button>
        <div class="dropdown-menu p-2 filter-multiselect-menu" style="max-height: 250px; overflow-y: auto; min-width: 220px;">
            @forelse($items as $item)
                <div class="form-check">
                    <input class="form-check-input filter-multiselect-checkbox" type="checkbox" name="{{ $name }}[]" value="{{ $item->id }}" id="{{ $idPrefix }}_{{ $item->id }}"
                        {{ in_array($item->id, $selectedIds) ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ $idPrefix }}_{{ $item->id }}">
                        {{ $item->name }}
                    </label>
                </div>
            @empty
                <div class="text-muted px-1">None found</div>
            @endforelse
        </div>
    </div>
</div>

@once
    @include('partials.filters.multi-select-dropdown-script')
@endonce
