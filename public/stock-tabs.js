(() => {
    const tabs = [...document.querySelectorAll('[data-stock-tab]')];
    function activate(tab, focus = false, updateUrl = true) {
        const key = tab.dataset.stockTab;
        for (const item of tabs) {
            const selected = item === tab;
            item.setAttribute('aria-selected', String(selected));
            item.tabIndex = selected ? 0 : -1;
            document.getElementById(item.getAttribute('aria-controls')).hidden = !selected;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('tab', key);
        if (updateUrl) window.history.replaceState(window.history.state, '', url);
        if (focus) tab.focus();
    }
    document.addEventListener('list:navigated', () => {
        const key = new URL(window.location.href).searchParams.get('tab') || 'balances';
        const tab = tabs.find(item => item.dataset.stockTab === key);
        if (tab) activate(tab, false, false);
    });
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', event => {
            if (event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            activate(tab);
        });
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            else if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else if (event.key === ' ') next = index;
            else return;
            event.preventDefault();
            activate(tabs[next], true);
        });
    });
})();

(() => {
    const form = document.querySelector('.stock-balance-filter');
    if (!form) return;
    const search = document.getElementById('stock-balance-search');
    const results = document.getElementById('stock-balance-results');
    let timer;
    function filter() {
        clearTimeout(timer);
        const url = new URL(window.location.href);
        for (const [name, value] of new FormData(form)) url.searchParams.set(name, value);
        url.searchParams.delete('balances_page');
        window.ListNavigation.navigate(url, results, { replace: true });
    }
    document.addEventListener('list:navigation-start', () => clearTimeout(timer));
    search.addEventListener('input', () => {
        window.ListNavigation.cancel();
        clearTimeout(timer);
        timer = setTimeout(filter, 250);
    });
    form.addEventListener('submit', event => { event.preventDefault(); filter(); });
})();
