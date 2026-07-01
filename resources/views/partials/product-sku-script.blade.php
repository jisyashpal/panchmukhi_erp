@php($preserveInitialSku = $preserveInitialSku ?? false)
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    const sizeInput = document.getElementById('size');
    const skuInput = document.getElementById('sku');
    const categorySelect = document.getElementById('category_id');
    const subCategorySelect = document.getElementById('sub_category_id');

    if (!nameInput || !sizeInput || !skuInput || !categorySelect || !subCategorySelect) return;

    const originalSubCategories = Array.from(subCategorySelect.options);
    let isInitialLoad = @json($preserveInitialSku);

    function filterSubCategories() {
        const selectedCategoryId = categorySelect.value;
        const currentSelectedValue = subCategorySelect.value;

        subCategorySelect.innerHTML = '';
        subCategorySelect.appendChild(originalSubCategories[0]);

        let selectedStillValid = false;
        originalSubCategories.slice(1).forEach(option => {
            if (option.dataset.categoryId === selectedCategoryId) {
                subCategorySelect.appendChild(option);
                if (option.value === currentSelectedValue) {
                    selectedStillValid = true;
                }
            }
        });

        subCategorySelect.value = selectedStillValid ? currentSelectedValue : '';

        if (!isInitialLoad) {
            generateSku();
        }
    }

    function generateSku() {
        const categoryOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryName = categoryOption ? categoryOption.text : '';
        const subCategoryOption = subCategorySelect.options[subCategorySelect.selectedIndex];
        const subCategoryName = (subCategoryOption && subCategoryOption.value) ? subCategoryOption.text : '';

        const cleanName = nameInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanCategory = categoryName.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanSubCategory = subCategoryName.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanSize = sizeInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();

        let sku = cleanCategory.substring(0, 3) + '_' + cleanName.substring(0, 3);
        if (cleanSubCategory) {
            sku += '_' + cleanSubCategory.substring(0, 3);
        }
        if (cleanSize) {
            sku += '_' + cleanSize.substring(0, 3);
        }

        skuInput.value = sku;
    }

    nameInput.addEventListener('input', generateSku);
    sizeInput.addEventListener('input', generateSku);
    categorySelect.addEventListener('change', filterSubCategories);
    subCategorySelect.addEventListener('change', generateSku);

    filterSubCategories();
    isInitialLoad = false;
});
</script>
