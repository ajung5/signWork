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
    const form = root.querySelector('[data-position-form]');
    const zoom = root.querySelector('[data-zoom]');
    const scroller = root.querySelector('[data-preview-scroll]');
    const format = root.querySelector('[data-format]');
    const setStatus = (message, invalid = false) => statuses.forEach(status => {
        status.textContent = message;
        status.classList.toggle('text-red-700', invalid);
        status.classList.toggle('text-blue-800', !invalid);
    });
    const fit = () => { surface.style.width = `${Math.max(240, Math.min(760, scroller.clientWidth - 24)) * Number(zoom.value)}px`; };
    const pageByNumber = (number) => pages.find((item) => Number(item.page) === Number(number));
    const selectedPageNumbers = (step) => {
        if (step.specimen_scope === 'all_pages') return pages.map((item) => Number(item.page));
        if (step.specimen_scope === 'selected_pages') return [...new Set((step.specimen_pages || []).map(Number).filter(Number.isFinite))].sort((a, b) => a - b);
        return step.page ? [Number(step.page)] : [];
    };
    const hasCoordinates = (position) => position && ['x', 'y', 'width', 'height'].every((key) => position[key] !== null && position[key] !== undefined && Number.isFinite(Number(position[key])));
    const pagePosition = (step, pageNumber) => {
        const saved = step.specimen_scope === 'selected_pages'
            ? step.specimen_positions?.[String(pageNumber)] ?? step.specimen_positions?.[pageNumber]
            : null;
        const position = step.specimen_scope === 'selected_pages' ? saved : step;
        if (!position) return null;
        return {
            x: position.x === null || position.x === undefined ? null : Number(position.x),
            y: position.y === null || position.y === undefined ? null : Number(position.y),
            width: Number(position.width),
            height: Number(position.height),
        };
    };
    const setPagePosition = (step, pageNumber, position) => {
        const normalized = {
            x: Number(position.x), y: Number(position.y),
            width: Number(position.width), height: Number(position.height),
        };
        if (step.specimen_scope === 'selected_pages') {
            step.specimen_positions ??= {};
            step.specimen_positions[String(pageNumber)] = normalized;
        }
        step.page = Number(pageNumber);
        step.x = normalized.x;
        step.y = normalized.y;
        step.width = normalized.width;
        step.height = normalized.height;
    };
    const seedSelectedPagePosition = (step, pageNumber) => {
        if (step.specimen_scope !== 'selected_pages') return;
        step.specimen_positions ??= {};
        const key = String(pageNumber);
        if (step.specimen_positions[key] || !hasCoordinates(step)) return;
        step.specimen_positions[key] = {
            x: Number(step.x), y: Number(step.y),
            width: Number(step.width), height: Number(step.height),
        };
    };
    const defaultPosition = (step, targetPage) => ({
        x: Math.max(0, (targetPage.width - Number(step.width)) / 2),
        y: Math.max(0, (targetPage.height - Number(step.height)) / 2),
        width: Number(step.width), height: Number(step.height),
    });
    const materializeInitialPagePosition = (step) => {
        if (step.specimen_scope === 'selected_pages' && step.page) {
            seedSelectedPagePosition(step, Number(step.page));
        }
    };
    const ensureCurrentSelected = (step) => {
        const current = Number(pageSelect.value);
        step.specimen_pages = [...new Set([...selectedPageNumbers(step), current].filter((value) => value > 0))].sort((a, b) => a - b);
        seedSelectedPagePosition(step, current);
        step.page = step.specimen_pages[0] || current;
    };
    steps.forEach((step) => {
        step.specimen_positions = step.specimen_positions && typeof step.specimen_positions === 'object' ? step.specimen_positions : {};
        ['x', 'y', 'width', 'height'].forEach((key) => { if (step[key] !== null && step[key] !== undefined) step[key] = Number(step[key]); });
        step.specimen_format = !step.specimen_format || step.specimen_format === 'qr_only' ? 'qr_2cm' : step.specimen_format;
        if (step.specimen_scope === 'selected_page') {
            step.specimen_scope = 'selected_pages';
            step.specimen_pages = step.page ? [Number(step.page)] : [];
        } else {
            step.specimen_scope = step.specimen_scope || 'all_pages';
            step.specimen_pages = Array.isArray(step.specimen_pages) ? [...new Set(step.specimen_pages.map(Number).filter(Number.isFinite))].sort((a, b) => a - b) : [];
        }
        const choice = step.layouts[step.specimen_format];
        if (choice && !choice.error) {
            step.width = choice.width;
            step.height = choice.height;
        }
        materializeInitialPagePosition(step);
    });
    let loaded = false;
    let drag = null;
    steps.forEach((step, i) => signerSelect.add(new Option(`${i + 1}. ${step.name_snapshot}`, i)));
    const active = () => steps[Number(signerSelect.value)];
    const page = () => pageByNumber(pageSelect.value);
    const renderPageOptions = () => {
        const step = active();
        const current = Number(pageSelect.value);
        const available = step?.specimen_scope === 'selected_pages'
            ? selectedPageNumbers(step)
            : pages.map((item) => Number(item.page));
        pageSelect.replaceChildren();
        available.forEach((pageNumber) => pageSelect.add(new Option(pageNumber, pageNumber)));
        if (available.length && !available.includes(current)) pageSelect.value = available[0];
    };
    renderPageOptions();
    const issues = () => {
        const problems = [];
        steps.forEach((step, i) => {
            const choice = step.layouts[step.specimen_format];
            if (!choice || choice.error) { problems.push(`Signer ${i + 1} (${step.name_snapshot}): ${choice?.error || 'Pilih format spesimen'}`); return; }
            const targetPageNumbers = selectedPageNumbers(step);
            if (step.specimen_scope === 'selected_pages' && targetPageNumbers.length === 0) { problems.push(`Signer ${i + 1}: pilih minimal satu halaman`); return; }
            targetPageNumbers.forEach((pageNumber) => {
                const targetPage = pageByNumber(pageNumber);
                const position = pagePosition(step, pageNumber);
                if (!targetPage || !hasCoordinates(position)) {
                    problems.push(`Signer ${i + 1}: posisi belum ditentukan pada halaman ${pageNumber}`);
                    return;
                }
                if (position.x < 0 || position.y < 0 || position.x + position.width > targetPage.width + 0.01 || position.y + position.height > targetPage.height + 0.01) {
                    problems.push(`Signer ${i + 1}: posisi keluar batas pada halaman ${pageNumber}`);
                }
                steps.slice(0, i).forEach((previous, j) => {
                    if (!selectedPageNumbers(previous).includes(pageNumber)) return;
                    const previousPosition = pagePosition(previous, pageNumber);
                    if (hasCoordinates(previousPosition) && position.x < previousPosition.x + previousPosition.width && position.x + position.width > previousPosition.x && position.y < previousPosition.y + previousPosition.height && position.y + position.height > previousPosition.y) {
                        problems.push(`Signer ${j + 1} dan ${i + 1}: blok bertumpuk pada halaman ${pageNumber}`);
                    }
                });
            });
        });
        return [...new Set(problems)];
    };
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
                    const pagePositionValue = pagePosition(step, selectedPage);
                    const pageInput = (key) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `positions[${i}][specimen_positions][${selectedPage}][${key}]`;
                        input.value = pagePositionValue?.[key] ?? '';
                        inputs.append(input);
                    };
                    ['x', 'y', 'width', 'height'].forEach(pageInput);
                    const selected = document.createElement('input');
                    selected.type = 'hidden';
                    selected.name = `positions[${i}][specimen_pages][]`;
                    selected.value = selectedPage;
                    inputs.append(selected);
                });
            }
            if (!loaded || !selectedPageNumbers(step).includes(Number(pageSelect.value))) return;
            const position = pagePosition(step, Number(pageSelect.value));
            if (!hasCoordinates(position)) return;
            const block = document.createElement('button');
            block.type = 'button';
            block.dataset.index = i;
            block.className = 'absolute cursor-move overflow-hidden bg-white';
            block.style.left = `${position.x / page().width * 100}%`;
            block.style.top = `${position.y / (page().height + footerHeight) * 100}%`;
            block.style.width = `${position.width / page().width * 100}%`;
            block.style.height = `${position.height / (page().height + footerHeight) * 100}%`;
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
                block.textContent = `Signer ${i + 1}: spesimen belum tersedia.`;
                block.className += ' border border-red-300 bg-red-50 p-1 text-xs text-red-700';
            }
            block.setAttribute('aria-label', `Geser blok ${step.name_snapshot} pada halaman ${pageSelect.value}`);
            blocks.append(block);
        });
        const problems = issues();
        setStatus(problems.length ? problems.join(' · ') : loaded ? 'Posisi lengkap dan tidak bertumpuk. Periksa isi surat sebelum konfirmasi.' : 'Memuat preview halaman…', problems.length > 0);
        root.querySelector('[data-confirm-positions]').disabled = problems.length > 0 || !loaded;
        const progress = root.querySelector('[data-signer-progress]');
        progress.replaceChildren();
        steps.forEach((step, i) => {
            const badge = document.createElement('button'); badge.type = 'button';
            badge.className = 'rounded border border-slate-300 px-3 py-2';
            const targets = selectedPageNumbers(step);
            const source = step.placement_source === 'placeholder' ? 'placeholder otomatis' : 'manual';
            badge.textContent = `${i + 1}. ${step.name_snapshot} · ${step.specimen_scope === 'all_pages' ? 'semua halaman' : targets.length ? 'hal. ' + targets.join(', ') : 'belum ditempatkan'} · ${source}`;
            badge.addEventListener('click', () => { signerSelect.value = i; signerSelect.dispatchEvent(new Event('change')); });
            progress.append(badge);
        });
    };
    const renderPagePicker = () => {
        if (!pagePicker) return;
        pagePicker.replaceChildren();
        const step = active();
        if (step.specimen_scope === 'all_pages') {
            pagePicker.textContent = 'Spesimen akan dicetak pada semua halaman dengan posisi yang sama.';
            return;
        }
        const selected = new Set(selectedPageNumbers(step));
        const label = document.createElement('span');
        label.className = 'basis-full text-slate-600';
        label.textContent = 'Pilih halaman untuk spesimen (setiap halaman dapat memiliki posisi berbeda):';
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
                const checked = [...pagePicker.querySelectorAll('input:checked')].map((input) => Number(input.value));
                if (!checked.length) {
                    checkbox.checked = true;
                    return;
                }
                step.specimen_pages = [...new Set(checked)].sort((a, b) => a - b);
                step.page = step.specimen_pages[0] || null;
                renderPageOptions();
                const nextPage = checkbox.checked ? Number(checkbox.value) : step.specimen_pages[0];
                if (nextPage && Number(pageSelect.value) !== nextPage) {
                    pageSelect.value = nextPage;
                    load();
                } else {
                    draw();
                }
            });
            chip.append(checkbox, document.createTextNode(`Halaman ${item.page}`));
            pagePicker.append(chip);
        });
    };
    const controls = () => {
        format.value = active().specimen_format;
        scope.value = active().specimen_scope;
        renderPageOptions();
        renderPagePicker();
        const choice = active().layouts[active().specimen_format];
        root.querySelector('[data-dimension-hint]').textContent = choice?.error || (choice ? `Ukuran otomatis: ${(choice.width / pointsPerCm).toFixed(2)} × ${(choice.height / pointsPerCm).toFixed(2)} cm. Posisi disimpan per halaman saat memilih beberapa halaman.` : 'Format spesimen tidak tersedia. Muat ulang halaman.');
    };
    const load = () => {
        loaded = false;
        preview.style.opacity = '0.3';
        draw();
        preview.src = `${root.dataset.previewUrl}?page=${pageSelect.value}`;
    };
    const locate = (event, offset = {x: 0, y: 0}) => {
        const bounds = surface.getBoundingClientRect();
        const step = active();
        const currentPage = page();
        if (!currentPage) return;
        if (step.specimen_scope === 'selected_pages') ensureCurrentSelected(step);
        const current = pagePosition(step, currentPage.page) || defaultPosition(step, currentPage);
        const position = {
            ...current,
            x: Math.floor(Math.max(0, Math.min(currentPage.width - current.width, (event.clientX - bounds.left) / bounds.width * currentPage.width - offset.x)) * 100) / 100,
            y: Math.floor(Math.max(0, Math.min(currentPage.height - current.height, (event.clientY - bounds.top) / bounds.height * (currentPage.height + footerHeight) - offset.y)) * 100) / 100,
        };
        setPagePosition(step, currentPage.page, position);
        draw();
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
    surface.addEventListener('pointerdown', (event) => {
        if (!loaded) return;
        event.preventDefault();
        const block = event.target.closest('[data-index]');
        if (block) signerSelect.value = block.dataset.index;
        controls();
        const bounds = surface.getBoundingClientRect();
        const currentPage = page();
        const current = pagePosition(active(), currentPage.page) || {x: 0, y: 0};
        drag = block ? {x: (event.clientX - bounds.left) / bounds.width * currentPage.width - current.x, y: (event.clientY - bounds.top) / bounds.height * (currentPage.height + footerHeight) - current.y} : {x: 0, y: 0};
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
        const currentPage = page();
        const position = pagePosition(step, currentPage.page);
        position.x = Math.max(0, Math.min(currentPage.width - position.width, position.x + (event.key === 'ArrowRight' ? 2 : event.key === 'ArrowLeft' ? -2 : 0)));
        position.y = Math.max(0, Math.min(currentPage.height - position.height, position.y + (event.key === 'ArrowDown' ? 2 : event.key === 'ArrowUp' ? -2 : 0)));
        setPagePosition(step, currentPage.page, position);
        draw();
        blocks.querySelector(`[data-index="${signerSelect.value}"]`)?.focus();
    });
    zoom.addEventListener('change', fit);
    window.addEventListener('resize', fit);
    format.addEventListener('change', () => {
        const step = active();
        step.specimen_format = format.value;
        const choice = step.layouts[format.value];
        if (choice && !choice.error) {
            step.width = choice.width;
            step.height = choice.height;
            const pagesToClamp = step.specimen_scope === 'selected_pages' ? selectedPageNumbers(step) : [Number(step.page) || Number(pageSelect.value)];
            pagesToClamp.forEach((pageNumber) => {
                const targetPage = pageByNumber(pageNumber);
                const position = pagePosition(step, pageNumber);
                if (!targetPage || !hasCoordinates(position)) return;
                position.x = Math.max(0, Math.min(position.x, targetPage.width - choice.width));
                position.y = Math.max(0, Math.min(position.y, targetPage.height - choice.height));
                position.width = choice.width;
                position.height = choice.height;
                setPagePosition(step, pageNumber, position);
            });
        }
        controls(); draw();
    });
    root.querySelector('[data-reset-position]').addEventListener('click', () => {
        const step = active();
        const currentPage = page();
        const position = pagePosition(step, currentPage.page) || {width: step.width, height: step.height};
        setPagePosition(step, currentPage.page, {
            ...position,
            x: Math.max(0, (currentPage.width - position.width) / 2),
            y: Math.max(0, (currentPage.height - position.height) / 2),
        });
        draw();
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
