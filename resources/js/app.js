const onReady = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
        return;
    }

    callback();
};

onReady(() => {
    const layout = document.querySelector('[data-app-layout]');
    if (layout) {
        const sidebar = layout.querySelector('[data-sidebar]');
        const backdrop = layout.querySelector('[data-sidebar-backdrop]');
        const toggle = layout.querySelector('[data-sidebar-toggle]');
        const closeButton = layout.querySelector('[data-sidebar-close]');
        const content = layout.querySelector('[data-app-content]');
        const desktop = window.matchMedia('(min-width: 64rem)');
        const storageKey = `signwork:sidebar:${layout.dataset.userId}`;
        let desktopOpen = true;
        try { desktopOpen = localStorage.getItem(storageKey) !== 'closed'; } catch { /* Storage is optional. */ }
        let mobileOpen = false;
        let previousOverflow = null;
        const isOpen = () => desktop.matches ? desktopOpen : mobileOpen;
        const render = () => {
            const open = isOpen();
            const modal = !desktop.matches && open;
            layout.dataset.sidebarOpen = String(open);
            sidebar.inert = !open;
            sidebar.setAttribute('aria-hidden', String(!open));
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Tutup navigasi' : 'Buka navigasi');
            backdrop.hidden = !modal;
            content.inert = modal;
            if (modal) {
                sidebar.setAttribute('role', 'dialog');
                sidebar.setAttribute('aria-modal', 'true');
                if (previousOverflow === null) previousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
            } else {
                sidebar.removeAttribute('role');
                sidebar.removeAttribute('aria-modal');
                if (previousOverflow !== null) document.body.style.overflow = previousOverflow;
                previousOverflow = null;
            }
            window.dispatchEvent(new Event('resize'));
        };
        const setOpen = (open) => {
            if (desktop.matches) {
                desktopOpen = open;
                try { localStorage.setItem(storageKey, open ? 'open' : 'closed'); } catch { /* Storage is optional. */ }
            } else mobileOpen = open;
            render();
            if (!open) toggle.focus();
            else if (!desktop.matches) closeButton.focus();
        };
        toggle.hidden = false;
        closeButton.hidden = false;
        layout.classList.add('sidebar-ready');
        render();
        toggle.addEventListener('click', () => setOpen(!isOpen()));
        closeButton.addEventListener('click', () => setOpen(false));
        backdrop.addEventListener('click', () => setOpen(false));
        sidebar.addEventListener('click', event => {
            if (!desktop.matches && event.target.closest('a')) setOpen(false);
        });
        sidebar.addEventListener('transitionend', event => {
            if (event.target === sidebar) window.dispatchEvent(new Event('resize'));
        });
        desktop.addEventListener('change', () => {
            const focusInSidebar = sidebar.contains(document.activeElement);
            mobileOpen = false;
            render();
            if (!isOpen() && focusInSidebar) toggle.focus();
        });
        document.addEventListener('keydown', event => {
            if (document.querySelector('[data-feedback-modal][open]')) return;
            if (event.key === 'Escape' && isOpen()) { event.preventDefault(); setOpen(false); }
            if (event.key !== 'Tab' || desktop.matches || !mobileOpen) return;
            const items = [...sidebar.querySelectorAll('a[href], button:not([disabled])')]
                .filter(item => !item.hidden && item.getClientRects().length);
            const first = items[0];
            const last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    }

    const passwordToggle = document.querySelector('[data-password-toggle]');
    const passwordInput = document.querySelector('#password');

    passwordToggle?.addEventListener('click', () => {
        if (! passwordInput) {
            return;
        }

        const isPassword = passwordInput.getAttribute('type') === 'password';

        passwordInput.setAttribute(
            'type',
            isPassword ? 'text' : 'password',
        );

        passwordToggle.setAttribute(
            'aria-label',
            isPassword ? 'Sembunyikan password' : 'Tampilkan password',
        );

        passwordToggle
            .querySelector('[data-eye-open]')
            ?.classList.toggle('hidden', isPassword);

        passwordToggle
            .querySelector('[data-eye-closed]')
            ?.classList.toggle('hidden', ! isPassword);
    });
});

import './pdf-workflow';
import './pdf-review';
onReady(() => {
    document.querySelectorAll('[data-single-submit]').forEach(form => form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"],button:not([type])').disabled = true;
    }));
    const form = document.querySelector('[data-hash-form]');
    if (!form) return;
    const picker = form.querySelector('[data-hash-file]');
    const input = form.querySelector('[data-hash-input]');
    const status = form.querySelector('[data-hash-status]');
    let generation = 0;
    picker.addEventListener('change', async () => {
        const ticket = ++generation;
        const file = picker.files[0];
        input.value = '';
        if (!file) return;
        if (file.size > 20 * 1024 * 1024) { status.textContent = 'Batas file 20 MB.'; return; }
        if (!window.crypto?.subtle) { status.textContent = 'Perhitungan browser memerlukan HTTPS atau localhost. Tempel hash secara manual.'; return; }
        status.textContent = 'Menghitung SHA-256 di perangkat Anda…';
        try {
            const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
            if (ticket !== generation) return;
            input.value = [...new Uint8Array(digest)].map(x => x.toString(16).padStart(2, '0')).join('');
            status.textContent = 'Hash siap. Tekan Bandingkan hash.';
        } catch { status.textContent = 'File gagal dibaca. Tempel hash secara manual.'; }
    });
});

onReady(() => {
    const modal = document.querySelector('[data-feedback-modal]');
    if (!modal || typeof modal.showModal !== 'function') return;
    const overflow = document.body.style.overflow;
    modal.close();
    modal.showModal();
    document.body.style.overflow = 'hidden';
    modal.addEventListener('close', () => { document.body.style.overflow = overflow; }, { once: true });
});

onReady(() => {
    const form = document.querySelector('[data-master-edit]');
    if (!form) return;
    const assigned = JSON.parse(form.dataset.assignedUsers);
    const select = form.querySelector('[name="target_user_id"]');
    const filter = () => {
        const type = form.querySelector('[name="type"]:checked').value;
        const used = (assigned[type] || []).map(Number);
        for (const option of select.options) {
            const occupied = option.value !== '' && used.includes(Number(option.value));
            option.hidden = occupied;
            option.disabled = occupied;
        }
        if (select.selectedOptions[0]?.disabled) select.value = '';
    };
    form.querySelectorAll('[name="type"]').forEach(input => input.addEventListener('change', filter));
    filter();
});
