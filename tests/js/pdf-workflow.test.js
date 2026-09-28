import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/pdf-workflow.js', import.meta.url), 'utf8');

// Minimal DOM adapter: exercise the actual editor events without browser dependencies.
class Element {
    constructor() {
        this.children = [];
        this.listeners = {};
        this.style = {};
        this.dataset = {};
        this.value = '';
        this.attributes = {};
        this.classList = { toggle() {} };
    }
    addEventListener(type, fn) { (this.listeners[type] ??= []).push(fn); }
    dispatchEvent(event) { for (const fn of this.listeners[event.type] ?? []) fn(event); }
    append(...children) { this.children.push(...children); }
    add(child) { if (!this.children.length) this.value = child.value; this.append(child); }
    querySelectorAll(selector) {
        const descendants = (node) => (node.children ?? []).flatMap((child) => [child, ...descendants(child)]);
        const inputs = descendants(this).filter((child) => child.tagName === 'INPUT');
        if (selector === 'input:checked') return inputs.filter((input) => input.type === 'checkbox' && input.checked);
        if (selector === 'input[type="checkbox"]') return inputs.filter((input) => input.type === 'checkbox');
        if (selector === 'input') return inputs;
        return [];
    }
    replaceChildren() { this.children = []; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return this.attributes[name] ?? null; }
}

function editor({ error = null, x = 500, y = 720, extraStep = false, pageWidth = 595, pageCount = 3, missingPages = [] } = {}) {
    const layouts = {
        qr_2cm: { width: 56.693, height: 56.693, preview: 'data:image/png;base64,qr' },
        qr_3cm: { width: 85.039, height: 85.039, preview: 'data:image/png;base64,qr3' },
        framed: error ? { error } : { width: 297.638, height: 133, preview: 'data:image/png;base64,frame' },
    };
    const steps = [{ id: 1, name_snapshot: 'Rizky Hidayat', page: 1, x, y,
        width: 56.693, height: 56.693, specimen_format: 'qr_2cm',
        specimen_scope: missingPages.length ? 'selected_pages' : 'all_pages',
        specimen_pages: missingPages.length ? [1, 2, 3] : undefined,
        specimen_positions: missingPages.length ? { 1: { x, y, width: 56.693, height: 56.693 } } : undefined,
        profile_fingerprint: 'hash', layouts }];
    if (extraStep) steps.push({ ...steps[0], id: 2, name_snapshot: 'Signer Dua', x: 350, y: 100 });
    const fields = Object.fromEntries(['active-signer', 'scope', 'page', 'page-surface', 'page-image', 'blocks',
        'position-inputs', 'position-form', 'zoom', 'preview-scroll', 'format', 'page-picker', 'confirm-positions',
        'signer-progress', 'page-checklist', 'checklist-summary', 'dimension-hint', 'reset-position'].map(key => [`[data-${key}]`, new Element()]));
    fields['[data-zoom]'].value = '1';
    fields['[data-preview-scroll]'].clientWidth = 900;
    const statuses = [new Element(), new Element()];
    const pages = Array.from({length: pageCount}, (_, index) => ({ page: index + 1, width: pageWidth, height: 842 }));
    const root = {
        dataset: { pages: JSON.stringify(pages), steps: JSON.stringify(steps), previewUrl: '/preview' },
        querySelector: selector => fields[selector],
        querySelectorAll: () => statuses,
    };
    vm.runInNewContext(source, {
        document: { readyState: 'complete', querySelectorAll: () => [], querySelector: () => root, createElement: (tagName) => { const element = new Element(); element.tagName = tagName.toUpperCase(); return element; }, createTextNode: (text) => ({ textContent: text }) },
        window: { addEventListener() {} },
        Option: function (text, value) { this.textContent = text; this.value = value; },
        Event: function (type) { this.type = type; },
    });
    const field = name => fields[`[data-${name}]`];
    const fire = (name, type) => field(name).dispatchEvent({ type });
    const select = format => { field('format').value = format; fire('format', 'change'); };
    const position = key => Number(field('position-inputs').children.find(input => input.name === `positions[0][${key}]`).value);
    const submitted = key => field('position-inputs').children.find(input => input.name === `positions[0][${key}]`)?.value;
    const pageCheckbox = (pageNumber) => field('page-picker').querySelectorAll('input[type="checkbox"]').find((input) => Number(input.value) === pageNumber);
    return { field, fire, select, position, submitted, statuses, pageCheckbox };
}

