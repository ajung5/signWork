const footerHeight = 34.016;

const ready = (fn) => document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', fn)
    : fn();

ready(() => {
    const root = document.querySelector('[data-specimen-review]');
    if (!root) return;

    const payload = JSON.parse(root.querySelector('[data-specimen-review-data]').textContent);
    const pages = payload.pages || [];
    const steps = payload.steps || [];
    const signerSelect = root.querySelector('[data-review-signer]');
    const pageSelect = root.querySelector('[data-review-page]');
    const status = root.querySelector('[data-review-status]');
    const surface = root.querySelector('[data-review-surface]');
    const preview = root.querySelector('[data-review-page-image]');
    const blocks = root.querySelector('[data-review-blocks]');
    const scroller = root.querySelector('[data-review-scroll]');
    let loaded = false;

    const pageByNumber = (number) => pages.find((page) => Number(page.page) === Number(number));
    const selectedPageNumbers = (step) => {
        if (step.specimen_scope === 'all_pages') return pages.map((page) => Number(page.page));
        if (step.specimen_scope === 'selected_pages') return [...new Set((step.specimen_pages || []).map(Number))].sort((a, b) => a - b);
        return step.page ? [Number(step.page)] : [];
    };
    const pagePosition = (step, pageNumber) => {
        if (step.specimen_scope === 'selected_pages') {
            return step.specimen_positions?.[String(pageNumber)] ?? step.specimen_positions?.[pageNumber] ?? null;
        }
        return step;
    };
    const hasCoordinates = (position) => position && ['x', 'y', 'width', 'height']
        .every((key) => position[key] !== null && position[key] !== undefined && Number.isFinite(Number(position[key])));
    const fit = () => {
        surface.style.width = `${Math.max(240, Math.min(760, scroller.clientWidth - 24))}px`;
    };
    const visibleSteps = () => signerSelect.value === 'all'
        ? steps.map((step, index) => ({step, index}))
        : [{step: steps[Number(signerSelect.value)], index: Number(signerSelect.value)}];
    const renderPageOptions = () => {
        const current = Number(pageSelect.value) || Number(pages[0]?.page);
        pageSelect.replaceChildren();
        pages.forEach((page) => pageSelect.add(new Option(`Halaman ${page.page}`, page.page)));
        if (pages.length) pageSelect.value = pages.some((page) => Number(page.page) === current) ? current : pages[0].page;
    };
    const draw = () => {
        blocks.replaceChildren();
        if (!loaded) {
            status.textContent = 'Memuat preview halaman…';
            return;
        }

        const currentPage = pageByNumber(pageSelect.value);
        if (!currentPage) {
            status.textContent = 'Halaman preview belum tersedia.';
            return;
        }

        let count = 0;
        visibleSteps().forEach(({step, index}) => {
            if (!step || !selectedPageNumbers(step).includes(Number(currentPage.page))) return;
            const position = pagePosition(step, currentPage.page);
            if (!hasCoordinates(position)) return;

            const block = document.createElement('div');
            block.className = 'absolute overflow-hidden rounded border-2 border-rose-600 bg-white/80 shadow-sm';
            block.style.left = `${Number(position.x) / Number(currentPage.width) * 100}%`;
            block.style.top = `${Number(position.y) / (Number(currentPage.height) + footerHeight) * 100}%`;
            block.style.width = `${Number(position.width) / Number(currentPage.width) * 100}%`;
            block.style.height = `${Number(position.height) / (Number(currentPage.height) + footerHeight) * 100}%`;
            block.title = `Signer ${index + 1}: ${step.name_snapshot}`;
            block.setAttribute('aria-label', `Posisi Spesiment signer ${index + 1}: ${step.name_snapshot}`);

            const choice = step.layouts?.[step.specimen_format];
            if (choice?.preview && !choice.error) {
                const image = document.createElement('img');
                image.src = choice.preview;
                image.alt = `Spesiment signer ${index + 1}: ${step.name_snapshot}`;
                image.draggable = false;
                image.className = 'h-full w-full';
                block.append(image);
            } else {
                block.textContent = `Signer ${index + 1}`;
                block.className += ' p-1 text-xs text-rose-800';
            }
            blocks.append(block);
            count += 1;
        });

        status.textContent = count
            ? `${count} posisi Spesiment tampil pada halaman ${currentPage.page}.`
            : `Tidak ada posisi Spesiment pada halaman ${currentPage.page}.`;
    };
    const load = () => {
        loaded = false;
        preview.style.opacity = '0.35';
        draw();
        preview.src = `${root.dataset.previewUrl}?page=${encodeURIComponent(pageSelect.value)}`;
    };

    renderPageOptions();
    signerSelect.addEventListener('change', draw);
    pageSelect.addEventListener('change', load);
    preview.addEventListener('load', () => {
        loaded = true;
        preview.style.opacity = '1';
        draw();
    });
    preview.addEventListener('error', () => {
        loaded = false;
        blocks.replaceChildren();
        status.textContent = 'Preview gagal dimuat. Muat ulang atau periksa PDF worker.';
    });
    window.addEventListener('resize', fit);
    fit();
    load();
});
