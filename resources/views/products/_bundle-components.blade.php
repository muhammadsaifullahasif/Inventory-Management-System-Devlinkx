@php
    // $initialComponents keeps whatever keys it arrives with (sequential from
    // the DB, possibly gapped from old('components') after a mid-list removal
    // on a failed validation redisplay) so @error('components.N....') lines up
    // with the keys Laravel actually validated against.
    $initialComponents = collect($initialComponents ?? []);
    $existingKeys = array_map('intval', array_keys($initialComponents->all()));
    $nextIndex = empty($existingKeys) ? 0 : max($existingKeys) + 1;
@endphp

<!-- Bundle Components Section -->
<div id="bundleComponentsSection" style="display: {{ ($bundleComponentsVisible ?? false) ? 'block' : 'none' }};">
    <div class="card bg-light border mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0">Bundle Components <span class="text-danger">*</span></h6>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-3">
                <i class="feather-info me-2"></i>
                <strong>Auto-Pairing:</strong> Each bundle uses 1 of each component. Bundle quantity is automatically calculated based on the product with the lowest stock. At least 2 components are required.
            </div>

            <div class="row g-3" id="componentsContainer" data-repeater-list data-next-index="{{ $nextIndex }}">
                @foreach($initialComponents as $i => $component)
                    <div class="col-sm-6 col-lg-3">
                        <div class="card h-100 bundle-component-card" data-repeater-item>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0">Product</label>
                                    <span class="badge bg-soft-info text-info component-stock-badge d-none"></span>
                                </div>
                                <select name="components[{{ $i }}][product_id]" class="form-select component-product @error('components.'.$i.'.product_id') is-invalid @enderror" required>
                                    <option value="">Select Product</option>
                                    @foreach($products ?? [] as $product)
                                        <option value="{{ $product->id }}" data-stock="{{ $product->available_stock }}" {{ (string) ($component['product_id'] ?? '') === (string) $product->id ? 'selected' : '' }}>
                                            {{ $product->name }} ({{ $product->sku }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('components.'.$i.'.product_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted component-product-info d-block mt-1"></small>
                                <input type="hidden" name="components[{{ $i }}][quantity]" class="component-quantity" value="{{ $component['quantity'] ?? 1 }}">
                                <div class="mt-auto pt-3">
                                    <button type="button" class="btn btn-sm text-danger px-0" data-repeater-remove>
                                        <i class="feather-trash-2 me-1"></i>Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="col-sm-6 col-lg-3">
                    <button type="button" class="bundle-component-add-tile" data-repeater-add>
                        <i class="feather-plus d-block mb-1"></i>
                        <span>Add another component</span>
                    </button>
                </div>

                <template data-repeater-template>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card h-100 bundle-component-card" data-repeater-item>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0">Product</label>
                                    <span class="badge bg-soft-info text-info component-stock-badge d-none"></span>
                                </div>
                                <select name="components[__INDEX__][product_id]" class="form-select component-product" required>
                                    <option value="">Select Product</option>
                                    @foreach($products ?? [] as $product)
                                        <option value="{{ $product->id }}" data-stock="{{ $product->available_stock }}">{{ $product->name }} ({{ $product->sku }})</option>
                                    @endforeach
                                </select>
                                <small class="text-muted component-product-info d-block mt-1"></small>
                                <input type="hidden" name="components[__INDEX__][quantity]" class="component-quantity" value="1">
                                <div class="mt-auto pt-3">
                                    <button type="button" class="btn btn-sm text-danger px-0" data-repeater-remove>
                                        <i class="feather-trash-2 me-1"></i>Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            @error('components')
                <div class="text-danger mt-2">{{ $message }}</div>
            @enderror

            <!-- Stock Preview -->
            <div class="mt-3 p-3 bg-white rounded border">
                <h6 class="mb-2">Stock Preview</h6>
                <div id="stockPreview" class="text-center text-muted py-2">
                    <small>Add at least 2 components to see stock calculation</small>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .bundle-component-card .form-label {
        font-size: .8125rem;
        font-weight: 500;
    }

    .bundle-component-card {
        min-height: 150px;
    }

    .bundle-component-add-tile {
        width: 100%;
        height: 100%;
        min-height: 150px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 2px dashed #c9cedb;
        border-radius: .5rem;
        background: transparent;
        color: #8a94a6;
        font-size: .875rem;
        transition: border-color .15s ease, color .15s ease;
    }

    .bundle-component-add-tile:hover,
    .bundle-component-add-tile:focus {
        border-color: var(--bs-primary, #635bff);
        color: var(--bs-primary, #635bff);
    }

    .bundle-component-card .select2-selection--single.select2-selection--clearable {
        padding-right: 3.5rem;
    }

    .bundle-component-card .select2-selection__clear {
        border: none;
        width: .25rem !important;
        height: .25rem !important;
    }
</style>
@endpush

@push('scripts')
<script>
(function() {
    // Bundles require at least 2 components (see ProductController's
    // 'components' => 'required_if:is_bundle,1|array|min:2' rule), so the
    // remove button is disabled once only 2 cards are left instead of 1.
    var MIN_COMPONENTS = 2;

    function initSelect2(select) {
        if (window.jQuery && jQuery.fn.select2) {
            jQuery(select).select2({
                placeholder: 'Search and select a product...',
                allowClear: true,
                width: '100%',
                theme: 'bootstrap-5'
            });
        }
    }

    function destroySelect2(select) {
        if (window.jQuery && jQuery.fn.select2 && jQuery(select).data('select2')) {
            jQuery(select).select2('destroy');
        }
    }

    function initRepeater(list) {
        var template = list.querySelector('template[data-repeater-template]');
        var addTile = list.querySelector('[data-repeater-add]');
        var next = parseInt(list.dataset.nextIndex, 10);
        if (isNaN(next)) next = 0;

        function items() {
            return list.querySelectorAll('[data-repeater-item]');
        }

        function updateStockBadge(row) {
            var select = row.querySelector('.component-product');
            var badge = row.querySelector('.component-stock-badge');
            if (!select || !badge) return;

            var option = select.options[select.selectedIndex];
            var stock = option ? option.dataset.stock : undefined;

            if (select.value && stock !== undefined) {
                badge.textContent = 'Stock: ' + stock;
                badge.classList.remove('d-none');
            } else {
                badge.textContent = '';
                badge.classList.add('d-none');
            }
        }

        function updateProductInfo(row) {
            var select = row.querySelector('.component-product');
            var info = row.querySelector('.component-product-info');
            if (!select || !info) return;

            var option = select.options[select.selectedIndex];
            info.textContent = (select.value && option) ? option.text.trim() : '';
        }

        function updateStockPreview() {
            var components = [];
            var isValid = true;

            items().forEach(function(row) {
                var productId = row.querySelector('.component-product').value;
                var quantity = parseInt(row.querySelector('.component-quantity').value, 10);

                if (productId && quantity > 0) {
                    components.push({ product_id: productId, quantity: quantity });
                } else {
                    isValid = false;
                }
            });

            var $preview = window.jQuery ? jQuery('#stockPreview') : null;
            if (!$preview) return;

            if (!isValid || components.length < MIN_COMPONENTS) {
                $preview.html('<small class="text-muted">Add at least 2 components to see stock calculation</small>');
                return;
            }

            jQuery.ajax({
                url: '{{ route('bundles.calculate-stock') }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    components: components
                },
                success: function(response) {
                    if (!response.success) return;

                    var html = '<div class="d-flex justify-content-between align-items-center mb-2">'
                        + '<strong>Available Bundles:</strong>'
                        + '<span class="badge bg-' + (response.available_bundles > 10 ? 'success' : (response.available_bundles > 0 ? 'warning' : 'danger')) + ' fs-6">'
                        + response.available_bundles + '</span></div>';

                    if (response.limiting_component) {
                        html += '<small class="text-muted">Limited by: <strong>' + response.limiting_component + '</strong></small>';
                    }

                    $preview.html(html);
                },
                error: function() {
                    $preview.html('<small class="text-danger">Failed to calculate stock</small>');
                }
            });
        }

        function sync() {
            var rows = items();
            rows.forEach(function(row) {
                var removeBtn = row.querySelector('[data-repeater-remove]');
                if (removeBtn) {
                    removeBtn.style.display = rows.length <= MIN_COMPONENTS ? 'none' : '';
                }
            });

            updateStockPreview();
        }

        function addItem() {
            if (!template || !addTile) return;

            var html = template.innerHTML.split('__INDEX__').join(String(next));
            next++;

            var wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            var node = wrapper.firstElementChild;

            list.insertBefore(node, addTile.parentElement);

            var select = node.querySelector('.component-product');
            if (select) initSelect2(select);

            sync();
        }

        if (addTile) {
            addTile.addEventListener('click', addItem);
        }

        // Event delegation so dynamically-added cards are covered without rebinding.
        list.addEventListener('click', function(e) {
            var removeBtn = e.target.closest('[data-repeater-remove]');
            if (!removeBtn || !list.contains(removeBtn)) return;

            var item = removeBtn.closest('[data-repeater-item]');
            if (!item) return;

            var select = item.querySelector('.component-product');
            if (select) destroySelect2(select);

            // Remove the grid column wrapper (item's parent), not just the card
            // inside it — removing only the card leaves an empty column behind
            // and throws off the grid.
            item.parentElement.remove();
            sync();
        });

        // select2 changes the value via jQuery's .trigger('change'), which — unlike
        // 'click' — isn't a native DOM method, so jQuery never actually dispatches a
        // real, bubbling 'change' event for it; a plain addEventListener delegated on
        // `list` never sees it. jQuery's own delegated handlers do, so use one here.
        if (window.jQuery) {
            jQuery(list).on('change', '.component-product', function(e) {
                var row = e.target.closest('[data-repeater-item]');
                if (row) {
                    updateStockBadge(row);
                    updateProductInfo(row);
                }
                sync();
            });
        } else {
            list.addEventListener('change', function(e) {
                if (e.target.classList.contains('component-product')) {
                    var row = e.target.closest('[data-repeater-item]');
                    if (row) {
                        updateStockBadge(row);
                        updateProductInfo(row);
                    }
                    sync();
                }
            });
        }

        items().forEach(function(row) {
            var select = row.querySelector('.component-product');
            if (select) initSelect2(select);
            updateStockBadge(row);
            updateProductInfo(row);
        });

        sync();

        return {
            addItem: addItem,
            // Tops up to the minimum of 2 cards without touching any that
            // already exist — safe to call whenever the bundle section
            // becomes visible (page load or the is_bundle checkbox).
            ensureMinimum: function() {
                while (items().length < MIN_COMPONENTS) {
                    addItem();
                }
            },
            reset: function() {
                items().forEach(function(row) {
                    var select = row.querySelector('.component-product');
                    if (select) destroySelect2(select);
                    row.parentElement.remove();
                });
                next = 0;
                sync();
            }
        };
    }

    document.addEventListener('DOMContentLoaded', function() {
        var list = document.getElementById('componentsContainer');
        if (!list) return;

        var repeater = initRepeater(list);
        window.bundleComponentsRepeater = repeater;

        var isBundleCheckbox = document.getElementById('is_bundle');
        if (isBundleCheckbox && isBundleCheckbox.checked) {
            repeater.ensureMinimum();
        }
    });
})();
</script>
@endpush
