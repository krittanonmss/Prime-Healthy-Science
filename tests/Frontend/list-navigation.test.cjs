const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/list-navigation.js', 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

// Minimal DOM and response doubles test the shared navigation coordinator without a browser dependency.
function setup(path, specs) {
    class Element {
        constructor(id = '') { this.id = id; this.dataset = {}; this.attrs = {}; this.childNodes = []; this.events = {}; }
        setAttribute(key, value) { this.attrs[key] = String(value); }
        getAttribute(key) { return this.attrs[key] ?? null; }
        removeAttribute(key) { delete this.attrs[key]; }
        replaceChildren(...nodes) { this.childNodes = nodes; }
        append(node) { this.childNodes.push(node); }
        before(node) { this.feedback = node; }
        addEventListener(key, handler) { this.events[key] = handler; }
        querySelectorAll() { return []; }
        querySelector() { return null; }
    }
    const regions = specs.map(spec => Object.assign(new Element(spec.id), { dataset: spec.dataset || {}, childNodes: ['original'] }));
    const handlers = {};
    const windowHandlers = {};
    const requests = [];
    const historyEntries = [path];
    const location = { origin: 'http://localhost', pathname: new URL(path, 'http://localhost').pathname, href: new URL(path, 'http://localhost').href, assign() { throw new Error('Document navigation is forbidden'); } };
    let activeTab = path.startsWith('/stock') ? { dataset: { stockTab: 'balances' } } : null;
    const document = {
        createElement: () => new Element(), createTextNode: text => text,
        addEventListener: (name, handler) => { handlers[name] = handler; },
        dispatchEvent: event => handlers[event.type]?.(event),
        querySelectorAll: () => regions,
        querySelector: selector => selector.includes('data-stock-tab') ? activeTab : regions[0],
    };
    const updateUrl = url => { location.href = new URL(url, location.href).href; };
    const history = { state: null, pushState(state, _, url) { this.state = state; historyEntries.push(String(url)); updateUrl(url); }, replaceState(state, _, url) { this.state = state; historyEntries[historyEntries.length - 1] = String(url); updateUrl(url); } };
    class DOMParser {
        parseFromString(html) {
            const values = JSON.parse(html);
            return { getElementById(id) { if (!(id in values)) return null; const item = new Element(id); item.childNodes = [values[id]]; return item; } };
        }
    }
    const fetch = (url, options) => new Promise((resolve, reject) => {
        const request = { url: new URL(url), options, resolve(values) { resolve({ ok: true, url: String(url), text: async () => JSON.stringify(values) }); }, fail() { reject(new Error('Offline')); } };
        options.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
        requests.push(request);
    });
    const window = { location, history, addEventListener: (key, handler) => { windowHandlers[key] = handler; } };
    vm.runInNewContext(source, { window, document, URL, AbortController, DOMParser, fetch, CustomEvent: class { constructor(type) { this.type = type; } } });
    function click(region, href, modifiers = {}) {
        const link = { href, target: '', hasAttribute: () => false, getAttribute: () => null, closest: () => region };
        const event = { target: { closest: () => link }, button: 0, detail: 1, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...modifiers };
        handlers.click(event);
        return event;
    }
    return { regions, requests, window, historyEntries, click, async settle(values) { requests.at(-1).resolve(values); await tick(); }, async pop(url) { updateUrl(url); windowHandlers.popstate(); await tick(); }, setTab(key) { activeTab = { dataset: { stockTab: key } }; } };
}

