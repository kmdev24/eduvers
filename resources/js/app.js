// EduVers — mobile sidebar toggle
const sidebar = document.querySelector('[data-sidebar]');
const overlay = document.querySelector('[data-sidebar-overlay]');

function openSidebar() {
    sidebar?.classList.remove('-translate-x-full');
    overlay?.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeSidebar() {
    sidebar?.classList.add('-translate-x-full');
    overlay?.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

document.querySelectorAll('[data-sidebar-open]').forEach((el) => el.addEventListener('click', openSidebar));
document.querySelectorAll('[data-sidebar-close]').forEach((el) => el.addEventListener('click', closeSidebar));
overlay?.addEventListener('click', closeSidebar);
document.addEventListener('keydown', (e) => e.key === 'Escape' && closeSidebar());

// Confirm before destructive form submits: <form data-confirm="Delete this?">
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirm)) e.preventDefault();
    });
});

// Show fields only for the selected role: <form data-role-form> + [data-show-for-role="teacher"]
document.querySelectorAll('[data-role-form]').forEach((form) => {
    const sync = () => {
        const role = form.querySelector('input[name="role"]:checked')?.value;
        form.querySelectorAll('[data-show-for-role]').forEach((el) => {
            const show = el.dataset.showForRole === role;
            el.classList.toggle('hidden', !show);
            el.querySelectorAll('input, select').forEach((input) => (input.disabled = !show));
        });
    };
    form.querySelectorAll('input[name="role"]').forEach((r) => r.addEventListener('change', sync));
    sync();
});

// Generate a readable random password: <button data-generate-password="password">
document.querySelectorAll('[data-generate-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.generatePassword);
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        const bytes = crypto.getRandomValues(new Uint32Array(10));
        input.value = Array.from(bytes, (b) => chars[b % chars.length]).join('');
        input.type = 'text';
        input.focus();
        input.select();
    });
});

// Show / hide password: <button data-toggle-password="password">
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.togglePassword);
        input.type = input.type === 'password' ? 'text' : 'password';
    });
});

// Only offer strands that fit the chosen grade level (SHS vs College):
// <select data-level-select="track_strand_id"> options carry data-level-type;
// strand options carry data-category.
document.querySelectorAll('[data-level-select]').forEach((level) => {
    const strand = document.getElementById(level.dataset.levelSelect);
    if (!strand) return;
    const sync = () => {
        const type = level.selectedOptions[0]?.dataset.levelType;
        strand.querySelectorAll('option[data-category]').forEach((opt) => {
            const ok = !type || (type === 'college') === (opt.dataset.category === 'college');
            opt.hidden = !ok;
            opt.disabled = !ok;
            if (!ok && opt.selected) strand.value = '';
        });
    };
    level.addEventListener('change', sync);
    sync();
});

// Announcement form: show the section picker only for "Section" audience
document.querySelectorAll('[data-audience-select]').forEach((select) => {
    const box = select.closest('form')?.querySelector('[data-audience-section]');
    if (!box) return;
    const sync = () => box.classList.toggle('hidden', select.value !== 'section');
    select.addEventListener('change', sync);
    sync();
});

