@push('scripts')
<script>
    (function () {
        document.querySelectorAll('.filter-multiselect').forEach(function (wrap) {
            var btn = wrap.querySelector('.filter-multiselect-toggle');
            var menu = wrap.querySelector('.filter-multiselect-menu');
            var label = wrap.querySelector('.filter-multiselect-label');
            var checkboxes = wrap.querySelectorAll('.filter-multiselect-checkbox');

            if (!btn || !menu || !label) {
                return;
            }

            function updateLabel() {
                var checkedCount = wrap.querySelectorAll('.filter-multiselect-checkbox:checked').length;
                label.textContent = checkedCount > 0 ? checkedCount + ' Selected' : label.dataset.defaultText;
            }

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('show');
            });

            menu.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', updateLabel);
            });
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('.filter-multiselect-menu.show').forEach(function (menu) {
                menu.classList.remove('show');
            });
        });
    })();
</script>
@endpush
