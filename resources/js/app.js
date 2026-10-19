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

// Keep a dropdown in step with the ones it depends on, without a page reload:
//   <select data-depends="level_type=levelType,strand=strandId">
//     <option data-level-type="shs" data-strand-id="5">…</option>
// Each pair is "id of the source select" = "the option's data key". An empty source
// means "no filter on that key"; options missing the key are always offered.
document.querySelectorAll('[data-depends]').forEach((target) => {
    const pairs = target.dataset.depends.split(',').map((pair) => {
        const [id, key] = pair.split('=').map((s) => s.trim());
        return { source: document.getElementById(id), key };
    }).filter((p) => p.source);

    if (!pairs.length) return;

    const sync = () => {
        [...target.options].forEach((option) => {
            // Remember a disabled set by the server (e.g. a full section) so filtering never undoes it
            if (option.dataset.lockedOut === undefined) option.dataset.lockedOut = option.disabled ? '1' : '';

            const ok = pairs.every(({ source, key }) =>
                !source.value || option.dataset[key] === undefined || option.dataset[key] === source.value);
            option.hidden = !ok;
            option.disabled = !ok || option.dataset.lockedOut === '1';
        });

        // An option that no longer fits must not stay selected
        if (target.selectedOptions[0]?.hidden) {
            target.value = [...target.options].find((o) => !o.hidden && !o.disabled)?.value ?? '';
        }

        // Don't leave a group heading behind with nothing under it
        target.querySelectorAll('optgroup').forEach((group) => {
            group.hidden = ![...group.children].some((o) => !o.hidden);
        });
    };

    pairs.forEach(({ source }) => source.addEventListener('change', sync));
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

// Idle sign-out (the server ends the session after data-idle-minutes without requests).
// Typing or clicking pings the server at most once a minute, so filling in a long quiz
// does not count as idle. Once the time is up the page reloads, which shows the login page
// and clears the screen of an unattended computer.
(() => {
    const minutes = Number(document.body.dataset.idleMinutes);
    const pingUrl = document.body.dataset.pingUrl;
    if (!(minutes > 0) || !pingUrl) return;

    let lastActive = Date.now();
    let lastPing = Date.now();
    const onActivity = () => {
        lastActive = Date.now();
        if (lastActive - lastPing > 60 * 1000) {
            lastPing = lastActive;
            fetch(pingUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' }).catch(() => {});
        }
    };
    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((type) => window.addEventListener(type, onActivity, { passive: true }));

    setInterval(() => {
        if (Date.now() - lastActive > minutes * 60 * 1000 + 5000) window.location.reload();
    }, 30 * 1000);
})();

// Show / hide a typed password (the eye in the password field)
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;

    button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.querySelector('[data-password-show]')?.classList.toggle('hidden', show);
        button.querySelector('[data-password-hide]')?.classList.toggle('hidden', !show);
        input.focus();
    });
});

