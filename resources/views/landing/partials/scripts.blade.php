{{--
    Landing page behaviour. Plain JS on purpose: the page renders no Livewire
    component, so Livewire's bundled Alpine isn't injected here, and the page
    must not depend on the Vite build either. Everything degrades to the
    fully server-rendered content without JS (or with reduced motion).
--}}
<script>
(() => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    // ── Copy buttons ────────────────────────────────────────────────────
    $$('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const text = btn.getAttribute('data-copy');
            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (_) {}
                ta.remove();
            }
            btn.classList.add('copied');
            setTimeout(() => btn.classList.remove('copied'), 1600);
        });
    });

    // ── Scroll reveal ───────────────────────────────────────────────────
    const onView = (els, cb, opts = { threshold: 0.18 }) => {
        if (!('IntersectionObserver' in window)) { els.forEach(cb); return; }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) { cb(e.target); io.unobserve(e.target); }
            });
        }, opts);
        els.forEach((el) => io.observe(el));
    };
    onView($$('.reveal'), (el) => el.classList.add('in'), { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    // ── Statement: words light up as the paragraph scrolls through ──────
    const words = $$('.statement .w');
    if (words.length) {
        const para = $('.statement p');
        let ticking = false;
        const paint = () => {
            ticking = false;
            const r = para.getBoundingClientRect();
            const vh = window.innerHeight;
            // 0 when the paragraph's top hits 85% of the viewport, 1 when its bottom reaches 45%.
            const progress = reduced ? 1 : Math.min(1, Math.max(0, (vh * 0.85 - r.top) / (r.height + vh * 0.4)));
            const lit = Math.round(progress * words.length);
            words.forEach((w, i) => w.classList.toggle('on', i < lit));
        };
        const queue = () => { if (!ticking) { ticking = true; requestAnimationFrame(paint); } };
        window.addEventListener('scroll', queue, { passive: true });
        window.addEventListener('resize', queue);
        paint();
    }

    // ── Feature grid: cursor spotlight ──────────────────────────────────
    $$('#feature-grid .cell').forEach((cell) => {
        cell.addEventListener('pointermove', (e) => {
            const r = cell.getBoundingClientRect();
            cell.style.setProperty('--mx', `${e.clientX - r.left}px`);
            cell.style.setProperty('--my', `${e.clientY - r.top}px`);
        });
    });

    // ── Counters ────────────────────────────────────────────────────────
    onView($$('[data-count]'), (el) => {
        const target = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (reduced || target === 0) { el.textContent = target; return; }
        const start = performance.now();
        const dur = 1100;
        el.textContent = '0';
        const step = (t) => {
            const k = Math.min(1, (t - start) / dur);
            el.textContent = Math.round(target * (1 - Math.pow(1 - k, 3)));
            if (k < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }, { threshold: 0.6 });

    // ── Code tabs (WAI-ARIA tabs pattern, arrow keys move between tabs) ──
    $$('[data-tabs]').forEach((win) => {
        const tabs = $$('[role="tab"]', win);
        const select = (tab) => {
            tabs.forEach((t) => {
                const on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                document.getElementById(t.getAttribute('aria-controls')).classList.toggle('active', on);
            });
        };
        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => select(tab));
            tab.addEventListener('keydown', (e) => {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                e.preventDefault();
                const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                next.focus();
                select(next);
            });
        });
    });

    // ── Deploy terminal: types itself out once it scrolls into view ─────
    const term = $('#terminal');
    if (term && !reduced) {
        const lines = $$('.ln', term).map((ln) => {
            const tx = $('.tx', ln);
            return { ln, tx, text: tx.textContent, kind: ln.dataset.kind };
        });
        lines.forEach(({ ln, tx }) => { tx.textContent = ''; ln.style.display = 'none'; });
        const caret = document.createElement('span');
        caret.className = 'caret';

        onView([term], async () => {
            const wait = (ms) => new Promise((r) => setTimeout(r, ms));
            for (const { ln, tx, text, kind } of lines) {
                ln.style.display = '';
                ln.appendChild(caret);
                if (kind === 'cmd') {
                    for (let i = 1; i <= text.length; i++) {
                        tx.textContent = text.slice(0, i);
                        await wait(text[i - 1] === ' ' ? 30 : 14);
                    }
                    await wait(260);
                } else {
                    tx.textContent = text;
                    await wait(kind === 'out' ? 0 : 160);
                }
            }
        }, { threshold: 0.35 });
    }

    // ── Hero flow: wires from each source chip into the inbox ───────────
    const flow = $('#flow');
    const svg = $('#wires');
    const rowsEl = $('#rows');
    if (!flow || !svg || !rowsEl) return;

    const NS = 'http://www.w3.org/2000/svg';
    const chips = $$('.src', flow);
    const pulses = new Map();

    const drawWires = () => {
        const box = svg.getBoundingClientRect();
        if (box.width === 0) return; // hidden on narrow screens
        const inbox = $('.inbox', flow).getBoundingClientRect();
        const endY = inbox.top + inbox.height / 2 - box.top;
        svg.setAttribute('viewBox', `0 0 ${box.width} ${box.height}`);
        svg.innerHTML = '';
        chips.forEach((chip, i) => {
            const c = chip.getBoundingClientRect();
            const y = c.top + c.height / 2 - box.top;
            const spread = (i - (chips.length - 1) / 2) * 6; // fan into the inbox edge
            const d = `M 0 ${y} C ${box.width * 0.55} ${y}, ${box.width * 0.45} ${endY + spread}, ${box.width} ${endY + spread}`;
            const base = document.createElementNS(NS, 'path');
            base.setAttribute('d', d);
            const pulse = document.createElementNS(NS, 'path');
            pulse.setAttribute('d', d);
            pulse.setAttribute('pathLength', '100');
            pulse.setAttribute('class', 'pulse');
            pulse.style.setProperty('--c', getComputedStyle(chip).getPropertyValue('--c'));
            svg.append(base, pulse);
            pulses.set(chip.dataset.src, pulse);
        });
    };
    drawWires();
    let resizeTimer;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(drawWires, 120); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(drawWires);

    if (reduced) return;

    const pool = [
        ['Jonas Klein', 'Manual entry', 'Phone call', 'High priority', 'hot'],
        ['Aylin Demir', 'CSV upload', 'Event import', 'New', 'new'],
        ['Marco Rossi', 'Meta Lead Ads', 'Retargeting', 'Duplicate', 'dup'],
        ['Clara Wagner', 'Google Sheets', 'Partner referrals', 'New', 'new'],
        ['Felix Schmidt', 'Webhook', '/contact form', 'Qualified', 'ok'],
        ['Mia Fischer', 'OpenFlow', 'Quote request', 'New', 'new'],
        ['Noah Weber', 'IMAP email', 'Inbound inquiry', 'New', 'new'],
        ['Emma Braun', 'Meta Lead Ads', 'Spring launch', 'High priority', 'hot'],
        ['Tom Becker', 'Webhook', '/pricing form', 'Duplicate', 'dup'],
        ['Lea Neumann', 'Google Sheets', 'Trade fair list', 'Qualified', 'ok'],
    ];
    const colorOf = (src) => {
        const chip = chips.find((c) => c.dataset.src === src);
        return chip ? getComputedStyle(chip).getPropertyValue('--c').trim() : '#818cf8';
    };
    const countEl = $('#inbox-count');
    let count = parseInt(countEl.textContent, 10) || 0;
    let n = 0;

    const makeRow = ([name, src, campaign, pill, kind]) => {
        const li = document.createElement('li');
        li.className = 'row new';
        li.style.setProperty('--c', colorOf(src));
        const initials = name.split(' ').map((p) => p[0]).join('');
        li.innerHTML = `<span class="avatar"></span><span class="who"><span class="name"></span><span class="meta"></span></span><span class="pill pill-${kind}"></span>`;
        $('.avatar', li).textContent = initials;
        $('.name', li).textContent = name;
        $('.meta', li).textContent = `${src} · ${campaign} · just now`;
        $('.pill', li).textContent = pill;
        return li;
    };

    const tick = () => {
        if (document.hidden) return;
        const lead = pool[n++ % pool.length];
        const chip = chips.find((c) => c.dataset.src === lead[1]);
        const pulse = pulses.get(lead[1]);
        chip?.classList.add('ping');
        if (pulse) {
            pulse.classList.remove('go');
            void pulse.getBoundingClientRect(); // restart the CSS animation
            pulse.classList.add('go');
        }
        setTimeout(() => {
            chip?.classList.remove('ping');
            rowsEl.prepend(makeRow(lead));
            // Age the previous "just now" rows and keep the list at five.
            $$('.row', rowsEl).slice(1).forEach((r) => r.classList.remove('new'));
            while (rowsEl.children.length > 5) rowsEl.lastElementChild.remove();
            if (lead[4] !== 'dup') countEl.textContent = ++count;
        }, 900);
    };
    setTimeout(() => { tick(); setInterval(tick, 2600); }, 1800);
})();
</script>
