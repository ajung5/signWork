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
        this.classList = { toggle() {} };
    }
    addEventListener(type, fn) { (this.listeners[type] ??= []).push(fn); }
    dispatchEvent(event) { for (const fn of this.listeners[event.type] ?? []) fn(event); }
    append(child) { this.children.push(child); }
    add(child) { if (!this.children.length) this.value = child.value; this.append(child); }
    querySelectorAll(selector) {
        if (selector === 'input:checked') return this.children.flatMap((child) => child.children ?? []).filter((child) => child.type === 'checkbox' && child.checked);
        return [];
    }
    replaceChildren() { this.children = []; }
    setAttribute() {}
}

function editor({ error = null, x = 500, y = 720, extraStep = false, pageWidth = 595 } = {}) {
    const layouts = {
        qr_2cm: { width: 56.693, height: 56.693, preview: 'data:image/png;base64,qr' },
        qr_3cm: { width: 85.039, height: 85.039, preview: 'data:image/png;base64,qr3' },
        framed: error ? { error } : { width: 297.638, height: 133, preview: 'data:image/png;base64,frame' },
    };
    const steps = [{ id: 1, name_snapshot: 'Rizky Hidayat', page: 1, x, y,
        width: 56.693, height: 56.693, specimen_format: 'qr_2cm', specimen_scope: 'all_pages', profile_fingerprint: 'hash', layouts }];
    if (extraStep) steps.push({ ...steps[0], id: 2, name_snapshot: 'Signer Dua', x: 350, y: 100 });
    const fields = Object.fromEntries(['active-signer', 'scope', 'page', 'page-surface', 'page-image', 'blocks',
        'position-inputs', 'position-form', 'zoom', 'preview-scroll', 'format', 'page-picker', 'confirm-positions',
        'signer-progress', 'dimension-hint', 'reset-position'].map(key => [`[data-${key}]`, new Element()]));
    fields['[data-zoom]'].value = '1';
    fields['[data-preview-scroll]'].clientWidth = 900;
    const statuses = [new Element(), new Element()];
    const root = {
        dataset: { pages: JSON.stringify([{ page: 1, width: pageWidth, height: 842 }, { page: 2, width: pageWidth, height: 842 }, { page: 3, width: pageWidth, height: 842 }]), steps: JSON.stringify(steps), previewUrl: '/preview' },
        querySelector: selector => fields[selector],
        querySelectorAll: () => statuses,
    };
    vm.runInNewContext(source, {
        document: { readyState: 'complete', querySelectorAll: () => [], querySelector: () => root, createElement: () => new Element(), createTextNode: (text) => ({ textContent: text }) },
        window: { addEventListener() {} },
        Option: function (text, value) { this.textContent = text; this.value = value; },
        Event: function (type) { this.type = type; },
    });
    const field = name => fields[`[data-${name}]`];
    const fire = (name, type) => field(name).dispatchEvent({ type });
    const select = format => { field('format').value = format; fire('format', 'change'); };
    const position = key => Number(field('position-inputs').children.find(input => input.name === `positions[0][${key}]`).value);
    return { field, fire, select, position, statuses };
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
    for (const status of ui.statuses) assert.match(status.textContent, /Rizky Hidayat.*pangkat/);
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
    assert.match(overlap.statuses[1].textContent, /bertumpuk/);
    const narrow = editor({ x: 10, y: 10, pageWidth: 200 });
    narrow.fire('page-image', 'load');
    narrow.select('framed');
    assert.equal(narrow.field('confirm-positions').disabled, true);
    assert.match(narrow.statuses[1].textContent, /keluar batas/);
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
    const pageThree = ui.field('page-picker').children.find((label) => label.children?.[0]?.value === 3);
    pageThree.children[0].checked = true;
    pageThree.children[0].dispatchEvent({ type: 'change' });
    const selected = ui.field('position-inputs').children
        .filter((input) => input.name === 'positions[0][specimen_pages][]')
        .map((input) => Number(input.value));
    assert.deepEqual(selected, [1, 3]);
    assert.match(ui.statuses[1].textContent, /Posisi lengkap/);
});
