@php
    $github = config('lodgely.brand.github_url');
    $vidual = config('lodgely.brand.vidual_url');
    $version = config('lodgely.version');

    $sources = [
        ['Meta Lead Ads', '#60a5fa'],
        ['Google Sheets', '#34d399'],
        ['Webhook', '#e879f9'],
        ['IMAP email', '#fbbf24'],
        ['OpenFlow', '#a78bfa'],
        ['CSV upload', '#fb923c'],
        ['Manual entry', '#94a3b8'],
    ];

    // Server-rendered starting state of the animated inbox; scripts.blade.php
    // keeps prepending rows from its own pool once the page is live.
    $rows = [
        ['Lena Hoffmann', 'Meta Lead Ads', '#60a5fa', 'Spring launch', 'New', 'new', '2m'],
        ['Marco Rossi', 'Webhook', '#e879f9', '/pricing form', 'High priority', 'hot', '6m'],
        ['Sophie Bauer', 'Google Sheets', '#34d399', 'Trade fair list', 'Qualified', 'ok', '14m'],
        ['Lena Hoffmann', 'IMAP email', '#fbbf24', 'Reply to newsletter', 'Duplicate', 'dup', '21m'],
        ['Tom Becker', 'OpenFlow', '#a78bfa', 'Quote request', 'New', 'new', '38m'],
    ];

    $marquee = ['Meta Lead Ads', 'Google Sheets', 'Webhooks', 'IMAP', 'CSV', 'OpenFlow', 'Meta Marketing API', 'Google Ads API', 'Ollama', 'OpenAI-compatible', 'PostgreSQL', 'Docker', 'SMTP', 'NDJSON'];

    $statement = 'lodgely is the layer *before* your CRM. No deals, no pipelines, no forecasts — just every incoming lead, *normalized,* *deduplicated* and in front of the right person within minutes.';

    $stats = [
        [7, 'lead sources'],
        [2, 'ad platforms'],
        [0, 'telemetry calls'],
        [1, 'docker compose up'],
    ];

    // [kind, text] — "cmd" lines are what the copy button puts on the clipboard.
    $terminal = [
        ['cmt', '# 1 · clone and configure'],
        ['cmd', 'git clone https://github.com/vidual-labs/lodgely.git && cd lodgely'],
        ['cmd', 'cp .env.example .env   # set APP_URL + DB_PASSWORD first'],
        ['cmt', '# 2 · postgres, app, queue worker, scheduler, caddy'],
        ['cmd', 'docker compose up -d --build'],
        ['cmd', 'docker compose exec app chown -R www-data:www-data storage bootstrap/cache'],
        ['cmt', '# 3 · bootstrap'],
        ['cmd', 'docker compose exec app composer install'],
        ['cmd', 'docker compose exec app php artisan key:generate'],
        ['cmd', 'docker compose exec app php artisan migrate --seed'],
        ['cmd', 'docker compose exec app npm ci && docker compose exec app npm run build'],
        ['out', '✓ lodgely is up — open your APP_URL and sign in'],
    ];
    $terminalCopy = collect($terminal)->where(0, 'cmd')->pluck(1)->implode("\n");
@endphp
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lodgely — the open-source lead intake hub</title>
    <meta name="description" content="Self-hosted lead intake for technical marketers: Meta Lead Ads, Google Sheets, webhooks, IMAP, CSV and OpenFlow in one deduplicated, GDPR-aware inbox — with ad-spend reporting and client portals.">
    <meta name="theme-color" content="#050507">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <script>document.documentElement.classList.replace('no-js', 'js');</script>
    @include('landing.partials.styles')
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

{{-- ───────────── Nav ───────────── --}}
<header class="nav">
    <div class="shell">
        <a href="#top" class="brand" aria-label="lodgely home">
            <svg viewBox="8 8 44 44" aria-hidden="true">
                <defs>
                    <linearGradient id="lg-ic" x1="14" y1="14" x2="46" y2="46" gradientUnits="userSpaceOnUse">
                        <stop offset="0" stop-color="#60a5fa"/>
                        <stop offset="1" stop-color="#818cf8"/>
                    </linearGradient>
                </defs>
                @php $i = 0; @endphp
                @foreach ([14, 22, 30, 38, 46] as $col => $x)
                    @for ($row = 0; $row <= $col; $row++)
                        <circle cx="{{ $x }}" cy="{{ 46 - $row * 8 }}" r="2.6" fill="url(#lg-ic)"
                                opacity="{{ [1, .9, .78, .62, .44][$row] }}" style="--i: {{ $i++ }}"/>
                    @endfor
                @endforeach
            </svg>
            lodgely
        </a>
        <nav class="nav-links" aria-label="Sections">
            <a href="#features">Features</a>
            <a href="#developers">Developers</a>
            <a href="#deploy">Deploy</a>
            <a href="{{ $github }}" target="_blank" rel="noopener">GitHub</a>
        </nav>
        <div class="nav-right">
            <a href="{{ route('login') }}" class="signin">Sign in</a>
            <a href="{{ $vidual }}" target="_blank" rel="noopener" class="btn btn-solid btn-sm">
                Talk to vidual
                <svg class="arrow" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
            </a>
        </div>
    </div>