// Live header clock in the school's timezone (Asia/Manila), 12-hour with AM/PM.
// Nothing is ever cut off: if the date and time don't fit side by side (phones, narrow
// tablets, a crowded header) they take turns every few seconds (data-clock-rotate="5"),
// and the date/time switch to shorter forms until they fit.
document.querySelectorAll('[data-live-clock]').forEach((clock) => {
    const timeZone = clock.dataset.timezone || 'Asia/Manila';
    const dateEl = clock.querySelector('[data-clock-date]');
    const timeEl = clock.querySelector('[data-clock-time] time');
    const zoneEl = clock.querySelector('[data-clock-zone]');

    // Longest first: "Tuesday, September 29, 2026" → "Tue, Sep 29, 2026" → "Sep 29, 2026"
    const dateFormats = [
        { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' },
        { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' },
        { month: 'short', day: 'numeric', year: 'numeric' },
    ].map((o) => new Intl.DateTimeFormat('en-US', { timeZone, ...o }));
    const timeFormat = new Intl.DateTimeFormat('en-US', { timeZone, hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });

    // Invisible copy of the clock, used to measure text without touching the real one
    const probe = clock.cloneNode(true);
    probe.removeAttribute('data-live-clock');
    ['clockShow', 'clockFit', 'clockRotate', 'timezone'].forEach((k) => delete probe.dataset[k]);
    probe.setAttribute('aria-hidden', 'true');
    probe.classList.remove('truncate');
    Object.assign(probe.style, { position: 'absolute', left: '-9999px', top: '0', width: 'auto', maxWidth: 'none', whiteSpace: 'nowrap', visibility: 'hidden', pointerEvents: 'none' });
    document.body.append(probe);
    const p = {
        date: probe.querySelector('[data-clock-date]'),
        sep: probe.querySelector('[data-clock-sep]'),
        time: probe.querySelector('[data-clock-time]'),
        timeText: probe.querySelector('[data-clock-time] time'),
        zone: probe.querySelector('[data-clock-zone]'),
    };
    const show = (el, on) => el && (el.style.display = on ? '' : 'none');
    const measure = ({ date = true, sep = true, time = true, zone = true }) => {
        show(p.date, date); show(p.sep, sep); show(p.time, time); show(p.zone, zone);
        return probe.scrollWidth;
    };

    let layout = { fit: 'both', dateLevel: 0, zone: true };
    let lastKey = '';

    const choose = (now) => {
        const available = clock.clientWidth;
        const time = timeFormat.format(now);
        const key = `${available}|${dateFormats[0].format(now)}|${time.length}`;
        if (key === lastKey || !available) return;
        lastKey = key;

        p.timeText.textContent = time;
        const fits = (w) => w <= available;

        // 1. Full date and time side by side, with shorter dates if needed
        for (let level = 0; level < dateFormats.length; level++) {
            p.date.textContent = dateFormats[level].format(now);
            if (fits(measure({}))) { layout = { fit: 'both', dateLevel: level, zone: true }; return; }
            if (level === 0) continue;
        }
        // 2. Take turns: the longest date that fits alone, and the time (with "PHT" if it fits)
        let dateLevel = dateFormats.length - 1;
        for (let level = 0; level < dateFormats.length; level++) {
            p.date.textContent = dateFormats[level].format(now);
            if (fits(measure({ sep: false, time: false }))) { dateLevel = level; break; }
        }
        const zone = fits(measure({ date: false, sep: false }));
        layout = { fit: 'rotate', dateLevel, zone };
    };

    const tick = () => {
        const now = new Date();
        choose(now);
        clock.dataset.clockFit = layout.fit;
        if (dateEl) dateEl.textContent = dateFormats[layout.dateLevel].format(now);
        if (timeEl) timeEl.textContent = timeFormat.format(now);
        if (zoneEl) zoneEl.hidden = !layout.zone;
    };

    tick();
    setInterval(tick, 1000);
    const refit = () => { lastKey = ''; tick(); };
    window.addEventListener('resize', refit);
    document.fonts?.ready.then(refit);

    // Take turns between date and time when they don't fit together
    const seconds = Number(clock.dataset.clockRotate) || 0;
    if (seconds > 0) {
        setInterval(() => {
            if (clock.dataset.clockFit !== 'rotate') return;
            clock.dataset.clockShow = clock.dataset.clockShow === 'date' ? 'time' : 'date';
        }, seconds * 1000);
    }
});

// Lesson form: show the panel for the chosen video source (none / upload / link)
document.querySelectorAll('[data-video-form]').forEach((box) => {
    const sync = () => {
        const source = box.querySelector('input[name="video_source"]:checked')?.value || 'none';
        box.querySelectorAll('[data-video-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.videoPanel !== source);
        });
    };
    box.querySelectorAll('input[name="video_source"]').forEach((radio) => radio.addEventListener('change', sync));
    sync();
});

// Warn before uploading a file that is over the limit: <input type="file" data-max-bytes="...">
document.querySelectorAll('input[type="file"][data-max-bytes]').forEach((input) => {
    input.addEventListener('change', () => {
        const max = Number(input.dataset.maxBytes);
        const file = input.files?.[0];
        const tooBig = file && file.size > max;
        input.setCustomValidity(tooBig
            ? `This file is ${Math.round(file.size / 1048576)} MB. The limit is ${Math.round(max / 1048576)} MB.`
            : '');
        if (tooBig) input.reportValidity();
    });
});

// Show an "uploading" state so large uploads aren't submitted twice: <form data-upload-form>
document.querySelectorAll('form[data-upload-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"][data-uploading-text]');
        if (!button) return;
        button.disabled = true;
        button.textContent = button.dataset.uploadingText;
    });
});