for (const [path, id, pageKey, dataset] of [
    ['/orders?q=Demo&channel=retail', 'orders-results', 'page', {}],
    ['/products?q=Foam', 'products-results', 'page', {}],
    ['/customers?q=clinic', 'customers-results', 'page', {}],
    ['/reports?channel=retail', 'reports-results', 'page', {}],
    ['/stock?tab=balances&balance_q=Foam&history_page=3', 'stock-balance-results', 'balances_page', { queryKeys: 'balance_q,balance_per_page,balances_page' }],
    ['/stock?tab=history&product_id=3&balances_page=2', 'stock-history-results', 'history_page', { queryKeys: 'product_id,history_q,history_per_page,history_page' }],
]) {
    test(`${id}: intercepts pagination, fetches results, preserves query and updates URL`, async () => {
        const app = setup(path, [{ id, dataset }]);
        if (path.includes('tab=history')) app.setTab('history');
        const target = new URL(path, 'http://localhost'); target.searchParams.set(pageKey, '2');
        const event = app.click(app.regions[0], target.href);
        assert.equal(event.defaultPrevented, true);
        assert.equal(app.requests.length, 1);
        assert.equal(app.requests[0].url.searchParams.get(pageKey), '2');
        assert.equal(app.regions[0].getAttribute('aria-busy'), 'true');
        await app.settle({ [id]: 'page two' });
        assert.deepEqual(app.regions[0].childNodes, ['page two']);
        assert.equal(new URL(app.window.location.href).searchParams.get(pageKey), '2');
        assert.equal(app.historyEntries.length, 2);
        assert.equal(app.regions[0].getAttribute('aria-busy'), null);
    });
}

test('Stock pagers retain the other tab’s current page instead of stale link parameters', async () => {
    const app = setup('/stock?tab=history&balances_page=4&balance_q=Foam', [{ id: 'stock-history-results', dataset: { queryKeys: 'product_id,history_q,history_per_page,history_page' } }]);
    app.setTab('history');
    app.click(app.regions[0], 'http://localhost/stock?tab=history&balances_page=1&history_page=2');
    assert.equal(app.requests[0].url.searchParams.get('balances_page'), '4');
    assert.equal(app.requests[0].url.searchParams.get('balance_q'), 'Foam');
    await app.settle({ 'stock-history-results': 'history two' });
});

test('Rapid clicks abort stale requests and only the latest response changes the list', async () => {
    const app = setup('/orders', [{ id: 'orders-results' }]);
    app.click(app.regions[0], 'http://localhost/orders?page=2');
    const first = app.requests[0];
    app.click(app.regions[0], 'http://localhost/orders?page=3');
    assert.equal(first.options.signal.aborted, true);
    await app.settle({ 'orders-results': 'page three' });
    first.resolve({ 'orders-results': 'stale page two' });
    await tick();
    assert.deepEqual(app.regions[0].childNodes, ['page three']);
    assert.equal(new URL(app.window.location.href).searchParams.get('page'), '3');
});

test('Failures preserve list and URL and provide a retry without document navigation', async () => {
    const app = setup('/orders', [{ id: 'orders-results' }]);
    app.click(app.regions[0], 'http://localhost/orders?page=2');
    app.requests[0].fail(); await tick();
    assert.deepEqual(app.regions[0].childNodes, ['original']);
    assert.equal(app.historyEntries.length, 1);
    const retry = app.regions[0].feedback.childNodes.at(-1);
    assert.equal(retry.textContent, 'Retry');
    retry.events.click();
    await app.settle({ 'orders-results': 'retried page two' });
    assert.deepEqual(app.regions[0].childNodes, ['retried page two']);
});

test('Back and forward refresh both Stock regions from one response without adding history entries', async () => {
    const app = setup('/stock?tab=balances', [{ id: 'stock-balance-results' }, { id: 'stock-history-results' }]);
    await app.pop('http://localhost/stock?tab=history&history_page=2');
    await app.settle({ 'stock-balance-results': 'balances', 'stock-history-results': 'history two' });
    assert.deepEqual(app.regions[1].childNodes, ['history two']);
    assert.equal(app.historyEntries.length, 1);
});

test('Modified clicks keep native open-in-new-tab behavior', () => {
    const app = setup('/orders', [{ id: 'orders-results' }]);
    const event = app.click(app.regions[0], 'http://localhost/orders?page=2', { ctrlKey: true });
    assert.equal(event.defaultPrevented, false);
    assert.equal(app.requests.length, 0);
});
