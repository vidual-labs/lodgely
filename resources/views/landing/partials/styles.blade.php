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
    .brand svg { width: 26px; height: 26px; }
    .brand svg circle { animation: dotIn .6s var(--ease) both; animation-delay: calc(var(--i) * 45ms); }
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

    /* ── Feature grid ── */
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--line); }
    .cell {
        position: relative;
        padding: 36px 32px 40px;
        border-right: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
        overflow: hidden;
        isolation: isolate;
    }
    .cell:nth-child(3n) { border-right: 0; }
    .cell:nth-last-child(-n+3) { border-bottom: 0; }
    .cell::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: -1;
        background: radial-gradient(360px circle at var(--mx, 50%) var(--my, 50%), rgba(129, 140, 248, .13), transparent 45%);
        opacity: 0;
        transition: opacity .35s;
    }
    .cell:hover::before { opacity: 1; }
    .cell .idx { position: absolute; top: 20px; right: 22px; font-family: var(--mono); font-size: 11px; color: var(--dim); }
    .cell .ico {
        display: grid;
        place-items: center;
        width: 42px;
        height: 42px;
        margin-bottom: 28px;
        border: 1px solid var(--line-2);
        border-radius: 10px;
        background: linear-gradient(180deg, rgba(255, 255, 255, .06), rgba(255, 255, 255, .01));
        color: var(--text);
        transition: transform .4s var(--ease), border-color .3s, color .3s;
    }
    .cell:hover .ico { transform: translateY(-3px); border-color: rgba(129, 140, 248, .5); color: var(--indigo); }
    .cell .ico svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
    .cell p { margin-top: 10px; font-size: 15px; color: var(--muted); }
    .cell .tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 18px; }
    .cell .tags span { font-family: var(--mono); font-size: 11px; color: var(--dim); padding: 2px 8px; border: 1px solid var(--line); border-radius: 4px; }

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
        .head { align-items: start; }
        .grid { grid-template-columns: repeat(2, 1fr); }
        .cell:nth-child(3n) { border-right: 1px solid var(--line); }
        .cell:nth-child(2n) { border-right: 0; }
        .cell:nth-last-child(-n+3) { border-bottom: 1px solid var(--line); }
        .cell:last-child { border-bottom: 0; }
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
        .grid { grid-template-columns: 1fr; }
        .cell, .cell:nth-child(3n) { border-right: 0; border-bottom: 1px solid var(--line); }
        .cell:last-child { border-bottom: 0; }
        .cell { padding: 32px 20px; }
        .stat { padding: 32px 20px; }
        .offer { grid-template-columns: 1fr; }
        .offer div { border-right: 0; border-bottom: 1px solid var(--line); }
        .offer div:last-child { border-bottom: 0; }
        .foot { grid-template-columns: 1fr; }
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
