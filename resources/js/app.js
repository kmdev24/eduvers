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

// Live header clock in the school's timezone (Asia/Manila), 12-hour with AM/PM
document.querySelectorAll('[data-live-clock]').forEach((clock) => {
    const timeZone = clock.dataset.timezone || 'Asia/Manila';
    const dateEl = clock.querySelector('[data-clock-date]');
    const timeEl = clock.querySelector('[data-clock-time] time');

    const dateFormat = new Intl.DateTimeFormat('en-US', {
        timeZone, weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
    });
    const timeFormat = new Intl.DateTimeFormat('en-US', {
        timeZone, hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true,
    });

    const tick = () => {
        const now = new Date();
        if (dateEl) dateEl.textContent = dateFormat.format(now);
        if (timeEl) timeEl.textContent = timeFormat.format(now);
    };

    tick();
    setInterval(tick, 1000);

    // Phones: take turns showing the date and the time every N seconds
    // (data-clock-rotate="5"). CSS hides the inactive one below the sm breakpoint.
    const seconds = Number(clock.dataset.clockRotate) || 0;
    if (seconds > 0) {
        setInterval(() => {
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
