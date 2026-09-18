{{--
    Generic Group By select. Params:
      $options - value => label array, e.g. ['channel' => 'Sales Channel', 'product' => 'Product', 'date' => 'Date', 'category' => 'Category']
      $current - currently selected value
--}}
<div class="col-md-2">
    <label class="form-label">Group By</label>
    <select name="group_by" class="form-select form-select-sm">
        @foreach($options as $value => $optionLabel)
            <option value="{{ $value }}" {{ $current == $value ? 'selected' : '' }}>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
