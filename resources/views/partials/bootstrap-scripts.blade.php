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
</script>