</header>

<main id="main">
<div class="shell" id="top">

    {{-- ───────────── Hero ───────────── --}}
    <section class="sec hero">
        <div class="hero-bg" aria-hidden="true">
            <div class="dots"></div>
            <div class="glow glow-a"></div>
            <div class="glow glow-b"></div>
            <div class="glow glow-c"></div>
        </div>
        <div class="hero-inner">
            <span class="eyebrow"><span class="dot"></span>Open source · Self-hosted · v{{ $version }}</span>
            <h1 aria-label="Every lead. One inbox. Your server.">
                @php $w = 0; @endphp
                @foreach ([['Every', 'lead.'], ['One', 'inbox.'], ['Your', 'server.']] as $n => $line)
                    <span class="line" aria-hidden="true">@foreach ($line as $word)<span class="word {{ $n === 2 ? 'grad' : '' }}" style="--i: {{ $w++ }}">{{ $word }}</span> @endforeach</span>
                @endforeach
            </h1>
            <p class="lead-text">
                lodgely pulls leads from Meta Lead Ads, Google Sheets, webhooks, IMAP, CSV and OpenFlow
                into one deduplicated, GDPR-aware inbox — with ad-spend reporting and client portals built in.
                Deliberately not a CRM.
            </p>
            <div class="hero-ctas">
                <a href="#deploy" class="btn btn-solid">
                    Deploy lodgely
                    <svg class="arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                </a>
                <a href="{{ $github }}" target="_blank" rel="noopener" class="btn btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                    Star on GitHub
                </a>
            </div>
            <div class="cmd">
                <span class="prompt" aria-hidden="true">$</span>
                <code>git clone {{ $github }}.git</code>
                <button type="button" class="copy" data-copy="git clone {{ $github }}.git" aria-label="Copy clone command">
                    <svg class="idle" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 009.5 2h-6A1.5 1.5 0 002 3.5v6A1.5 1.5 0 003.5 11H5"/></svg>
                    <svg class="ok" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8.5l3 3 7-7"/></svg>
                </button>
            </div>

            {{-- Sources → inbox. The wires are drawn by scripts.blade.php from the
                 live chip positions, so the diagram survives any font / layout. --}}
            <div class="flow" id="flow" aria-label="Leads flowing from seven sources into the lodgely inbox" role="img">
                <div class="sources">
                    @foreach ($sources as [$label, $color])
                        <div class="src" data-src="{{ $label }}" style="--c: {{ $color }}"><i></i>{{ $label }}</div>
                    @endforeach
                </div>
                <svg class="wires" id="wires" aria-hidden="true"></svg>
                <div class="inbox">
                    <div class="inbox-head">
                        <span class="lights" aria-hidden="true"><span></span><span></span><span></span></span>
                        <span class="title">Inbox</span>
                        <span class="count"><b id="inbox-count">128</b> new today</span>
                    </div>
                    <ul class="rows" id="rows">
                        @foreach ($rows as [$name, $src, $color, $campaign, $pill, $pillKind, $ago])
                            <li class="row" style="--c: {{ $color }}">
                                <span class="avatar">{{ collect(explode(' ', $name))->map(fn ($p) => $p[0])->implode('') }}</span>
                                <span class="who">
                                    <span class="name">{{ $name }}</span>
                                    <span class="meta">{{ $src }} · {{ $campaign }} · {{ $ago }}</span>
                                </span>
                                <span class="pill pill-{{ $pillKind }}">{{ $pill }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ───────────── Marquee ───────────── --}}
    <section class="sec" aria-label="Integrations">
        <div class="marquee-label"><span class="eyebrow">Plugs into the stack you already run</span></div>
        <div class="marquee">
            <div class="marquee-track">
                @foreach ([0, 1] as $copy)
                    @foreach ($marquee as $item)
                        <span class="marquee-item" @if ($copy) aria-hidden="true" @endif>{{ $item }}</span>
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>

    {{-- ───────────── Statement ───────────── --}}
    <section class="sec pad statement" id="statement">
        <span class="eyebrow reveal" style="margin-bottom: 28px; display: inline-flex;">Philosophy</span>
        <p aria-label="{{ str_replace('*', '', $statement) }}">
            @foreach (explode(' ', $statement) as $word)
                <span class="w {{ str_contains($word, '*') ? 'hl' : '' }}" aria-hidden="true">{{ str_replace('*', '', $word) }}</span>
            @endforeach
        </p>
    </section>

    {{-- ───────────── Features ───────────── --}}
    @include('landing.partials.features')

    {{-- ───────────── Stats ───────────── --}}
    <section class="sec stats" aria-label="lodgely in numbers">
        @foreach ($stats as $n => [$value, $label])
            <div class="stat reveal" style="--d: {{ $n * 90 }}ms">
                <div class="num" data-count="{{ $value }}">{{ $value }}</div>
                <div class="lbl">{{ $label }}</div>
            </div>
        @endforeach
    </section>

    {{-- ───────────── Developers ───────────── --}}
    <section class="sec pad" id="developers">
        <div class="split">
            <div>
                <span class="eyebrow reveal">For the technical marketer</span>
                <h2 class="reveal" style="--d: 80ms; margin-top: 20px;">Boring stack.<br><span class="grad">No black boxes.</span></h2>
                <p class="lead-text reveal" style="--d: 160ms; margin-top: 24px;">
                    No SaaS seat pricing, no black-box sync. Read the code, point your forms at it,
                    and extend it the same way the built-in sources are written.
                </p>
                <ul class="checks">
                    @foreach ([
                        ['Laravel 12 · Livewire 3 · PostgreSQL.', 'Server-rendered, no SPA — hosts anywhere Docker runs.'],
                        ['Every source is an adapter.', 'Implement one interface, register it in one line.'],
                        ['Idempotent by design.', 'Stable external ids mean re-fetches never duplicate.'],
                        ['Webhooks for everything else.', 'Form builders, n8n, Make, Zapier — anything that can POST JSON.'],
                        ['Config in .env, secrets in the UI.', 'Credentials are encrypted at rest; env vars stay a fallback.'],
                    ] as $n => [$strong, $rest])
                        <li class="reveal" style="--d: {{ 200 + $n * 70 }}ms">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7"/></svg>
                            <span><strong>{{ $strong }}</strong> {{ $rest }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="window reveal" style="--d: 120ms" data-tabs>
                <div class="window-bar" role="tablist" aria-label="Code examples">
                    <span class="lights" aria-hidden="true"><span></span><span></span><span></span></span>
                    <button type="button" class="tab" role="tab" aria-selected="true" aria-controls="tab-webhook" id="t-webhook">webhook.sh</button>
                    <button type="button" class="tab" role="tab" aria-selected="false" aria-controls="tab-adapter" id="t-adapter" tabindex="-1">TypeformSource.php</button>
                    <button type="button" class="tab" role="tab" aria-selected="false" aria-controls="tab-env" id="t-env" tabindex="-1">.env</button>
                </div>
<div class="panel active" id="tab-webhook" role="tabpanel" aria-labelledby="t-webhook"><pre><code><span class="tk-c"># Create an endpoint under /webhooks, then POST from anywhere:</span>
<span class="tk-f">curl</span> -X POST https://leads.example.com/api/webhooks/<span class="tk-v">$TOKEN</span> \
  -H <span class="tk-s">"Content-Type: application/json"</span> \
  -d <span class="tk-s">'{
    "full_name":     "Jane Doe",
    "email":         "jane@example.com",
    "phone":         "+49 30 1234567",
    "campaign_name": "Spring launch",
    "message":       "Can we book a demo next week?"
  }'</span>