// "Apply to all": copy one <select>'s value into every select in the target list.
// Nothing is saved until the form's own Save button is pressed.
document.querySelectorAll('[data-apply-to-all]').forEach((panel) => {
    const source = panel.querySelector('[data-apply-to-all-value]');
    const button = panel.querySelector('[data-apply-to-all-button]');
    const targets = document.querySelectorAll(`${panel.dataset.applyToAll} select`);
    if (!source || !button || !targets.length) return;

    button.addEventListener('click', () => {
        targets.forEach((select) => {
            select.value = source.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
});

// "Select all" for a list of checkboxes: <ul data-check-all-group> + <input data-check-all>
document.querySelectorAll('[data-check-all-group]').forEach((group) => {
    const master = group.querySelector('[data-check-all]');
    const boxes = [...group.querySelectorAll('input[type=checkbox]')].filter((b) => b !== master);
    if (!master || !boxes.length) return;

    const sync = () => {
        const usable = boxes.filter((b) => !b.disabled);
        const checked = usable.filter((b) => b.checked).length;
        master.checked = usable.length > 0 && checked === usable.length;
        master.indeterminate = checked > 0 && checked < usable.length;
    };

    master.addEventListener('change', () => {
        boxes.filter((b) => !b.disabled).forEach((b) => (b.checked = master.checked));
        master.indeterminate = false;
    });
    boxes.forEach((b) => b.addEventListener('change', sync));
    group.addEventListener('rows-changed', sync);
});

// Don't let a student be moved into another year level or course by accident:
//   <ul data-level-guard="section_id"> with <li data-grade-level-id="3" data-strand-id="5">
// A row that doesn't match the chosen section on both is shown as not enrollable.
// A row with a value missing (not admitted into one yet) never clashes on it.
document.querySelectorAll('[data-level-guard]').forEach((list) => {
    const section = document.getElementById(list.dataset.levelGuard);
    if (!section) return;

    const sync = () => {
        const target = section.selectedOptions[0]?.dataset ?? {};

        list.querySelectorAll('li[data-grade-level-id]').forEach((row) => {
            const clashes = (own, want) => own !== '' && want !== undefined && want !== '' && own !== want;
            const wrongLevel = clashes(row.dataset.gradeLevelId, target.gradeLevelId);
            const wrongCourse = clashes(row.dataset.strandId, target.strandId);
            const clash = wrongLevel || wrongCourse;

            const box = row.querySelector('input[type=checkbox]');
            if (box) {
                box.disabled = clash;
                if (clash) box.checked = false;
            }

            const badge = row.querySelector('[data-level-warning]');
            if (badge) {
                badge.hidden = !clash;
                badge.textContent = wrongLevel && wrongCourse ? 'Another year level and course'
                    : wrongLevel ? 'Another year level'
                    : 'Another strand or course';
            }
            row.classList.toggle('opacity-50', clash);
        });

        list.dispatchEvent(new Event('rows-changed'));
    };

    section.addEventListener('change', sync);
    sync();
});

// Live list: refresh a list as the user types or changes a filter, with no page reload.
//   <form data-live-form data-live-target="#list" data-live-url="/path?partial=1">
// The server returns just the list's HTML. Falls back to a normal form submit without JS.
document.querySelectorAll('[data-live-form]').forEach((form) => {
    const target = document.querySelector(form.dataset.liveTarget);
    const url = form.dataset.liveUrl;
    if (!target || !url) return;

    let timer;
    let newest = 0; // a slow answer must never overwrite a newer one

    const load = async (extra = '') => {
        const params = new URLSearchParams(new FormData(form));
        const mine = ++newest;
        target.setAttribute('aria-busy', 'true');

        try {
            const res = await fetch(`${url}${url.includes('?') ? '&' : '?'}${params}${extra}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.ok && mine === newest) {
                target.innerHTML = await res.text();
                // Keep the address bar in step, so a refresh shows the same list
                history.replaceState(null, '', `${location.pathname}?${params}`);
            }
        } catch {
            // Offline: leave what is on screen
        }

        if (mine === newest) target.removeAttribute('aria-busy');
    };

    form.addEventListener('submit', (e) => { e.preventDefault(); load(); });
    form.querySelectorAll('select').forEach((el) => el.addEventListener('change', () => load()));
    form.querySelectorAll('input[type=search], input[type=text]').forEach((el) =>
        el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); }));

    // Paging links live inside the part that gets replaced
    target.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const page = new URL(link.href, location.origin).searchParams.get('page');
        if (!page) return;
        e.preventDefault();
        load(`&page=${encodeURIComponent(page)}`);
    });
});

// Popups built on the browser's own <dialog>, so Esc and focus trapping come for free:
//   <button data-dialog-open="my-dialog">  …  <dialog id="my-dialog"> … <button data-dialog-close>
document.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
    const dialog = document.getElementById(trigger.dataset.dialogOpen);
    if (!dialog) return;
    trigger.addEventListener('click', () => dialog.showModal());
});

document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.querySelectorAll('[data-dialog-close]').forEach((btn) =>
        btn.addEventListener('click', () => dialog.close()));

    // Clicking the dark area outside the panel closes it
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close();
    });
});

// Bring a section into view on load — used so a sign-in error is never left off-screen
// above the fold on a phone, where the form is the second screen down.
document.querySelectorAll('[data-scroll-into-view]').forEach((el) => {
    if (window.innerWidth < 1024) el.scrollIntoView({ block: 'start' });
});

// Focus a field on wide screens only (see the note on the sign-in email box)
document.querySelectorAll('[data-focus-on-wide]').forEach((el) => {
    if (window.innerWidth >= 1024) el.focus();
});

// The student help assistant in the corner. Answers come from the server
// (config/assistant.php), so nothing about the system is duplicated here.
document.querySelectorAll('[data-assistant]').forEach((root) => {
    const panel = root.querySelector('[data-assistant-panel]');
    const log = root.querySelector('[data-assistant-log]');
    const form = root.querySelector('[data-assistant-form]');
    const input = root.querySelector('[data-assistant-input]');
    const opener = root.querySelector('[data-assistant-open]');
    const url = root.dataset.assistantUrl;
    if (!panel || !log || !form || !input || !opener || !url) return;

    const token = document.querySelector('meta[name=csrf-token]')?.content ?? '';

    const show = (open) => {
        panel.hidden = !open;
        opener.hidden = open;
        if (open) input.focus();
    };

    const bubble = (text, mine) => {
        const el = document.createElement('div');
        el.className = mine
            ? 'ml-auto w-fit max-w-[85%] rounded-2xl rounded-tr-sm bg-gold-gradient px-4 py-2.5 text-ink-900'
            : 'w-fit max-w-[90%] rounded-2xl rounded-tl-sm bg-ivory-200 px-4 py-3 text-ink-900';
        el.textContent = text;
        log.append(el);
        log.scrollTop = log.scrollHeight;
        return el;
    };

    const ask = async (question) => {
        bubble(question, true);
        input.value = '';
        const thinking = bubble('…', false);

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
                body: JSON.stringify({ question }),
            });
            const data = await res.json();

            thinking.textContent = data.answer ?? 'Sorry, something went wrong. Please try again.';

            // A button to the page being talked about, when there is one
            if (data.url && data.label) {
                const link = document.createElement('a');
                link.href = data.url;
                link.className = 'btn-outline-gold mt-3 px-3 py-1.5 text-xs';
                link.textContent = data.label;
                thinking.append(link);
            }
        } catch {
            thinking.textContent = 'I could not reach EduVers just now. Please try again.';
        }

        log.scrollTop = log.scrollHeight;
    };

    opener.addEventListener('click', () => show(true));
    root.querySelector('[data-assistant-close]')?.addEventListener('click', () => show(false));
    document.addEventListener('keydown', (e) => e.key === 'Escape' && !panel.hidden && show(false));

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const question = input.value.trim();
        if (question) ask(question);
    });

    root.querySelectorAll('[data-assistant-example]').forEach((chip) =>
        chip.addEventListener('click', () => ask(chip.textContent.trim())));
});
