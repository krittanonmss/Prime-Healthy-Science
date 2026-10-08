(() => {
    const pagePath = window.location.pathname;
    let pending;
    const feedback = new Map();

    function message(region, text, retry) {
        let status = feedback.get(region.id);
        if (!status) {
            status = document.createElement('div');
            status.className = 'list-navigation-status';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            region.before(status);
            feedback.set(region.id, status);
        }
        status.replaceChildren();
        status.hidden = !text;
        if (!text) return;
        status.append(document.createTextNode(text));
        if (retry) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Retry';
            button.addEventListener('click', retry);
            status.append(button);
        }
    }

    function cancel() {
        if (!pending) return;
        pending.controller.abort();
        for (const region of pending.regions) {
            region.removeAttribute('aria-busy');
            message(region, '');
        }
        pending = null;
    }

    async function navigate(target, region, options = {}) {
        const url = new URL(target, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== pagePath) return;
        cancel();
        document.dispatchEvent(new CustomEvent('list:navigation-start'));
        const regions = region ? [region] : [...document.querySelectorAll('[data-ajax-region]')];
        if (!regions.length) return;
        const request = { controller: new AbortController(), regions };
        pending = request;
        for (const item of regions) {
            item.setAttribute('aria-busy', 'true');
            message(item, 'Loading…');
        }
        try {
            const response = await fetch(url, { signal: request.controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Could not load page');
            const finalUrl = new URL(response.url || url, window.location.href);
            if (finalUrl.pathname !== pagePath) throw new Error('Unexpected page response');
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (pending !== request) return;
            const replacements = regions.map(item => parsed.getElementById(item.id));
            if (replacements.some(item => !item)) throw new Error('Missing list');
            for (const [index, item] of regions.entries()) {
                const scrolls = [...item.querySelectorAll('.table-card, .stock-table-scroll')].map(node => node.scrollLeft);
                item.replaceChildren(...replacements[index].childNodes);
                [...item.querySelectorAll('.table-card, .stock-table-scroll')].forEach((node, i) => { node.scrollLeft = scrolls[i] || 0; });
                message(item, '');
            }
            if (!options.pop) {
                const activeTab = document.querySelector('[data-stock-tab][aria-selected="true"]');
                if (region?.dataset.queryKeys && activeTab) finalUrl.searchParams.set('tab', activeTab.dataset.stockTab);
                const state = { ...window.history.state, listNavigation: true };
                if (options.replace) window.history.replaceState(state, '', finalUrl);
                else window.history.pushState(state, '', finalUrl);
            }
            document.dispatchEvent(new CustomEvent('list:navigated'));
            if (options.focus && region) region.querySelector('.orders-pagination [aria-current="page"]')?.focus({ preventScroll: true });
        } catch (error) {
            if (error.name !== 'AbortError' && pending === request) {
                for (const item of regions) message(item, 'Unable to load this page. Your current list is unchanged. ', () => navigate(url, region, options));
            }
        } finally {
            if (pending === request) {
                for (const item of regions) item.removeAttribute('aria-busy');
                pending = null;
            }
        }
    }

    document.addEventListener('click', event => {
        const link = event.target.closest?.('.orders-pagination a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        const region = link.closest('[data-ajax-region]');
        if (!region) return;
        let url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== pagePath) return;
        event.preventDefault();
        if (region.dataset.queryKeys) {
            const merged = new URL(window.location.href);
            for (const key of region.dataset.queryKeys.split(',')) {
                merged.searchParams.delete(key);
                if (url.searchParams.has(key)) merged.searchParams.set(key, url.searchParams.get(key));
            }
            merged.searchParams.set('tab', url.searchParams.get('tab') || 'balances');
            merged.searchParams.delete('page');
            url = merged;
        }
        if (link.getAttribute('aria-current') === 'page') return;
        navigate(url, region, { focus: event.detail === 0 });
    });
    window.addEventListener('popstate', () => {
        if (window.location.pathname === pagePath && document.querySelector('[data-ajax-region]')) navigate(window.location.href, null, { pop: true });
    });
    window.ListNavigation = { navigate, cancel };
})();