<span class="tk-c"># → normalized, deduplicated, retention date set, audit-logged.</span></code></pre></div>
<div class="panel" id="tab-adapter" role="tabpanel" aria-labelledby="t-adapter"><pre><code><span class="tk-k">final class</span> <span class="tk-f">TypeformSource</span> <span class="tk-k">implements</span> <span class="tk-f">LeadSource</span>
{
    <span class="tk-k">public function</span> <span class="tk-f">key</span>(): <span class="tk-k">string</span>   { <span class="tk-k">return</span> <span class="tk-s">'typeform'</span>; }
    <span class="tk-k">public function</span> <span class="tk-f">label</span>(): <span class="tk-k">string</span> { <span class="tk-k">return</span> <span class="tk-s">'Typeform'</span>; }

    <span class="tk-k">public function</span> <span class="tk-f">pull</span>(<span class="tk-f">Import</span> <span class="tk-v">$import</span>): <span class="tk-k">iterable</span>
    {
        <span class="tk-k">foreach</span> (<span class="tk-v">$this</span>-&gt;<span class="tk-f">responses</span>() <span class="tk-k">as</span> <span class="tk-v">$row</span>) {
            <span class="tk-k">yield new</span> <span class="tk-f">IncomingLead</span>(
                source:     <span class="tk-s">'typeform'</span>,
                fullName:   <span class="tk-v">$row</span>[<span class="tk-s">'name'</span>],
                email:      <span class="tk-v">$row</span>[<span class="tk-s">'email'</span>],
                externalId: <span class="tk-v">$row</span>[<span class="tk-s">'response_id'</span>], <span class="tk-c">// idempotent</span>
            );
        }
    }
}

