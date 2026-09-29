<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
    function addDynamicRow(containerId, rowHtml) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const index = container.querySelectorAll('.dynamic-row').length;
        const div = document.createElement('div');
        div.className = 'dynamic-row row g-2 mb-2 align-items-end';
        div.innerHTML = rowHtml.replace(/__INDEX__/g, index);
        container.appendChild(div);
    }

    function removeDynamicRow(button) {
        const row = button.closest('.dynamic-row');
        const container = row?.parentElement;
        if (row && container && container.querySelectorAll('.dynamic-row').length > 1) {
            row.remove();
            container.querySelectorAll('.dynamic-row').forEach((el, i) => {
                el.querySelectorAll('[name]').forEach(input => {
                    input.name = input.name.replace(/\[\d+\]/, '[' + i + ']');
                });
            });
        }
    }

    function initSearchableItemSelect(select) {
        if (!select || select.dataset.itemSearchReady === '1') return;
        select.dataset.itemSearchReady = '1';

        const wrap = document.createElement('div');
        wrap.className = 'searchable-item-select-wrap position-relative';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control searchable-item-select-input' + (select.classList.contains('form-select-sm') ? ' form-control-sm' : '');
        input.placeholder = 'Enter item name or SKU...';
        input.autocomplete = 'off';
        input.setAttribute('aria-label', 'Search Item Name / SKU');

        const box = document.createElement('div');
        box.className = 'item-sku-suggestions shadow border rounded-bottom bg-white';

        wrap.insertBefore(input, select);
        wrap.appendChild(box);
        select.classList.add('searchable-item-select-native');

        let requiredDesired = select.required;

        function syncFromSelect() {
            const hidden = select.classList.contains('d-none');
            wrap.classList.toggle('d-none', hidden);
            if (select.required) {
                requiredDesired = true;
                select.required = false;
            }
            input.required = requiredDesired && !hidden && !select.disabled;
            input.disabled = select.disabled || hidden;
            const option = select.options[select.selectedIndex];
            input.value = option && option.value ? optionLabel(option) : '';
        }

        function optionLabel(option) {
            const name = option.dataset.name || option.textContent.trim();
            const meta = [option.dataset.category, option.dataset.itemType].filter(Boolean).join(' / ');
            const sku = option.dataset.sku || '';
            if (!meta && !sku) return name;
            return name + (meta ? ' — ' + meta : '') + (sku ? ' (' + sku + ')' : '');
        }

        function placeBox() {
            const rect = input.getBoundingClientRect();
            box.style.position = 'fixed';
            box.style.left = rect.left + 'px';
            box.style.top = (rect.bottom + 2) + 'px';
            box.style.width = Math.max(rect.width, 220) + 'px';
            box.style.right = 'auto';
            box.style.zIndex = '2000';
            if (box.parentNode !== document.body) {
                document.body.appendChild(box);
            }
        }

        function render(query) {
            const q = query.trim().toLowerCase();
            const matches = Array.from(select.options).filter(function (option) {
                if (!option.value) return false;
                const name = (option.dataset.name || option.textContent).toLowerCase();
                const sku = (option.dataset.sku || '').toLowerCase();
                const category = (option.dataset.category || '').toLowerCase();
                const itemType = (option.dataset.itemType || '').toLowerCase();
                return !q || name.includes(q) || sku.includes(q) || category.includes(q) || itemType.includes(q);
            }).slice(0, 40);

            box.innerHTML = '';
            if (!matches.length) {
                box.classList.remove('is-open');
                return;
            }
            placeBox();

            matches.forEach(function (option) {
                const item = document.createElement('div');
                item.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                const name = document.createElement('span');
                name.className = 'fw-semibold text-dark d-block text-truncate';
                name.style.maxWidth = '100%';
                name.textContent = option.dataset.name || option.textContent.trim();
                const sku = document.createElement('span');
                sku.className = 'text-muted small';
                const meta = [option.dataset.category, option.dataset.itemType].filter(Boolean).join(' / ');
                sku.textContent = (meta ? meta + ' · ' : '') + (option.dataset.sku || 'No SKU');
                item.append(name, document.createTextNode(' '), sku);
                item.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    select.value = option.value;
                    input.value = optionLabel(option);
                    input.setCustomValidity('');
                    box.classList.remove('is-open');
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                box.appendChild(item);
            });
            box.classList.add('is-open');
        }

        input.addEventListener('focus', function () {
            render(input.value);
        });
        window.addEventListener('scroll', function (event) {
            if (!box.classList.contains('is-open') || box.contains(event.target)) {
                return;
            }
            placeBox();
        }, true);
        window.addEventListener('resize', function () {
            if (box.classList.contains('is-open')) {
                placeBox();
            }
        });
        input.addEventListener('input', function () {
            input.setCustomValidity('');
            if (!input.value.trim()) {
                if (select.value !== '') {
                    select.value = '';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
                const row = select.closest('.dynamic-row, tr, form');
                if (row && select.classList.contains('item-product-select')) {
                    const catSelect = row.querySelector('.item-category-select');
                    const itemTypeSelect = row.querySelector('.item-subcategory-select');
                    if (catSelect) {
                        catSelect.value = '';
                        catSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (itemTypeSelect) {
                        itemTypeSelect.value = '';
                        itemTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
            render(input.value);
        });
        input.addEventListener('blur', function () {
            window.setTimeout(function () {
                box.classList.remove('is-open');
                const option = select.options[select.selectedIndex];
                input.value = option && option.value ? optionLabel(option) : '';
            }, 150);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                box.classList.remove('is-open');
            }
            if (event.key === 'Enter') {
                const first = box.querySelector('.suggestion-item');
                if (first && box.classList.contains('is-open')) {
                    event.preventDefault();
                    first.dispatchEvent(new MouseEvent('mousedown'));
                }
            }
        });

        if (select.form) {
            select.form.addEventListener('submit', function (event) {
                if (input.disabled || !input.required) return;
                if (!select.value) {
                    input.setCustomValidity('Select an item from the list');
                    input.reportValidity();
                    event.preventDefault();
                }
            });
        }

        new MutationObserver(syncFromSelect).observe(select, {
            childList: true,
            attributes: true,
            attributeFilter: ['disabled', 'required', 'class'],
        });

        syncFromSelect();
    }

    function initSearchableItemSelects(root) {
        (root || document).querySelectorAll('select.searchable-item-select').forEach(initSearchableItemSelect);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSearchableItemSelects(document);
        new MutationObserver(function () {
            initSearchableItemSelects(document);
        }).observe(document.body, { childList: true, subtree: true });
    });
</script>
