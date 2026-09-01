@php($preserveInitialSku = $preserveInitialSku ?? false)
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    const sizeInput = document.getElementById('size');
    const skuInput = document.getElementById('sku');
    const categorySelect = document.getElementById('category_id');
    const subCategorySelect = document.getElementById('sub_category_id');
    const brandSelect = document.getElementById('brand_id');

    if (!nameInput || !sizeInput || !skuInput || !categorySelect || !subCategorySelect) return;

    const originalSubCategories = Array.from(subCategorySelect.options);
    let isInitialLoad = @json($preserveInitialSku);

    function filterSubCategories() {
        const selectedCategoryId = categorySelect.value;
        const currentSelectedValue = subCategorySelect.value;

        subCategorySelect.innerHTML = '';
        
        if (!selectedCategoryId) {
            subCategorySelect.disabled = true;
            const defaultOpt = originalSubCategories[0].cloneNode(true);
            defaultOpt.text = 'Select Category First';
            subCategorySelect.appendChild(defaultOpt);
            subCategorySelect.value = '';
            if (!isInitialLoad) {
                generateSku();
            }
            return;
        }

        subCategorySelect.disabled = false;
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
        const brandOption = brandSelect ? brandSelect.options[brandSelect.selectedIndex] : null;
        const brandName = (brandOption && brandOption.value) ? brandOption.text : '';

        const cleanCategory = categoryName.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanSubCategory = subCategoryName.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanSize = sizeInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanBrand = brandName.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanName = nameInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();

        const catPart = cleanCategory ? cleanCategory.substring(0, 3) : '';
        const subPart = cleanSubCategory ? cleanSubCategory.substring(0, 3) : '';
        const sizePart = cleanSize || '';   
        const brandPart = cleanBrand ? cleanBrand.substring(0, 3) : '';
        const namePart = cleanName ? cleanName.substring(0, 3) : '';

        let sku = catPart;
        if (subPart) {
            sku += '_' + subPart;
        }
        if (brandPart) {
            sku += '_' + brandPart;
        }
        if (namePart) {
            sku += '_' + namePart;
        }
        if (sizePart) {
            sku += '_' + sizePart;
        }

        skuInput.value = sku;
    }

    nameInput.addEventListener('input', generateSku);
    sizeInput.addEventListener('input', generateSku);
    categorySelect.addEventListener('change', filterSubCategories);
    subCategorySelect.addEventListener('change', generateSku);
    if (brandSelect) {
        brandSelect.addEventListener('change', generateSku);
    }

    filterSubCategories();
    isInitialLoad = false;
});
</script>
