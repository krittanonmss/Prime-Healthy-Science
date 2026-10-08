(() => {
    let nextId = 0;
    const normalize = value => value.normalize('NFKC').toLocaleLowerCase().trim();

    function enhance(select) {
        if (select.dataset.searchReady) return;
        select.dataset.searchReady = 'true';
        const wrapper = document.createElement('div');
        wrapper.className = 'search-select';
        select.before(wrapper);
        wrapper.append(select);
        select.classList.add('search-select-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'search-select-input';
        input.autocomplete = 'off';
        input.placeholder = select.dataset.placeholder || 'พิมพ์ค้นหา / เลือกรายการ…';
        const label = wrapper.previousElementSibling;
        input.setAttribute('aria-label', select.dataset.searchLabel || label?.textContent || 'ค้นหาและเลือกรายการ');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-haspopup', 'listbox');
        const list = document.createElement('div');
        list.id = `search-options-${++nextId}`;
        list.className = 'search-select-options';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        input.setAttribute('aria-controls', list.id);
        wrapper.append(input, list);
        let matches = [];
        let active = -1;
        let opened = false;

        function sync() {
            input.value = select.selectedOptions[0]?.textContent || '';
            input.disabled = select.disabled || !select.options.length;
            input.setAttribute('aria-required', String(select.required));
        }
        function close() {
            opened = false;
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            sync();
        }
        function activate(index) {
            active = index;
            [...list.children].forEach((item, i) => item.classList.toggle('is-active', i === active));
            if (list.children[active] && matches.length) {
                input.setAttribute('aria-activedescendant', list.children[active].id);
                list.children[active].scrollIntoView({ block: 'nearest' });
            } else input.removeAttribute('aria-activedescendant');
        }
        function choose(option) {
            select.value = option.value;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            close();
            input.focus();
        }
        function render(term = '') {
            const words = normalize(term).split(/\s+/).filter(Boolean);
            matches = [...select.options].filter(option => !option.disabled && !option.hidden && words.every(word => normalize(option.textContent + ' ' + (option.dataset.search || '')).includes(word)));
            list.replaceChildren();
            for (const [index, option] of matches.entries()) {
                const item = document.createElement('div');
                item.id = `${list.id}-${index}`;
                item.className = 'search-select-option';
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', String(option.selected));
                item.textContent = option.textContent;
                item.addEventListener('mousedown', event => event.preventDefault());
                item.addEventListener('click', () => choose(option));
                list.append(item);
            }
            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'search-select-empty';
                empty.setAttribute('role', 'status');
                empty.textContent = select.dataset.emptyMessage || 'ไม่พบรายการที่ตรงกับคำค้น';
                list.append(empty);
            }
            active = -1;
            input.removeAttribute('aria-activedescendant');
            opened = true;
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
        input.addEventListener('focus', () => { render(); input.select(); });
        input.addEventListener('click', () => { if (!opened) { render(); input.select(); } });
        input.addEventListener('input', () => render(input.value));
        input.addEventListener('blur', close);
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(); }
            else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (!opened) render();
                if (matches.length) activate(event.key === 'ArrowDown' ? (active + 1) % matches.length : (active <= 0 ? matches.length - 1 : active - 1));
            } else if (event.key === 'Enter' && opened) {
                event.preventDefault();
                if (matches.length) choose(matches[active < 0 ? 0 : active]);
            }
        });
        select.addEventListener('change', sync);
        select.addEventListener('invalid', event => { event.preventDefault(); input.focus(); });
        select.form?.addEventListener('reset', () => setTimeout(close, 0));
        new MutationObserver(() => { if (opened) render(input.value); else sync(); }).observe(select, { childList: true, subtree: true, attributes: true, characterData: true });
        sync();
    }
    const scan = () => document.querySelectorAll('select[data-searchable]').forEach(enhance);
    document.addEventListener('DOMContentLoaded', () => {
        scan();
        new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
        document.querySelectorAll('[data-list-search]').forEach(input => {
            input.addEventListener('input', () => {
                const words = normalize(input.value).split(/\s+/).filter(Boolean);
                const target = document.getElementById(input.dataset.listSearch);
                let visible = 0;
                target.querySelectorAll('[data-search-item]').forEach(item => {
                    item.hidden = !words.every(word => normalize(item.textContent).includes(word));
                    if (!item.hidden) visible++;
                });
                const status = document.getElementById(input.dataset.listSearch + '-status');
                if (status) status.textContent = input.dataset.searchLanguage === 'en' ? (visible ? `${visible} matching orders (checked orders remain selected)` : 'No matching orders found.') : (visible ? `พบ ${visible} รายการ (รายการที่เลือกไว้ยังคงอยู่)` : 'ไม่พบรายการที่ตรงกับคำค้น');
            });
        });
    });
})();