<span class="tk-c">// AppServiceProvider::IMPORTERS — one line, done.</span>
<span class="tk-s">'typeform'</span> =&gt; <span class="tk-f">TypeformSource</span>::<span class="tk-k">class</span>,</code></pre></div>
<div class="panel" id="tab-env" role="tabpanel" aria-labelledby="t-env"><pre><code><span class="tk-v">APP_URL</span>=<span class="tk-s">https://leads.example.com</span>

<span class="tk-c"># Compliance defaults</span>
<span class="tk-v">LODGELY_DEFAULT_RETENTION_DAYS</span>=<span class="tk-n">365</span>
<span class="tk-v">LODGELY_BACKUP_PASSPHRASE</span>=<span class="tk-s">change-me</span>

<span class="tk-c"># Intake + reporting</span>
<span class="tk-v">LODGELY_EMAIL_IMPORT_DRIVER</span>=<span class="tk-s">imap</span>
<span class="tk-v">LODGELY_AD_METRICS_SOURCES</span>=<span class="tk-s">meta,google</span>

<span class="tk-c"># Opt-in only — nothing leaves your server by default</span>
<span class="tk-v">LODGELY_AI_ENABLED</span>=<span class="tk-k">false</span>

<span class="tk-c"># This page. false = "/" goes straight to the login.</span>
<span class="tk-v">LODGELY_LANDING_ENABLED</span>=<span class="tk-k">true</span></code></pre></div>
            </div>
        </div>
    </section>

    {{-- ───────────── Deploy ───────────── --}}
    <section class="sec pad" id="deploy">
        <div class="deploy">
            <div>
                <span class="eyebrow reveal">Deploy</span>
                <h2 class="reveal" style="--d: 80ms; margin-top: 20px;">Up in minutes.<br>On your own box.</h2>
                <ol class="steps">
                    <li class="reveal" style="--d: 140ms">
                        <h3>Clone &amp; configure</h3>
                        <p>Copy <code>.env.example</code> and set <code>APP_URL</code> and <code>DB_PASSWORD</code> before the first boot — Postgres bakes the password into its volume.</p>
                    </li>
                    <li class="reveal" style="--d: 210ms">
                        <h3>Boot the stack</h3>
                        <p>One <code>docker compose up</code> starts Postgres 16, the app, a queue worker, the scheduler for recurring pulls and Caddy.</p>
                    </li>
                    <li class="reveal" style="--d: 280ms">
                        <h3>Bootstrap &amp; sign in</h3>
                        <p>Migrate, seed, build assets — then sign in and swap the seeded demo accounts for real ones before going live.</p>
                    </li>
                </ol>
                <p class="note reveal" style="--d: 340ms">
                    Rather have the login as your front door? Set <code>LODGELY_LANDING_ENABLED=false</code> in <code>.env</code>
                    and <code>/</code> goes straight to the inbox.
                    <a href="{{ $github }}#quick-start-docker" target="_blank" rel="noopener" style="color: var(--text); text-decoration: underline; text-underline-offset: 3px;">Full install guide →</a>
                </p>
            </div>

            <div class="window term reveal" style="--d: 120ms">
                <div class="window-bar">
                    <span class="lights" aria-hidden="true"><span></span><span></span><span></span></span>
                    <span class="mono" style="font-size: 12px; color: var(--dim);">~/lodgely — zsh</span>
                    <button type="button" class="copy" data-copy="{{ $terminalCopy }}" aria-label="Copy install commands">
                        <svg class="idle" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 009.5 2h-6A1.5 1.5 0 002 3.5v6A1.5 1.5 0 003.5 11H5"/></svg>
                        <svg class="ok" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8.5l3 3 7-7"/></svg>
                    </button>
                </div>