test('switching to a valid frame keeps it within the page and allows confirmation', () => {
    const ui = editor();
    assert.equal(ui.field('confirm-positions').disabled, true);
    ui.fire('page-image', 'load');
    ui.select('framed');
    assert.equal(ui.field('confirm-positions').disabled, false);
    assert.equal(ui.position('width'), 297.638);
    assert.equal(ui.position('height'), 133);
    assert.ok(ui.position('x') + ui.position('width') <= 595);
    assert.ok(ui.position('y') + ui.position('height') <= 842);
    assert.match(ui.field('blocks').children[0].children[0].src, /frame$/);
});

test('missing profile explains disabled confirmation without emitting a broken image', () => {
    const ui = editor({ error: 'Profil signer belum lengkap: pangkat.' });
    ui.fire('page-image', 'load');
    ui.select('framed');
    assert.equal(ui.field('confirm-positions').disabled, true);
    for (const status of ui.statuses) assert.match(status.textContent, /Profil atau format spesimen belum siap untuk signer 1/);
    assert.equal(ui.field('blocks').children[0].children.length, 0);
    ui.select('qr_2cm');
    assert.equal(ui.field('confirm-positions').disabled, false);
    assert.match(ui.field('blocks').children[0].children[0].src, /qr$/);
});

test('overlapping frames and pages narrower than the frame still block confirmation', () => {
    const overlap = editor({ x: 500, y: 100, extraStep: true });
    overlap.fire('page-image', 'load');
    assert.equal(overlap.field('confirm-positions').disabled, false);
    overlap.select('framed');
    assert.equal(overlap.field('confirm-positions').disabled, true);
    assert.match(overlap.statuses[1].textContent, /Perlu diperbaiki: Signer 2 \(3 halaman\)/);
    const overlapChips = overlap.field('page-checklist').children
        .flatMap((card) => card.children[1]?.children ?? []);
    assert.match(overlapChips.map((chip) => chip.textContent).join(' '), /Halaman 1 · perlu diperbaiki/);
    assert.match(overlapChips.map((chip) => chip.title).join(' '), /bertumpuk/);
    const narrow = editor({ x: 10, y: 10, pageWidth: 200 });
    narrow.fire('page-image', 'load');
    narrow.select('framed');
    assert.equal(narrow.field('confirm-positions').disabled, true);
    assert.match(narrow.statuses[1].textContent, /Perlu diperbaiki: Signer 1 \(3 halaman\)/);
    const narrowChips = narrow.field('page-checklist').children
        .flatMap((card) => card.children[1]?.children ?? []);
    assert.match(narrowChips.map((chip) => chip.textContent).join(' '), /Halaman 1 · perlu diperbaiki/);
    assert.match(narrowChips.map((chip) => chip.title).join(' '), /keluar batas/);
});

test('failed PDF preview disables confirmation and displays a recovery message', () => {
    const ui = editor();
    ui.fire('page-image', 'load');
    assert.equal(ui.field('confirm-positions').disabled, false);
    ui.fire('page-image', 'error');
    assert.equal(ui.field('confirm-positions').disabled, true);
    assert.match(ui.statuses[1].textContent, /Preview gagal dimuat/);
});


test('three centimetre QR uses fixed dimensions in the submitted positions', () => {
    const ui = editor();
    ui.fire('page-image', 'load');
    ui.select('qr_3cm');
    assert.equal(ui.position('width'), 85.039);
    assert.equal(ui.position('height'), 85.039);
    assert.equal(ui.field('confirm-positions').disabled, false);
});

