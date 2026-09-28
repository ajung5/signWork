const pointsPerCm = 72 / 2.54;
const footerHeight = 34.016;

const ready = (fn) => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn();

ready(() => {
    document.querySelectorAll('[data-participant-list]').forEach((list) => {
        const rows = list.querySelector('[data-rows]');
        const number = () => [...rows.children].forEach((row, i) => row.querySelector('[data-order]').textContent = i + 1);
        list.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const row = button.closest('[data-row]');
            if (button.hasAttribute('data-add') && rows.children.length < 10) {
                const clone = rows.firstElementChild.cloneNode(true);
                clone.querySelector('select').value = '';
                rows.append(clone);
            }
            if (button.hasAttribute('data-remove') && rows.children.length > 1) row.remove();
            if (button.hasAttribute('data-up') && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
            if (button.hasAttribute('data-down') && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
            number();
        });
    });

    const root = document.querySelector('[data-placement]');
    if (!root) return;
    const pages = JSON.parse(root.dataset.pages);
    const steps = JSON.parse(root.dataset.steps);
    const signerSelect = root.querySelector('[data-active-signer]');
    const scope = root.querySelector('[data-scope]');
    const pageSelect = root.querySelector('[data-page]');
    const pagePicker = root.querySelector('[data-page-picker]');
    const surface = root.querySelector('[data-page-surface]');
    const preview = root.querySelector('[data-page-image]');
    const blocks = root.querySelector('[data-blocks]');
    const inputs = root.querySelector('[data-position-inputs]');
    const statuses = root.querySelectorAll('[data-placement-status]');
    const setStatus = (message, invalid = false) => statuses.forEach(status => {
        status.textContent = message;
        status.classList.toggle('text-red-700', invalid);
        status.classList.toggle('text-blue-800', !invalid);
    });
    const form = root.querySelector('[data-position-form]');
    const zoom = root.querySelector('[data-zoom]');
    const scroller = root.querySelector('[data-preview-scroll]');
    const format = root.querySelector('[data-format]');
    const fit = () => { surface.style.width = `${Math.max(240, Math.min(760, scroller.clientWidth - 24)) * Number(zoom.value)}px`; };
    const selectedPageNumbers = (step) => {
        if (step.specimen_scope === 'all_pages') return pages.map((item) => Number(item.page));
        if (step.specimen_scope === 'selected_pages') return [...new Set((step.specimen_pages || []).map(Number).filter(Number.isFinite))].sort((a, b) => a - b);
        return step.page ? [Number(step.page)] : [];
    };
    const hasPage = (step, pageNumber) => selectedPageNumbers(step).includes(Number(pageNumber));
    const ensureCurrentSelected = (step) => {
        const current = Number(pageSelect.value);
        step.specimen_pages = [...new Set([...selectedPageNumbers(step), current].filter((value) => value > 0))].sort((a, b) => a - b);
        step.page = step.specimen_pages[0] || current;
    };
    const issues = () => {
        const problems = [];
        steps.forEach((s, i) => {
            const choice = s.layouts[s.specimen_format];
            if (!choice || choice.error) { problems.push(`Signer ${i + 1} (${s.name_snapshot}): ${choice?.error || 'Pilih format spesimen'}`); return; }
            const targetPageNumbers = selectedPageNumbers(s);
            const targetPages = pages.filter(p => targetPageNumbers.includes(Number(p.page)));
            if (s.specimen_scope === 'selected_pages' && targetPageNumbers.length === 0) { problems.push(`Signer ${i + 1}: pilih minimal satu halaman`); return; }
            const p = targetPages[0];
            if (!p || s.x === null || s.y === null) { problems.push(`Signer ${i + 1}: posisi belum ditentukan`); return; }
            if (![s.x, s.y, s.width, s.height].every(v => Number.isFinite(Number(v))) || targetPages.some(page => s.x < 0 || s.y < 0 || s.x + s.width > page.width + 0.01 || s.y + s.height > page.height + 0.01)) problems.push(`Signer ${i + 1}: ukuran atau posisi keluar batas pada salah satu halaman`);
            steps.slice(0, i).forEach((t, j) => {
                const sharedPages = pages.filter(page => hasPage(s, page.page) && hasPage(t, page.page));
                if (sharedPages.length && t.x !== null && t.y !== null && s.x < t.x + t.width && s.x + s.width > t.x && s.y < t.y + t.height && s.y + s.height > t.y) problems.push(`Signer ${j + 1} dan ${i + 1}: blok bertumpuk`);
            });
        });
        return problems;
    };
    steps.forEach(s => ['x','y','width','height'].forEach(key => { if (s[key] !== null) s[key] = Number(s[key]); }));
    steps.forEach(s => {
        s.specimen_format = !s.specimen_format || s.specimen_format === 'qr_only' ? 'qr_2cm' : s.specimen_format;
        if (s.specimen_scope === 'selected_page') {
            s.specimen_scope = 'selected_pages';
            s.specimen_pages = s.page ? [Number(s.page)] : [];
        } else {
            s.specimen_scope = s.specimen_scope || 'all_pages';
            s.specimen_pages = Array.isArray(s.specimen_pages) ? [...new Set(s.specimen_pages.map(Number).filter(Number.isFinite))].sort((a, b) => a - b) : [];
        }
        const choice = s.layouts[s.specimen_format];
        if (choice && !choice.error) { s.width = choice.width; s.height = choice.height; }
    });
    let loaded = false;
    let drag = null;
    steps.forEach((step, i) => signerSelect.add(new Option(`${i + 1}. ${step.name_snapshot}`, i)));
    pages.forEach(page => pageSelect.add(new Option(page.page, page.page)));
    const active = () => steps[Number(signerSelect.value)];
    const page = () => pages[Number(pageSelect.value) - 1];
    const draw = () => {
        blocks.replaceChildren();
        inputs.replaceChildren();
        steps.forEach((step, i) => {
            for (const key of ['id', 'page', 'x', 'y', 'width', 'height', 'specimen_format', 'specimen_scope', 'profile_fingerprint']) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `positions[${i}][${key}]`;
                input.value = step[key] ?? '';
                inputs.append(input);
            }
            if (step.specimen_scope === 'selected_pages') {
                selectedPageNumbers(step).forEach((selectedPage) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `positions[${i}][specimen_pages][]`;
                    input.value = selectedPage;
                    inputs.append(input);
                });
            }
            if (!loaded || !hasPage(step, Number(pageSelect.value)) || step.x === null || step.y === null) return;
            const block = document.createElement('button');
            block.type = 'button';
            block.dataset.index = i;
            block.className = 'absolute cursor-move overflow-hidden bg-white';
            block.style.left = `${step.x / page().width * 100}%`;
            block.style.top = `${step.y / (page().height + footerHeight) * 100}%`;
            block.style.width = `${step.width / page().width * 100}%`;
            block.style.height = `${step.height / (page().height + footerHeight) * 100}%`;
            block.style.outline = i === Number(signerSelect.value) ? '2px solid #FF95A5' : 'none';
            const choice = step.layouts[step.specimen_format];
            if (choice?.preview && !choice.error) {
                const image = document.createElement('img');
                image.src = choice.preview;
                image.alt = `Spesimen ${i + 1}: ${step.name_snapshot}`;
                image.draggable = false;
                image.className = 'h-full w-full pointer-events-none';
                block.append(image);
            } else {
                block.textContent = `Signer ${i + 1}: spesimen belum tersedia. Lihat keterangan di bawah preview.`;
                block.className += ' border border-red-300 bg-red-50 p-1 text-xs text-red-700';
            }
            block.setAttribute('aria-label', `Geser blok ${step.name_snapshot}`);
            blocks.append(block);
        });
        const problems = issues();
        setStatus(problems.length ? problems.join(' · ') : loaded ? 'Posisi lengkap dan tidak bertumpuk. Periksa isi surat sebelum konfirmasi.' : 'Memuat preview halaman…', problems.length > 0);
        root.querySelector('[data-confirm-positions]').disabled = problems.length > 0 || !loaded;
        const progress = root.querySelector('[data-signer-progress]');
        progress.replaceChildren();
        steps.forEach((s, i) => {
            const badge = document.createElement('button'); badge.type = 'button';
            badge.className = 'rounded border border-slate-300 px-3 py-2';
            const targets = selectedPageNumbers(s);
            badge.textContent = `${i + 1}. ${s.name_snapshot} · ${s.specimen_scope === 'all_pages' ? 'semua halaman' : targets.length ? 'hal. ' + targets.join(', ') : 'belum ditempatkan'}`;
            badge.addEventListener('click', () => { signerSelect.value = i; signerSelect.dispatchEvent(new Event('change')); });
            progress.append(badge);
        });
    };
    const renderPagePicker = () => {
        if (!pagePicker) return;
        pagePicker.replaceChildren();
        const step = active();
        if (step.specimen_scope === 'all_pages') {
            pagePicker.textContent = 'Spesimen akan dicetak pada semua halaman.';
            return;
        }
        const selected = new Set(selectedPageNumbers(step));
        const label = document.createElement('span');
        label.className = 'basis-full text-slate-600';
        label.textContent = 'Pilih halaman untuk spesimen (dapat lebih dari satu):';
        pagePicker.append(label);
        pages.forEach((item) => {
            const chip = document.createElement('label');
            chip.className = 'inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = item.page;
            checkbox.checked = selected.has(Number(item.page));
            checkbox.addEventListener('change', () => {
                step.specimen_scope = 'selected_pages';
                step.specimen_pages = [...pagePicker.querySelectorAll('input:checked')].map((input) => Number(input.value)).sort((a, b) => a - b);
                step.page = step.specimen_pages[0] || null;
                draw();
            });
            chip.append(checkbox, document.createTextNode(`Halaman ${item.page}`));
            pagePicker.append(chip);
        });
    };
    const controls = () => {
        format.value = active().specimen_format;
        scope.value = active().specimen_scope;
        renderPagePicker();
        const choice = active().layouts[active().specimen_format];
        root.querySelector('[data-dimension-hint]').textContent = choice?.error || (choice ? `Ukuran otomatis: ${(choice.width / pointsPerCm).toFixed(2)} × ${(choice.height / pointsPerCm).toFixed(2)} cm. Zoom hanya memperbesar preview.` : 'Format spesimen tidak tersedia. Muat ulang halaman.');
    };
    const load = () => {
        loaded = false;
        preview.style.opacity = '0.3';
        draw();
        preview.src = `${root.dataset.previewUrl}?page=${pageSelect.value}`;
    };
    preview.addEventListener('load', () => { loaded = true; preview.style.opacity = '1'; draw(); });
    preview.addEventListener('error', () => { loaded = false; draw(); setStatus('Preview gagal dimuat. Muat ulang atau periksa PDF worker.', true); });
    signerSelect.addEventListener('change', () => {
        controls();
        if (active().page && Number(pageSelect.value) !== Number(active().page)) { pageSelect.value = active().page; load(); } else draw();
    });
    pageSelect.addEventListener('change', load);
    scope.addEventListener('change', () => {
        active().specimen_scope = scope.value;
        if (scope.value === 'selected_pages') ensureCurrentSelected(active());
        else active().specimen_pages = [];
        controls(); draw();
    });
    const locate = (event, offset = {x: 0, y: 0}) => {
        const bounds = surface.getBoundingClientRect();
        const step = active();
        step.page = Number(pageSelect.value);
        if (step.specimen_scope === 'selected_pages') ensureCurrentSelected(step);
        step.x = Math.floor(Math.max(0, Math.min(page().width - step.width, (event.clientX - bounds.left) / bounds.width * page().width - offset.x)) * 100) / 100;
        step.y = Math.floor(Math.max(0, Math.min(page().height - step.height, (event.clientY - bounds.top) / bounds.height * (page().height + footerHeight) - offset.y)) * 100) / 100;
        draw();
    };
    surface.addEventListener('pointerdown', (event) => {
        if (!loaded) return;
        event.preventDefault();
        const block = event.target.closest('[data-index]');
        if (block) signerSelect.value = block.dataset.index;
        controls();
        const bounds = surface.getBoundingClientRect();
        drag = block ? {x: (event.clientX - bounds.left) / bounds.width * page().width - active().x, y: (event.clientY - bounds.top) / bounds.height * (page().height + footerHeight) - active().y} : {x: 0, y: 0};
        surface.setPointerCapture(event.pointerId);
        locate(event, drag);
    });
    surface.addEventListener('pointermove', (event) => { if (drag) locate(event, drag); });
    surface.addEventListener('pointerup', () => { drag = null; });
    surface.addEventListener('pointercancel', () => { drag = null; });
    surface.addEventListener('keydown', (event) => {
        const block = event.target.closest('[data-index]');
        if (!block || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        signerSelect.value = block.dataset.index;
        const step = active();
        step.x = Math.max(0, Math.min(page().width - step.width, step.x + (event.key === 'ArrowRight' ? 2 : event.key === 'ArrowLeft' ? -2 : 0)));
        step.y = Math.max(0, Math.min(page().height - step.height, step.y + (event.key === 'ArrowDown' ? 2 : event.key === 'ArrowUp' ? -2 : 0)));
        draw();
        blocks.querySelector(`[data-index="${signerSelect.value}"]`)?.focus();
    });
    zoom.addEventListener('change', fit);
    window.addEventListener('resize', fit);
    format.addEventListener('change', () => {
        active().specimen_format = format.value;
        const choice = active().layouts[format.value];
        if (choice && !choice.error) {
            active().width = choice.width;
            active().height = choice.height;
            const placedPage = pages.find(p => p.page === Number(active().page)) || page();
            if (placedPage && active().x !== null && active().y !== null) {
                active().x = Math.max(0, Math.min(active().x, placedPage.width - choice.width));
                active().y = Math.max(0, Math.min(active().y, placedPage.height - choice.height));
            }
        }
        controls(); draw();
    });
    root.querySelector('[data-reset-position]').addEventListener('click', () => {
        active().page = Number(pageSelect.value);
        if (active().specimen_scope === 'selected_pages') ensureCurrentSelected(active());
        active().x = Math.max(0, (page().width - active().width) / 2); active().y = Math.max(0, (page().height - active().height) / 2); draw();
    });
    form.addEventListener('submit', (event) => {
        if (issues().length || !loaded) {
            event.preventDefault();
            setStatus(issues().join(' · ') || 'Tunggu preview selesai dimuat.', true);
        }
    });
    fit();
    controls();
    if (active().page) pageSelect.value = active().page;
    load();
});
