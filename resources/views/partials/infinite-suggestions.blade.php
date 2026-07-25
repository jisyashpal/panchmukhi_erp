{{-- Shared infinite-scroll helper for product search suggestion lists --}}
<script>
window.PanchmukhiInfiniteSuggestions = window.PanchmukhiInfiniteSuggestions || (function () {
    const DEFAULT_PAGE_SIZE = 25;

    function create(box, options) {
        options = options || {};
        const pageSize = options.pageSize || DEFAULT_PAGE_SIZE;
        const renderItem = options.renderItem;
        const renderTail = options.renderTail || null;
        let results = [];
        let offset = 0;

        function clearFooter() {
            const existing = box.querySelector('.suggestion-footer');
            if (existing) {
                existing.remove();
            }
        }

        function clearTail() {
            const existing = box.querySelector('.suggestion-tail');
            if (existing) {
                existing.remove();
            }
        }

        function updateFooter() {
            clearFooter();
            if (!results.length) {
                return;
            }

            const footer = document.createElement('div');
            footer.className = 'suggestion-footer px-3 py-2 small text-muted bg-light border-top';
            footer.textContent = offset < results.length
                ? ('Showing ' + offset + ' of ' + results.length + ' — scroll for more')
                : (results.length + ' product' + (results.length === 1 ? '' : 's'));
            box.appendChild(footer);
        }

        function appendTail() {
            clearTail();
            if (typeof renderTail !== 'function') {
                return;
            }
            const tail = renderTail();
            if (!tail) {
                return;
            }
            tail.classList.add('suggestion-tail');
            box.appendChild(tail);
        }

        function loadMore() {
            if (offset >= results.length) {
                return;
            }

            clearFooter();
            clearTail();

            const next = results.slice(offset, offset + pageSize);
            offset += next.length;
            next.forEach(function (item) {
                box.appendChild(renderItem(item));
            });

            updateFooter();
            if (offset >= results.length) {
                appendTail();
            }
        }

        function setResults(list) {
            results = Array.isArray(list) ? list : [];
            offset = 0;
            box.innerHTML = '';

            if (!results.length) {
                appendTail();
                return results.length > 0 || (typeof renderTail === 'function' && box.children.length > 0);
            }

            loadMore();
            return true;
        }

        function reset() {
            results = [];
            offset = 0;
            box.innerHTML = '';
        }

        if (!box.dataset.infiniteScrollBound) {
            box.dataset.infiniteScrollBound = '1';
            box.addEventListener('scroll', function () {
                if (box.scrollTop + box.clientHeight >= box.scrollHeight - 24) {
                    loadMore();
                }
            });
        }

        return {
            setResults: setResults,
            reset: reset,
            loadMore: loadMore,
        };
    }

    return { create: create };
})();
</script>