<pre id="terminal"><code>@foreach ($terminal as [$kind, $text])<span class="ln ln-{{ $kind }}" data-kind="{{ $kind }}">@if ($kind === 'cmd')<span class="ps">$ </span>@endif<span class="tx">{{ $text }}</span></span>@endforeach</code></pre>
            </div>
        </div>
    </section>

    {{-- ───────────── vidual CTA ───────────── --}}
    <section class="sec cta" id="vidual">
        <div class="hero-bg" aria-hidden="true">
            <div class="dots"></div>
            <div class="glow glow-a"></div>
            <div class="glow glow-b"></div>
        </div>
        <div class="cta-inner">
            <a href="{{ $vidual }}" target="_blank" rel="noopener" class="by reveal"><b>vidual</b> lodgely is built &amp; maintained by vidual</a>
            <h2 class="reveal" style="--d: 80ms">Get the most<br><span class="grad">out of lodgely.</span></h2>
            <p class="lead-text reveal" style="--d: 160ms">
                Open source means you can run it yourself. When you’d rather not, the team behind lodgely
                sets it up, connects your ad accounts and lead sources, builds custom adapters and keeps it
                running — so your people work leads, not infrastructure.
            </p>
            <div class="hero-ctas reveal" style="--d: 240ms">
                <a href="{{ $vidual }}" target="_blank" rel="noopener" class="btn btn-solid">
                    Talk to vidual
                    <svg class="arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                </a>
                <a href="{{ $github }}" target="_blank" rel="noopener" class="btn btn-ghost">Browse the source</a>
            </div>
            <div class="offer reveal" style="--d: 320ms">
                <div><strong>Setup &amp; hosting</strong><span>Installed, secured and backed up on your infrastructure or ours.</span></div>
                <div><strong>Ad platform wiring</strong><span>Meta and Google Ads connected per client, reporting from day one.</span></div>
                <div><strong>Custom lead sources</strong><span>New adapters for the form tools and portals you actually use.</span></div>
                <div><strong>Onboarding</strong><span>Operators and clients trained on the inbox and their reports.</span></div>
            </div>
        </div>
    </section>

    {{-- ───────────── Footer ───────────── --}}
    <footer class="sec">
        <div class="pad-sm foot">
            <div>
                <a href="#top" class="brand">lodgely</a>
                <p class="tag">{{ config('lodgely.brand.tagline') }} The open-source lead intake hub for agencies and in-house marketing teams.</p>
            </div>
            <div>
                <h4>Product</h4>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#developers">Developers</a></li>
                    <li><a href="#deploy">Deploy</a></li>
                    <li><a href="{{ route('login') }}">Sign in</a></li>
                </ul>
            </div>
            <div>
                <h4>Open source</h4>
                <ul>
                    <li><a href="{{ $github }}" target="_blank" rel="noopener">GitHub</a></li>
                    <li><a href="{{ $github }}/blob/main/CHANGELOG.md" target="_blank" rel="noopener">Changelog</a></li>
                    <li><a href="{{ $github }}/blob/main/ROADMAP.md" target="_blank" rel="noopener">Roadmap</a></li>
                    <li><a href="{{ $github }}/blob/main/LICENSE" target="_blank" rel="noopener">License (GPL-3.0)</a></li>
                </ul>
            </div>
            <div>
                <h4>Get help</h4>
                <ul>
                    <li><a href="{{ $vidual }}" target="_blank" rel="noopener">vidual.org</a></li>
                    <li><a href="{{ $vidual }}" target="_blank" rel="noopener">Setup &amp; integrations</a></li>
                    <li><a href="{{ $github }}/issues" target="_blank" rel="noopener">Issues</a></li>
                </ul>
            </div>
        </div>
        <div class="sec legal">
            <span>© {{ date('Y') }} <a href="{{ $vidual }}" target="_blank" rel="noopener">vidual</a> · GPL-3.0</span>
            <span>lodgely v{{ $version }}</span>
        </div>
    </footer>
</div>
</main>

@include('landing.partials.scripts')
</body>
</html>