test('selected-pages scope accepts multiple page checkboxes and submits them', () => {
    const ui = editor();
    ui.fire('page-image', 'load');
    ui.field('scope').value = 'selected_pages';
    ui.fire('scope', 'change');
    const pageThree = ui.pageCheckbox(3);
    pageThree.checked = true;
    pageThree.dispatchEvent({ type: 'change' });
    assert.deepEqual(ui.field('page').children.map((option) => Number(option.value)), [1, 3]);
    ui.fire('page-image', 'load');
    ui.fire('reset-position', 'click');
    const selected = ui.field('position-inputs').children
        .filter((input) => input.name === 'positions[0][specimen_pages][]')
        .map((input) => Number(input.value));
    assert.deepEqual(selected, [1, 3]);
    assert.match(ui.statuses[1].textContent, /Semua posisi QR lengkap/);
});

test('large selected-page lists stay compact inside a searchable dropdown', () => {
    const ui = editor({ pageCount: 1000 });
    ui.fire('page-image', 'load');
    ui.field('scope').value = 'selected_pages';
    ui.fire('scope', 'change');

    const picker = ui.field('page-picker');
    const dropdown = picker.children.find((child) => child.children?.some((nested) => nested.tagName === 'BUTTON'));
    const toggle = dropdown.children[0];
    const search = picker.querySelectorAll('input').find((input) => input.type === 'search');
    const panel = dropdown.children[1];

    assert.match(toggle.textContent, /1 halaman dipilih/);
    assert.equal(picker.querySelectorAll('input[type="checkbox"]').length, 1000);
    assert.equal(panel.children[2].className.includes('max-h-60'), true);
    search.value = '1000';
    search.dispatchEvent({ type: 'input' });
    assert.equal(picker.querySelectorAll('input[type="checkbox"]').length, 1);
});

test('selected-pages preview remembers the page being positioned per signer', () => {
    const ui = editor({ extraStep: true });
    ui.fire('page-image', 'load');
    ui.field('scope').value = 'selected_pages';
    ui.fire('scope', 'change');
    const pageThree = ui.pageCheckbox(3);
    pageThree.checked = true;
    pageThree.dispatchEvent({ type: 'change' });

    assert.equal(Number(ui.field('page').value), 3);
    assert.equal(Number(ui.submitted('page')), 3);

    ui.field('active-signer').value = 1;
    ui.fire('active-signer', 'change');
    ui.field('active-signer').value = 0;
    ui.fire('active-signer', 'change');
    assert.equal(Number(ui.field('page').value), 3);
});

test('page checklist identifies missing and completed positions', () => {
    const ui = editor();
    ui.fire('page-image', 'load');
    ui.field('scope').value = 'selected_pages';
    ui.fire('scope', 'change');
    const pageThree = ui.pageCheckbox(3);
    pageThree.checked = true;
    pageThree.dispatchEvent({ type: 'change' });

    assert.match(ui.field('checklist-summary').textContent, /1\/2 halaman lengkap/);
    assert.match(ui.field('page-checklist').children[0].children[0].textContent, /1\/2 halaman lengkap/);
    assert.match(ui.field('page-checklist').children[0].children[1].children[1].textContent, /Halaman 3 · belum ditempatkan/);

    ui.fire('page-image', 'load');
    ui.fire('reset-position', 'click');
    assert.match(ui.field('checklist-summary').textContent, /2\/2 halaman lengkap/);
    assert.match(ui.field('page-checklist').children[0].children[1].children[1].textContent, /Halaman 3 · lengkap/);
});

test('position errors group missing pages by signer', () => {
    const ui = editor({ missingPages: [2, 3] });
    ui.fire('page-image', 'load');

    assert.match(ui.statuses[1].textContent, /Posisi belum lengkap: Signer 1 \(2 halaman\)/);
    assert.doesNotMatch(ui.statuses[1].textContent, /posisi belum ditentukan pada halaman/);
    assert.match(ui.field('page-checklist').children[0].children[1].children[1].textContent, /Halaman 2 · belum ditempatkan/);
    assert.match(ui.field('page-checklist').children[0].children[1].children[2].textContent, /Halaman 3 · belum ditempatkan/);
});