// Dropdowns: <div data-dropdown> <button data-dropdown-toggle> <div data-dropdown-panel class="hidden">
document.querySelectorAll('[data-dropdown]').forEach((root) => {
    const toggle = root.querySelector('[data-dropdown-toggle]');
    const panel = root.querySelector('[data-dropdown-panel]');
    if (!toggle || !panel) return;

    const setOpen = (open) => {
        panel.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        setOpen(panel.classList.contains('hidden'));
    });
    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.classList.contains('hidden')) {
            setOpen(false);
            toggle.focus();
        }
    });
});

// Notification bell: check for new notifications every N seconds while the tab is visible
// (and right away when the student comes back to the tab/app). On a new one: update the
// badge, refresh the dropdown and show a toast. No page reload needed.
document.querySelectorAll('[data-notification-bell]').forEach((bell) => {
    const countUrl = bell.dataset.countUrl;
    const dropdownUrl = bell.dataset.dropdownUrl;
    const badge = bell.querySelector('[data-notification-badge]');
    const list = bell.querySelector('[data-notification-list]');
    const toasts = document.querySelector('[data-notification-toasts]');
    const seconds = Number(bell.dataset.pollSeconds) || 15;
    let latestId = bell.dataset.latestId || '';
    let busy = false;
    if (!countUrl || !badge) return;

    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    const setBadge = (unread) => {
        badge.textContent = unread > 9 ? '9+' : String(unread);
        badge.classList.toggle('hidden', !unread);
    };

    const showToast = (item) => {
        if (!toasts) return;
        const toast = document.createElement('a');
        toast.href = item.url;
        toast.className = 'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border border-gold-300 bg-white p-4 shadow-elevated ring-1 ring-gold-200 transition duration-300 sm:w-96';
        const dot = document.createElement('span');
        dot.className = 'mt-1 size-2.5 shrink-0 rounded-full bg-gold-500 ring-4 ring-gold-100';
        const body = document.createElement('span');
        body.className = 'min-w-0 flex-1';
        const headline = document.createElement('span');
        headline.className = 'block text-[11px] font-semibold uppercase tracking-wide text-champagne-dark';
        headline.textContent = item.headline;
        const title = document.createElement('span');
        title.className = 'block text-sm font-semibold text-ink-900';
        title.textContent = item.title;
        body.append(headline, title);
        toast.append(dot, body);
        toasts.append(toast);
        setTimeout(() => toast.classList.add('opacity-0'), 7000);
        setTimeout(() => toast.remove(), 7400);
    };

    const refresh = async () => {
        if (document.hidden || busy) return;
        busy = true;
        try {
            const res = await fetch(countUrl, { headers, credentials: 'same-origin', cache: 'no-store' });
            if (!res.ok || !(res.headers.get('content-type') || '').includes('json')) return;
            const { unread, latest } = await res.json();
            setBadge(unread);

            const newId = latest?.id || '';
            if (newId && newId !== latestId) {
                latestId = newId;
                if (latest.unread) showToast(latest);
                if (list && dropdownUrl) {
                    const html = await fetch(dropdownUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
                    if (html.ok) list.innerHTML = await html.text();
                }
            }
        } catch {
            // Offline or signed out — try again next time
        } finally {
            busy = false;
        }
    };

    setInterval(refresh, seconds * 1000);
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('focus', refresh);
    window.addEventListener('pageshow', (e) => e.persisted && refresh());
});
