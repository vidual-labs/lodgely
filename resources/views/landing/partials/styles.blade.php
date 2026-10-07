{{--
    Landing page styles. Deliberately hand-written and inline rather than
    Tailwind utilities: the page is brand-new surface, and per the CLAUDE.md
    gotcha a stale production CSS bundle would silently drop any utility
    that only just landed. Inline CSS also keeps the page free of any
    build-step or third-party dependency (no web fonts, no CDNs).
--}}
<style>
    :root {
        --bg: #050507;
        --bg-2: #0a0a0f;
        --bg-3: #101018;
        --line: rgba(255, 255, 255, .08);
        --line-2: rgba(255, 255, 255, .14);
        --line-3: rgba(255, 255, 255, .28);
        --text: #f4f4f6;
        --muted: rgba(244, 244, 246, .64);
        --dim: rgba(244, 244, 246, .42);
        --sky: #60a5fa;
        --indigo: #818cf8;
        --violet: #a78bfa;
        --fuchsia: #e879f9;
        --ok: #34d399;
        --warn: #fbbf24;
        --sans: "InterVariable", "Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        --mono: ui-monospace, "SFMono-Regular", "JetBrains Mono", Menlo, Consolas, monospace;
        --shell: 1240px;
        --ease: cubic-bezier(.2, .7, .2, 1);
        color-scheme: dark;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body {
        margin: 0;
        background: var(--bg);
        color: var(--text);
        font-family: var(--sans);
        font-size: 16px;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        font-feature-settings: "cv02", "cv03", "cv04", "cv11";
        overflow-x: hidden;
    }
    a { color: inherit; text-decoration: none; }
    img, svg { display: block; }
    button { font: inherit; color: inherit; background: none; border: 0; cursor: pointer; padding: 0; }
    ::selection { background: rgba(129, 140, 248, .35); }
    :focus-visible { outline: 2px solid var(--indigo); outline-offset: 3px; border-radius: 4px; }

    .skip { position: absolute; left: -9999px; top: 8px; z-index: 100; background: var(--text); color: var(--bg); padding: 8px 14px; border-radius: 6px; }
    .skip:focus { left: 16px; }

    /* ── Shell: the full-height vertical rails Payload-style layouts hang off ── */
    .shell {
        position: relative;
        max-width: var(--shell);
        margin: 0 auto;
        border-left: 1px solid var(--line);
        border-right: 1px solid var(--line);
    }
    .sec { position: relative; border-top: 1px solid var(--line); }
    /* "+" crosshair marks where section dividers meet the rails */
    .sec::before, .sec::after {
        content: "";
        position: absolute;
        top: -6px;
        width: 11px;
        height: 11px;
        background:
            linear-gradient(var(--line-3), var(--line-3)) center / 1px 100% no-repeat,
            linear-gradient(var(--line-3), var(--line-3)) center / 100% 1px no-repeat;
        z-index: 2;
        pointer-events: none;
    }
    .sec::before { left: -6px; }
    .sec::after { right: -6px; }

    .pad { padding: 96px 56px; }
    .pad-sm { padding: 56px 56px; }

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-family: var(--mono);
        font-size: 12px;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--dim);
    }
    .eyebrow .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ok); box-shadow: 0 0 12px var(--ok); animation: pulse 2.4s ease-in-out infinite; }

    h1, h2, h3 { margin: 0; font-weight: 600; letter-spacing: -.035em; line-height: 1.04; }
    h2 { font-size: clamp(34px, 4.6vw, 56px); }
    h3 { font-size: 19px; letter-spacing: -.015em; line-height: 1.3; }
    p { margin: 0; }
    .lead-text { font-size: 18px; color: var(--muted); max-width: 620px; }
    .grad {
        background: linear-gradient(90deg, var(--sky), var(--indigo) 30%, var(--violet) 60%, var(--fuchsia));
        background-size: 200% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: shimmer 8s ease-in-out infinite alternate;
    }
    code, .mono { font-family: var(--mono); }

    /* ── Buttons ── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        height: 46px;
        padding: 0 20px;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 500;
        white-space: nowrap;
        transition: background-color .2s, border-color .2s, color .2s, transform .2s var(--ease);
    }
    .btn .arrow { transition: transform .25s var(--ease); }
    .btn:hover .arrow { transform: translateX(4px); }
    .btn-solid { background: var(--text); color: #08080b; }
    .btn-solid:hover { background: #fff; }
    .btn-ghost { border: 1px solid var(--line-2); color: var(--text); background: rgba(255, 255, 255, .02); }
    .btn-ghost:hover { border-color: var(--line-3); background: rgba(255, 255, 255, .05); }
    .btn-sm { height: 36px; padding: 0 14px; font-size: 14px; }

    /* ── Nav ── */
    .nav {
        position: sticky;
        top: 0;
        z-index: 50;
        background: rgba(5, 5, 7, .72);
        -webkit-backdrop-filter: saturate(140%) blur(14px);
        backdrop-filter: saturate(140%) blur(14px);
        border-bottom: 1px solid var(--line);
    }
    .nav .shell { border-color: var(--line); display: flex; align-items: center; gap: 32px; height: 64px; padding: 0 24px; }
    .brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 600; font-size: 18px; letter-spacing: -.02em; }
    .nav .brand svg { width: 26px; height: 26px; }
    .nav .brand svg circle { animation: dotIn .6s var(--ease) both; animation-delay: calc(var(--i) * 45ms); }
    .nav-links { display: flex; gap: 4px; margin-right: auto; }
    .nav-links a { padding: 6px 12px; border-radius: 6px; font-size: 14px; color: var(--muted); transition: color .2s, background-color .2s; }
    .nav-links a:hover { color: var(--text); background: rgba(255, 255, 255, .04); }
    .nav-right { display: flex; align-items: center; gap: 8px; }
    .signin { padding: 6px 12px; font-size: 14px; color: var(--dim); transition: color .2s; }
    .signin:hover { color: var(--text); }

    /* ── Hero ── */
    .hero { overflow: hidden; border-top: 0; }
    .hero::before, .hero::after { display: none; }
    .hero-bg { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
    .hero-bg .dots {
        position: absolute;
        inset: 0;
        background-image: radial-gradient(rgba(255, 255, 255, .11) 1px, transparent 1px);
        background-size: 24px 24px;
        -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 30%, #000 30%, transparent 75%);
        mask-image: radial-gradient(ellipse 70% 60% at 50% 30%, #000 30%, transparent 75%);
    }
    .glow { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .5; }
    .glow-a { width: 520px; height: 520px; left: -120px; top: -180px; background: radial-gradient(circle, rgba(96, 165, 250, .55), transparent 65%); animation: drift 18s ease-in-out infinite alternate; }
    .glow-b { width: 560px; height: 560px; right: -160px; top: 80px; background: radial-gradient(circle, rgba(232, 121, 249, .35), transparent 65%); animation: drift 22s ease-in-out infinite alternate-reverse; }
    .glow-c { width: 420px; height: 420px; left: 40%; bottom: -260px; background: radial-gradient(circle, rgba(129, 140, 248, .5), transparent 65%); animation: drift 26s ease-in-out infinite alternate; }

    .hero-inner { position: relative; padding: 96px 56px 72px; }
    .hero h1 { font-size: clamp(46px, 8.2vw, 104px); letter-spacing: -.05em; line-height: .98; margin-top: 28px; }
    .hero h1 .line { display: block; overflow: hidden; padding-bottom: .06em; }
    .hero h1 .word { display: inline-block; animation: rise .9s var(--ease) both; animation-delay: calc(120ms + var(--i) * 70ms); }
    /* gradient words run both the entrance and the shimmer (one animation property) */
    .hero h1 .word.grad { animation: rise .9s var(--ease) both, shimmer 8s ease-in-out infinite alternate; animation-delay: calc(120ms + var(--i) * 70ms), 0s; }
    .hero .lead-text { margin-top: 28px; animation: fadeUp .9s var(--ease) .55s both; }
    .hero-ctas { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 40px; animation: fadeUp .9s var(--ease) .7s both; }
    .cmd {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        margin-top: 28px;
        padding: 10px 10px 10px 16px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: rgba(255, 255, 255, .025);
        font-family: var(--mono);
        font-size: 13px;
        color: var(--muted);
        max-width: 100%;
        animation: fadeUp .9s var(--ease) .85s both;
    }
    .cmd .prompt { color: var(--ok); }
    .cmd code { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .copy {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        color: var(--dim);
        transition: color .2s, background-color .2s;
    }
    .copy:hover { color: var(--text); background: rgba(255, 255, 255, .06); }
    .copy .ok { display: none; color: var(--ok); }
    .copy.copied .ok { display: block; }
    .copy.copied .idle { display: none; }

    /* ── Hero visual: sources → lodgely inbox ── */
    .flow {
        position: relative;
        display: grid;
        grid-template-columns: 200px 1fr minmax(0, 420px);
        align-items: center;
        margin: 56px 0 0;
        padding: 32px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: linear-gradient(180deg, rgba(255, 255, 255, .03), rgba(255, 255, 255, .005));
        animation: fadeUp 1s var(--ease) 1s both;
    }
    .flow::before {
        content: "";
        position: absolute;
        inset: -1px;
        border-radius: 14px;
        padding: 1px;
        background: linear-gradient(120deg, rgba(96, 165, 250, .5), transparent 30%, transparent 70%, rgba(232, 121, 249, .45));
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
    }
    .sources { display: flex; flex-direction: column; gap: 10px; }
    .src {
        display: flex;
        align-items: center;
        gap: 10px;
        height: 38px;
        padding: 0 12px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--bg-2);
        font-size: 13px;
        color: var(--muted);
        transition: border-color .3s, color .3s, box-shadow .3s;
    }
    .src i { width: 7px; height: 7px; border-radius: 2px; background: var(--c, var(--indigo)); flex: none; }
    .src.ping { border-color: color-mix(in srgb, var(--c) 60%, transparent); color: var(--text); box-shadow: 0 0 0 4px color-mix(in srgb, var(--c) 12%, transparent); }
    .wires { width: 100%; height: 100%; min-height: 330px; overflow: visible; }
    .wires path { fill: none; stroke: var(--line-2); stroke-width: 1; vector-effect: non-scaling-stroke; }
    .wires path.pulse { stroke: var(--c); stroke-width: 2; stroke-dasharray: 6 94; stroke-dashoffset: 100; stroke-linecap: round; opacity: 0; }
    .wires path.pulse.go { animation: travel 1.1s var(--ease) forwards; }

    .inbox {
        position: relative;
        border: 1px solid var(--line-2);
        border-radius: 12px;
        background: var(--bg-2);
        overflow: hidden;
        box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .8), 0 0 0 1px rgba(255, 255, 255, .02) inset;
    }
    .inbox-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
    .inbox-head .lights { display: flex; gap: 6px; }
    .inbox-head .lights span { width: 9px; height: 9px; border-radius: 50%; background: rgba(255, 255, 255, .12); }
    .inbox-head .title { font-weight: 600; }
    .inbox-head .count { margin-left: auto; font-family: var(--mono); font-size: 11px; color: var(--dim); }
    .inbox-head .count b { color: var(--text); font-weight: 500; }
    .rows { list-style: none; margin: 0; padding: 0; height: 288px; overflow: hidden; }
    .row {
        display: grid;
        grid-template-columns: 28px 1fr auto;
        align-items: center;
        gap: 12px;
        height: 57.6px;
        padding: 0 14px;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
    }
    .row.new { animation: rowIn .7s var(--ease) both; background: linear-gradient(90deg, color-mix(in srgb, var(--c) 10%, transparent), transparent 70%); }
    .avatar { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-size: 11px; font-weight: 600; color: #0a0a0f; background: var(--c); }
    .row .who { min-width: 0; }
    .row .name, .row .meta { display: block; }
    .row .name { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .row .meta { font-family: var(--mono); font-size: 11px; color: var(--dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pill { font-family: var(--mono); font-size: 10.5px; letter-spacing: .03em; padding: 3px 8px; border-radius: 999px; border: 1px solid; white-space: nowrap; }
    .pill-new { color: var(--sky); border-color: rgba(96, 165, 250, .35); background: rgba(96, 165, 250, .08); }
    .pill-dup { color: var(--warn); border-color: rgba(251, 191, 36, .35); background: rgba(251, 191, 36, .08); }
    .pill-ok { color: var(--ok); border-color: rgba(52, 211, 153, .35); background: rgba(52, 211, 153, .08); }
    .pill-hot { color: var(--fuchsia); border-color: rgba(232, 121, 249, .35); background: rgba(232, 121, 249, .08); }

    /* ── Marquee ── */
    .marquee { overflow: hidden; padding: 26px 0; -webkit-mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent); mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent); }
    .marquee-track { display: flex; width: max-content; animation: marquee 40s linear infinite; }
    .marquee:hover .marquee-track { animation-play-state: paused; }
    .marquee-item { display: inline-flex; align-items: center; gap: 12px; padding: 0 28px; font-family: var(--mono); font-size: 13px; letter-spacing: .06em; text-transform: uppercase; color: var(--dim); white-space: nowrap; }
    .marquee-item::before { content: ""; width: 5px; height: 5px; border-radius: 1px; background: var(--line-3); transform: rotate(45deg); }
    .marquee-label { padding: 0 56px 0; margin-bottom: -6px; padding-top: 22px; }

    /* ── Statement (scroll-linked word reveal) ── */
    .statement p { font-size: clamp(28px, 3.6vw, 46px); font-weight: 500; letter-spacing: -.03em; line-height: 1.18; max-width: 1000px; }
    .statement .w { opacity: .18; transition: opacity .25s linear; }
    .statement .w.on { opacity: 1; }
    .statement .w.hl.on { color: var(--violet); }
    .no-js .statement .w { opacity: 1; }

    /* ── Section heads ── */
    .head { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: end; margin-bottom: 56px; }
    .head .eyebrow { margin-bottom: 20px; }

    /* ── Highlighted feature sections (copy + animated graphic) ── */
    .feat { overflow: hidden; }
    .feat-grid { display: grid; grid-template-columns: 5fr 7fr; gap: 64px; align-items: center; padding: 104px 56px; }
    .feat-grid.flip { grid-template-columns: 7fr 5fr; }
    .feat-grid.flip .feat-copy { order: 2; }
    .feat .eyebrow .n { color: var(--a); }
    .feat h2 { margin-top: 20px; font-size: clamp(32px, 4vw, 50px); }
    .feat .lead-text { margin-top: 22px; font-size: 17px; }
    .feat .lead-text code { font-size: 14px; color: var(--text); }
    .feat-list { list-style: none; margin: 28px 0 0; padding: 0; display: grid; gap: 10px; }
    .feat-list li { position: relative; padding-left: 18px; font-size: 15px; color: var(--muted); }
    .feat-list li::before { content: ""; position: absolute; left: 0; top: .62em; width: 6px; height: 6px; border-radius: 1px; background: var(--a); transform: rotate(45deg); }
    .feat-list code { font-size: 13px; color: var(--text); }

    .viz {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 14px;
        min-height: 420px;
        padding: 32px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background:
            radial-gradient(120% 70% at 50% 0%, color-mix(in srgb, var(--a) 13%, transparent), transparent 65%),
            linear-gradient(180deg, rgba(255, 255, 255, .03), rgba(255, 255, 255, .005));
        font-size: 13px;
    }
    .viz::after {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: 16px;
        background-image: radial-gradient(rgba(255, 255, 255, .07) 1px, transparent 1px);
        background-size: 18px 18px;
        -webkit-mask-image: radial-gradient(ellipse at 50% 40%, #000, transparent 70%);
        mask-image: radial-gradient(ellipse at 50% 40%, #000, transparent 70%);
        pointer-events: none;
        z-index: 0;
    }
    .viz > * { position: relative; z-index: 1; }
    .vz-card { border: 1px solid var(--line-2); border-radius: 12px; background: var(--bg-2); padding: 14px 16px; transition: border-color .3s, box-shadow .3s; }
    .vz-row { display: grid; grid-template-columns: 32px 1fr auto; align-items: center; gap: 12px; }
    .vz-row .avatar { width: 32px; height: 32px; }
    .vz-row .who b { display: block; font-weight: 500; font-size: 14px; }
    .vz-row .who small { display: block; font-family: var(--mono); font-size: 11px; color: var(--dim); }
    .vz-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; font-weight: 500; }
    .vz-head span:first-child { display: inline-flex; align-items: center; gap: 6px; }
    .vz-head .mono { font-size: 11px; color: var(--dim); font-weight: 400; }

    /* 01 · automation — 10s loop that starts and ends on the resting (final) frame */
    .swap { display: grid; justify-items: end; }
    .swap > * { grid-area: 1 / 1; }
    .au-phone { font-family: var(--mono); font-size: 12px; color: var(--sky); padding: 3px 8px; border-radius: 6px; text-decoration: underline; text-underline-offset: 3px; }
    .au-group { display: flex; align-items: center; gap: 12px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }
    .au-group small { width: 64px; flex: none; font-family: var(--mono); font-size: 11px; color: var(--dim); }
    .au-pills { display: flex; flex-wrap: wrap; gap: 6px; }
    .au-pill, .au-tog { padding: 4px 10px; border: 1px solid var(--line-2); border-radius: 999px; font-size: 12px; color: var(--dim); }
    .au-pill.p-pen { color: var(--warn); border-color: rgba(251, 191, 36, .45); background: rgba(251, 191, 36, .1); }
    .au-tog.t-called { color: var(--ok); border-color: rgba(52, 211, 153, .45); background: rgba(52, 211, 153, .1); }
    .au-ai .vz-head { margin-bottom: 8px; }
    .au-ai { position: relative; }
    .au-wait { position: absolute; left: 0; right: 0; top: 50%; text-align: center; font-family: var(--mono); font-size: 12px; color: var(--dim); opacity: 0; }
    .au-wait i { font-style: normal; animation: pulse 1.2s ease-in-out infinite; }
    .au-wait i:nth-child(2) { animation-delay: .2s; }
    .au-wait i:nth-child(3) { animation-delay: .4s; }
    .au-ai .vz-head span:first-child { color: var(--violet); }
    .au-draft { color: var(--dim); border-color: var(--line-2); opacity: 0; }
    .au-in { display: block; font-size: 11.5px; color: var(--dim); padding: 6px 8px; border: 1px dashed var(--line-2); border-radius: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .au-out { margin-top: 10px; }
    .au-prio { font-size: 12px; color: var(--muted); }
    .au-prio b { margin-left: 6px; padding: 2px 8px; border-radius: 999px; font-family: var(--mono); font-size: 11px; font-weight: 500; color: var(--fuchsia); background: rgba(232, 121, 249, .1); border: 1px solid rgba(232, 121, 249, .35); }
    .au-out p { margin-top: 6px; font-size: 12.5px; color: var(--muted); }
    .au-out .au-next { color: var(--text); }
    .au-caps { justify-items: center; }
    .au-cap { font-family: var(--mono); font-size: 12px; color: var(--dim); text-align: center; opacity: 0; }
    .au-cap b { color: var(--text); font-weight: 500; }
    .au-cap.c4 { opacity: 1; }

    .viz-auto.play .p-new { animation: auNew 10s var(--ease) infinite; }
    .viz-auto.play .p-rev { animation: auRev 10s var(--ease) infinite; }
    .viz-auto.play .p-pen { animation: auPen 10s var(--ease) infinite; }
    .viz-auto.play .t-called { animation: auCalled 10s var(--ease) infinite; }
    .viz-auto.play .au-phone { animation: auPhone 10s var(--ease) infinite; }
    .viz-auto.play .au-in { animation: auIn 10s var(--ease) infinite; }
    .viz-auto.play .au-wait { animation: auWait 10s var(--ease) infinite; }
    .viz-auto.play .au-out { animation: auOut 10s var(--ease) infinite; }
    .viz-auto.play .au-draft { animation: auDraft 10s var(--ease) infinite; }
    .viz-auto.play .au-approved { animation: auApproved 10s var(--ease) infinite; }
    .viz-auto.play .c0 { animation: auC0 10s var(--ease) infinite; }
    .viz-auto.play .c1 { animation: auC1 10s var(--ease) infinite; }
    .viz-auto.play .c2 { animation: auC2 10s var(--ease) infinite; }
    .viz-auto.play .c3 { animation: auC3 10s var(--ease) infinite; }
    .viz-auto.play .c4 { animation: auC4 10s var(--ease) infinite; }
    @keyframes auNew {
        0%, 6%, 20%, 100% { color: var(--dim); border-color: var(--line-2); background: transparent; }
        9%, 17% { color: var(--sky); border-color: rgba(96, 165, 250, .45); background: rgba(96, 165, 250, .1); }
    }
    @keyframes auRev {
        0%, 17%, 46%, 100% { color: var(--dim); border-color: var(--line-2); background: transparent; }
        20%, 43% { color: var(--violet); border-color: rgba(167, 139, 250, .45); background: rgba(167, 139, 250, .1); }
    }
    @keyframes auPen {
        0%, 6%, 46%, 100% { color: var(--warn); border-color: rgba(251, 191, 36, .45); background: rgba(251, 191, 36, .1); }
        9%, 43% { color: var(--dim); border-color: var(--line-2); background: transparent; }
    }
    @keyframes auCalled {
        0%, 6%, 46%, 100% { color: var(--ok); border-color: rgba(52, 211, 153, .45); background: rgba(52, 211, 153, .1); box-shadow: none; }
        9%, 31% { color: var(--dim); border-color: var(--line-2); background: transparent; box-shadow: none; }
        34%, 43% { color: var(--warn); border-color: rgba(251, 191, 36, .6); background: transparent; box-shadow: 0 0 0 4px rgba(251, 191, 36, .14); }
        38% { box-shadow: 0 0 0 7px rgba(251, 191, 36, .06); }
    }
    @keyframes auPhone { 0%, 30%, 37%, 100% { background: transparent; } 32%, 34% { background: rgba(96, 165, 250, .18); } }
    @keyframes auWait { 0%, 8%, 56%, 100% { opacity: 0; } 11%, 53% { opacity: 1; } }
    @keyframes auIn { 0%, 6% { opacity: 1; } 9%, 54% { opacity: 0; } 58%, 100% { opacity: 1; } }
    @keyframes auOut { 0%, 6% { opacity: 1; transform: none; } 9%, 64% { opacity: 0; transform: translateY(6px); } 69%, 100% { opacity: 1; transform: none; } }
    @keyframes auDraft { 0%, 6%, 80%, 100% { opacity: 0; } 9%, 76% { opacity: 1; } }
    @keyframes auApproved { 0%, 6% { opacity: 1; transform: none; } 9%, 76% { opacity: 0; transform: scale(.85); } 81% { opacity: 1; transform: scale(1.1); } 84%, 100% { opacity: 1; transform: none; } }
    @keyframes auC0 { 0%, 7%, 18%, 100% { opacity: 0; } 9%, 16% { opacity: 1; } }
    @keyframes auC1 { 0%, 18%, 31%, 100% { opacity: 0; } 20%, 29% { opacity: 1; } }
    @keyframes auC2 { 0%, 31%, 44%, 100% { opacity: 0; } 33%, 42% { opacity: 1; } }
    @keyframes auC3 { 0%, 44%, 56%, 100% { opacity: 0; } 46%, 54% { opacity: 1; } }
    @keyframes auC4 { 0%, 6%, 58%, 100% { opacity: 1; } 8%, 56% { opacity: 0; } }

    /* 02 · reporting */
    .kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
    .kpis .vz-card { padding: 14px; }
    .kpis small { display: block; font-family: var(--mono); font-size: 11px; color: var(--dim); text-transform: uppercase; letter-spacing: .06em; }
    .kpis b { display: block; margin-top: 6px; font-size: 24px; font-weight: 600; letter-spacing: -.03em; font-variant-numeric: tabular-nums; }
    .kpis em { font-style: normal; font-family: var(--mono); font-size: 11px; }
    .kpis em.up { color: var(--ok); }
    .chart { padding-bottom: 8px; }
    .chart-head { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 8px; font-size: 12px; color: var(--muted); }
    .legend { display: inline-flex; align-items: center; gap: 6px; font-family: var(--mono); font-size: 11px; color: var(--dim); }
    .legend i { width: 8px; height: 8px; border-radius: 2px; background: var(--c); margin-left: 6px; }
    .chart svg { width: 100%; height: 120px; overflow: visible; }
    .chart .gl { stroke: var(--line); stroke-width: 1; fill: none; vector-effect: non-scaling-stroke; }
    .chart .line { fill: none; stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; vector-effect: non-scaling-stroke; stroke-dasharray: 2000; stroke-dashoffset: 0; }
    /* dash units are screen px under non-scaling-stroke, so pathLength can't be used here */
    .chart .l-meta { stroke: #60a5fa; }
    .chart .l-google { stroke: #e879f9; }
    .bars { display: grid; gap: 10px; }
    .bar { display: grid; grid-template-columns: 130px 1fr 120px; align-items: center; gap: 12px; font-size: 12px; }
    .bar small { color: var(--dim); font-family: var(--mono); font-size: 10.5px; }
    .bar-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-track { height: 8px; border-radius: 4px; background: rgba(255, 255, 255, .05); overflow: hidden; }
    .bar-fill { display: block; height: 100%; width: var(--w); border-radius: 4px; background: linear-gradient(90deg, color-mix(in srgb, var(--c) 50%, transparent), var(--c)); transform-origin: left; }
    .bar-val { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .js .viz-report .line { stroke-dashoffset: 2000; }
    .js .viz-report .area { opacity: 0; }
    .js .viz-report .bar-fill { transform: scaleX(0); }
    .viz-report.play .line { animation: draw 1.8s var(--ease) .2s forwards; }
    .viz-report.play .l-google { animation-delay: .5s; }
    .viz-report.play .area { animation: fadeIn 1.2s var(--ease) 1s forwards; }
    .viz-report.play .bar-fill { animation: grow 1.1s var(--ease) forwards; animation-delay: calc(.5s + var(--i) * 140ms); }
    @keyframes draw { to { stroke-dashoffset: 0; } }
    @keyframes grow { to { transform: scaleX(1); } }

    /* 03 · client portals */
    .viz-clients { gap: 16px; }
    .cl-rows { list-style: none; margin: 0; padding: 0; display: grid; gap: 4px; }
    .cl-rows li { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 6px; color: var(--muted); transition: background-color .3s, color .3s; }
    .cl-rows li i { width: 8px; height: 8px; border-radius: 50%; background: var(--cc); flex: none; }
    .cl-rows li span { margin-left: auto; font-family: var(--mono); font-size: 11px; color: var(--dim); }
    .c-n { --cc: #60a5fa; }
    .c-a { --cc: #e879f9; }
    .c-b { --cc: #34d399; }
    .op .cl-rows { grid-template-columns: 1fr 1fr; column-gap: 12px; }
    .clients { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .cl { display: flex; flex-direction: column; }
    .cl-n { --cc: #60a5fa; }
    .cl-a { --cc: #e879f9; }
    .cl .vz-head span:first-child { color: var(--cc); }
    .cl-export { margin-top: 10px; align-self: flex-start; font-family: var(--mono); font-size: 11px; padding: 3px 8px; border: 1px solid var(--line-2); border-radius: 5px; color: var(--muted); }
    .viz-clients.play .op .c-n, .viz-clients.play .cl-n li { animation: clRowA 8s var(--ease) infinite; }
    .viz-clients.play .op .c-a, .viz-clients.play .cl-a li { animation: clRowB 8s var(--ease) infinite; }
    .viz-clients.play .cl-n { animation: clCardA 8s var(--ease) infinite; }
    .viz-clients.play .cl-a { animation: clCardB 8s var(--ease) infinite; }
    .viz-clients.play .op .c-b { animation: clDim 8s var(--ease) infinite; }
    @keyframes clRowA { 0%, 5%, 45%, 100% { background: transparent; color: var(--muted); } 10%, 40% { background: color-mix(in srgb, var(--cc) 14%, transparent); color: var(--text); } }
    @keyframes clRowB { 0%, 50%, 95%, 100% { background: transparent; color: var(--muted); } 55%, 90% { background: color-mix(in srgb, var(--cc) 14%, transparent); color: var(--text); } }
    @keyframes clCardA { 0%, 5%, 45%, 100% { border-color: var(--line-2); box-shadow: none; } 10%, 40% { border-color: color-mix(in srgb, var(--cc) 55%, transparent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--cc) 10%, transparent), 0 20px 50px -20px color-mix(in srgb, var(--cc) 40%, transparent); } }
    @keyframes clCardB { 0%, 50%, 95%, 100% { border-color: var(--line-2); box-shadow: none; } 55%, 90% { border-color: color-mix(in srgb, var(--cc) 55%, transparent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--cc) 10%, transparent), 0 20px 50px -20px color-mix(in srgb, var(--cc) 40%, transparent); } }
    @keyframes clDim { 0%, 5%, 95%, 100% { opacity: 1; } 10%, 90% { opacity: .35; } }

    /* 04 · privacy */
    .ret-row { display: flex; justify-content: space-between; align-items: baseline; color: var(--muted); }
    .ret-row b { color: var(--text); font-weight: 500; font-size: 13px; }
    .ret-track { display: block; height: 6px; margin: 10px 0 8px; border-radius: 3px; background: rgba(255, 255, 255, .06); overflow: hidden; }
    .ret-fill { position: relative; display: block; width: 72%; height: 100%; border-radius: 3px; background: linear-gradient(90deg, rgba(52, 211, 153, .4), var(--ok)); overflow: hidden; }
    .ret-fill::after { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .55), transparent); transform: translateX(-100%); }
    .viz-privacy.play .ret-fill::after { animation: shine 2.8s ease-in-out infinite; }
    .ret small { font-size: 11px; color: var(--dim); }
    .ticker { height: 132px; overflow: hidden; -webkit-mask-image: linear-gradient(transparent, #000 22%, #000 78%, transparent); mask-image: linear-gradient(transparent, #000 22%, #000 78%, transparent); }
    .ticker ul { list-style: none; margin: 0; padding: 0; }
    .viz-privacy.play .ticker ul { animation: tick 18s linear infinite; }
    .ticker li { display: flex; gap: 12px; height: 33px; align-items: center; border-bottom: 1px dashed var(--line); font-family: var(--mono); font-size: 12px; color: var(--muted); white-space: nowrap; }
    .ticker .t { color: var(--dim); }
    .ticker .tk-by { margin-left: auto; color: var(--dim); }
    .badges { display: flex; flex-wrap: wrap; gap: 8px; }
    .badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border: 1px solid var(--line-2); border-radius: 999px; background: var(--bg-2); font-family: var(--mono); font-size: 11.5px; color: var(--muted); }
    .badge::before { content: "✓"; color: var(--ok); }
    .badge.live::before { content: none; }
    .badge.live { color: var(--text); border-color: rgba(52, 211, 153, .4); }
    .badge.live i { width: 7px; height: 7px; border-radius: 50%; background: var(--ok); box-shadow: 0 0 0 0 rgba(52, 211, 153, .6); }
    .viz-privacy.play .badge.live i { animation: ring 2s ease-out infinite; }
    @keyframes shine { 0% { transform: translateX(-100%); } 60%, 100% { transform: translateX(100%); } }
    @keyframes tick { to { transform: translateY(-50%); } }
    @keyframes ring { 0% { box-shadow: 0 0 0 0 rgba(52, 211, 153, .6); } 100% { box-shadow: 0 0 0 10px rgba(52, 211, 153, 0); } }

    /* ── Stats ── */
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); }
    .stat { padding: 44px 32px; border-right: 1px solid var(--line); }
    .stat:last-child { border-right: 0; }
    .stat .num { font-size: clamp(40px, 5vw, 64px); font-weight: 600; letter-spacing: -.05em; line-height: 1; font-variant-numeric: tabular-nums; }
    .stat .lbl { margin-top: 12px; font-family: var(--mono); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--dim); }

    /* ── Split (dev section) ── */
    .split { display: grid; grid-template-columns: 5fr 7fr; gap: 56px; align-items: start; }
    .checks { list-style: none; margin: 32px 0 0; padding: 0; display: grid; gap: 14px; }
    .checks li { display: grid; grid-template-columns: 20px 1fr; gap: 12px; color: var(--muted); font-size: 15px; }
    .checks li strong { color: var(--text); font-weight: 500; }
    .checks svg { width: 18px; height: 18px; margin-top: 3px; stroke: var(--ok); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

    .window {
        border: 1px solid var(--line-2);
        border-radius: 12px;
        background: var(--bg-2);
        overflow: hidden;
        box-shadow: 0 40px 100px -30px rgba(0, 0, 0, .9);
    }
    .window-bar { display: flex; align-items: center; gap: 4px; padding: 0 8px 0 14px; height: 44px; border-bottom: 1px solid var(--line); background: rgba(255, 255, 255, .015); }
    .window-bar .lights { display: flex; gap: 6px; margin-right: 12px; }
    .window-bar .lights span { width: 10px; height: 10px; border-radius: 50%; background: rgba(255, 255, 255, .12); }
    .tab { position: relative; height: 44px; padding: 0 12px; font-family: var(--mono); font-size: 12px; color: var(--dim); transition: color .2s; }
    .tab:hover { color: var(--muted); }
    .tab[aria-selected="true"] { color: var(--text); }
    .tab[aria-selected="true"]::after { content: ""; position: absolute; left: 10px; right: 10px; bottom: -1px; height: 1px; background: linear-gradient(90deg, var(--sky), var(--fuchsia)); }
    .window-bar .copy { margin-left: auto; }
    .panel { display: none; }
    .panel.active { display: block; animation: fadeIn .35s var(--ease); }
    pre { margin: 0; padding: 22px 24px; overflow-x: auto; font-family: var(--mono); font-size: 13px; line-height: 1.75; color: #d6d6de; tab-size: 4; }
    .tk-k { color: #c4b5fd; }
    .tk-s { color: #86efac; }
    .tk-c { color: rgba(244, 244, 246, .36); font-style: italic; }
    .tk-v { color: #93c5fd; }
    .tk-f { color: #f0abfc; }
    .tk-n { color: #fcd34d; }

    /* ── Deploy ── */
    .deploy { display: grid; grid-template-columns: 5fr 7fr; gap: 56px; align-items: start; }
    .steps { list-style: none; margin: 36px 0 0; padding: 0; counter-reset: step; }
    .steps li { position: relative; padding: 0 0 26px 52px; counter-increment: step; }
    .steps li::before {
        content: counter(step, decimal-leading-zero);
        position: absolute;
        left: 0;
        top: 0;
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border: 1px solid var(--line-2);
        border-radius: 8px;
        font-family: var(--mono);
        font-size: 12px;
        color: var(--muted);
        background: var(--bg);
    }
    .steps li::after { content: ""; position: absolute; left: 17px; top: 38px; bottom: 4px; width: 1px; background: var(--line); }
    .steps li:last-child::after { display: none; }
    .steps h3 { font-size: 16px; padding-top: 6px; }
    .steps p { margin-top: 6px; font-size: 14px; color: var(--muted); }
    .note {
        margin-top: 8px;
        padding: 14px 16px;
        border: 1px dashed var(--line-2);
        border-radius: 8px;
        font-size: 14px;
        color: var(--muted);
    }
    .note code { color: var(--text); font-size: 13px; }

    .term pre { min-height: 380px; }
    .term .ln { display: block; white-space: pre; }
    .term .ln-cmt { color: rgba(244, 244, 246, .36); }
    .term .ln-out { color: var(--ok); }
    .term .ps { color: var(--ok); user-select: none; }
    .caret { display: inline-block; width: 8px; height: 1.1em; vertical-align: -.2em; background: var(--text); animation: blink 1s steps(1) infinite; }

    /* ── vidual CTA ── */
    .cta { position: relative; overflow: hidden; text-align: center; }
    .cta .glow-a { left: 10%; top: auto; bottom: -320px; }
    .cta .glow-b { right: 5%; top: -280px; }
    .cta-inner { position: relative; padding: 120px 56px; }
    .cta h2 { font-size: clamp(38px, 6vw, 76px); letter-spacing: -.045em; }
    .cta .lead-text { margin: 24px auto 0; }
    .cta .hero-ctas { justify-content: center; animation: none; }
    .by { display: inline-flex; align-items: center; gap: 10px; padding: 6px 14px 6px 6px; border: 1px solid var(--line-2); border-radius: 999px; font-size: 13px; color: var(--muted); background: rgba(255, 255, 255, .03); margin-bottom: 28px; transition: border-color .2s, color .2s; }
    .by:hover { border-color: var(--line-3); color: var(--text); }
    .by b { font-family: var(--mono); font-weight: 500; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #0a0a0f; background: var(--text); border-radius: 999px; padding: 3px 9px; }
    .offer { display: grid; grid-template-columns: repeat(4, 1fr); margin-top: 72px; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; text-align: left; background: rgba(5, 5, 7, .6); }
    .offer div { padding: 22px 22px 24px; border-right: 1px solid var(--line); }
    .offer div:last-child { border-right: 0; }
    .offer strong { display: block; font-weight: 500; font-size: 15px; }
    .offer span { display: block; margin-top: 6px; font-size: 13px; color: var(--dim); }

    /* ── Footer ── */
    .foot { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 32px; }
    .foot h4 { margin: 0 0 14px; font-family: var(--mono); font-size: 11px; font-weight: 500; letter-spacing: .12em; text-transform: uppercase; color: var(--dim); }
    .foot ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
    .foot a { font-size: 14px; color: var(--muted); transition: color .2s; }
    .foot a:hover { color: var(--text); }
    .foot .tag { margin-top: 14px; font-size: 14px; color: var(--dim); max-width: 300px; }
    .legal { display: flex; flex-wrap: wrap; gap: 12px 24px; justify-content: space-between; padding: 22px 56px; font-family: var(--mono); font-size: 12px; color: var(--dim); }
    .legal a:hover { color: var(--text); }

    /* ── Scroll reveal ── */
    .js .reveal { opacity: 0; transform: translateY(18px); transition: opacity .8s var(--ease), transform .8s var(--ease); transition-delay: var(--d, 0ms); }
    .js .reveal.in { opacity: 1; transform: none; }

    /* ── Keyframes ── */
    @keyframes rise { from { transform: translateY(105%); } to { transform: none; } }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes shimmer { from { background-position: 0% 50%; } to { background-position: 100% 50%; } }
    @keyframes drift { from { transform: translate(0, 0) scale(1); } to { transform: translate(60px, 40px) scale(1.12); } }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
    @keyframes dotIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    @keyframes marquee { to { transform: translateX(-50%); } }
    @keyframes travel { 0% { stroke-dashoffset: 100; opacity: 0; } 15% { opacity: 1; } 85% { opacity: 1; } 100% { stroke-dashoffset: 0; opacity: 0; } }
    @keyframes rowIn { from { opacity: 0; transform: translateY(-100%); } to { opacity: 1; transform: none; } }
    @keyframes blink { 50% { opacity: 0; } }

    /* ── Responsive ── */
    @media (max-width: 1080px) {
        .flow { grid-template-columns: 1fr; gap: 20px; padding: 20px; }
        .sources { flex-direction: row; flex-wrap: wrap; gap: 8px; }
        .src { height: 32px; font-size: 12px; }
        .wires { display: none; }
        .split, .deploy, .head { grid-template-columns: 1fr; gap: 32px; }
        .feat-grid, .feat-grid.flip { grid-template-columns: 1fr; gap: 40px; padding: 80px 40px; }
        .feat-grid.flip .feat-copy { order: 0; }
        .head { align-items: start; }
        .stats { grid-template-columns: repeat(2, 1fr); }
        .stat:nth-child(2) { border-right: 0; }
        .stat:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
        .offer { grid-template-columns: repeat(2, 1fr); }
        .offer div:nth-child(2) { border-right: 0; }
        .offer div:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
        .foot { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 760px) {
        .nav .shell { gap: 12px; padding: 0 16px; }
        .nav-links { display: none; }
        .nav-right { margin-left: auto; }
        .pad, .pad-sm { padding: 64px 16px; }
        .hero-inner { padding: 72px 16px 48px; }
        .cta-inner { padding: 80px 16px; }
        .marquee-label { padding-left: 16px; padding-right: 16px; }
        .legal { padding: 22px 16px; }
        .stat { padding: 32px 20px; }
        .offer { grid-template-columns: 1fr; }
        .offer div { border-right: 0; border-bottom: 1px solid var(--line); }
        .offer div:last-child { border-bottom: 0; }
        .foot { grid-template-columns: 1fr; }
        .feat-grid, .feat-grid.flip { padding: 64px 16px; }
        .viz { padding: 16px; min-height: 0; }
        .kpis { grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
        .kpis .vz-card { padding: 10px; }
        .kpis b { font-size: 18px; }
        .bar { grid-template-columns: 1fr 70px; }
        .bar-track { grid-column: 1 / -1; grid-row: 2; }
        .vz-row { grid-template-columns: 32px 1fr; }
        .au-phone { grid-column: 2; justify-self: start; padding-left: 0; }
        .au-group { align-items: flex-start; flex-direction: column; gap: 8px; }
        .clients { grid-template-columns: 1fr; }
        .op .cl-rows { grid-template-columns: 1fr; }
        .row { grid-template-columns: 28px 1fr auto; }
        .term pre { min-height: 0; font-size: 12px; }
        pre { padding: 18px 16px; font-size: 12px; }
        .shell { border-left: 0; border-right: 0; }
        .sec::before, .sec::after { display: none; }
    }
    @media (max-width: 420px) {
        .nav .btn-sm { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; scroll-behavior: auto !important; }
        .marquee-track { animation: none; }
        .statement .w { opacity: 1; }
    }
</style>
