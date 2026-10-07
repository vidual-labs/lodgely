{{--
    The four highlighted features, alternating copy / animated graphic.
    Every .viz renders its resting (final) state server-side; the animations
    only run once scripts.blade.php adds .play as the graphic scrolls into
    view, and the looping ones start and end on that same resting frame so
    nothing flashes. Reduced motion or no JS = the static resting frame.
--}}
<section class="sec pad" id="features" style="padding-bottom: 72px;">
    <div class="head" style="margin-bottom: 0;">
        <div>
            <span class="eyebrow reveal">Features</span>
            <h2 class="reveal" style="--d: 80ms; margin-top: 20px;">Everything between<br>the ad click and the call.</h2>
        </div>
        <p class="lead-text reveal" style="--d: 160ms">
            Four things worth switching for. The rest — duplicate detection, saved views,
            bulk edits, CSV and NDJSON exports, EN/DE — is one click away once you’re in.
        </p>
    </div>
</section>

{{-- 01 · Automation (auto status, outreach toggles, AI qualification) ─ --}}
<section class="sec feat" style="--a: #a78bfa;">
    <div class="feat-grid">
        <div class="feat-copy">
            <span class="eyebrow reveal"><span class="n">01</span> Automation</span>
            <h2 class="reveal" style="--d: 80ms">Leads that sort<br>themselves.</h2>
            <p class="lead-text reveal" style="--d: 160ms">
                lodgely takes the clicks nobody wants to make: opening a lead marks it Reviewed,
                the first call or email moves it to Pending. Click a phone number and the Called
                toggle lights up as a reminder to confirm. Switch on AI qualification and each lead
                gets a priority recommendation — built from a pseudonymized copy, delivered as a
                draft an operator approves.
            </p>
            <ul class="feat-list reveal" style="--d: 240ms">
                <li>Forward-only status steps, audited like manual edits</li>
                <li>Qualified / Called / Mailed toggles with one-click <code>tel:</code> and <code>mailto:</code></li>
                <li>AI via any OpenAI-compatible API or a local Ollama — off by default</li>
            </ul>
        </div>

        <div class="viz viz-auto reveal" style="--d: 120ms" aria-hidden="true">
            <div class="vz-card au-lead">
                <div class="vz-row">
                    <span class="avatar" style="--c: #e879f9">MR</span>
                    <span class="who"><b>Marco Rossi</b><small>Webhook · /pricing form · 09:12</small></span>
                    <span class="au-phone">+49 170 555 0189</span>
                </div>
                <div class="au-group">
                    <small>Status</small>
                    <span class="au-pills">
                        <span class="au-pill p-new">New</span>
                        <span class="au-pill p-rev">Reviewed</span>
                        <span class="au-pill p-pen">Pending</span>
                    </span>
                </div>
                <div class="au-group">
                    <small>Outreach</small>
                    <span class="au-pills">
                        <span class="au-tog">Qualified</span>
                        <span class="au-tog t-called">✓ Called</span>
                        <span class="au-tog">Mailed</span>
                    </span>
                </div>
            </div>

            <div class="vz-card au-ai">
                <div class="vz-head">
                    <span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/></svg>
                        AI qualification
                    </span>
                    <span class="swap">
                        <span class="pill au-draft">draft</span>
                        <span class="pill pill-ok au-approved">approved by Pat</span>
                    </span>
                </div>
                <span class="au-wait">Qualification queued<i>.</i><i>.</i><i>.</i></span>
                <code class="au-in">Lead #1187 · m***@acme-studio.de · +49 *** 89</code>
                <div class="au-out">
                    <span class="au-prio">Recommended priority <b>high</b></span>
                    <p>Asks for pricing and a demo date via the /pricing form — clear buying intent.</p>
                    <p class="au-next">→ Call back today, offer two demo slots.</p>
                </div>
            </div>

            <div class="swap au-caps">
                <span class="au-cap c0">New lead arrives via webhook</span>
                <span class="au-cap c1">Opened by Pat → <b>Reviewed</b>, automatically</span>
                <span class="au-cap c2">Phone number clicked → <b>Called</b> lights up as a reminder</span>
                <span class="au-cap c3">Call confirmed → <b>Pending</b>, automatically</span>
                <span class="au-cap c4">AI recommends <b>high</b> → operator approves the draft</span>
            </div>
        </div>
    </div>
</section>

{{-- 02 · Reporting ─────────────────────────────────────────────────── --}}
<section class="sec feat" style="--a: #60a5fa;">
    <div class="feat-grid flip">
        <div class="feat-copy">
            <span class="eyebrow reveal"><span class="n">02</span> Reporting</span>
            <h2 class="reveal" style="--d: 80ms">Ad spend next to<br>the leads it bought.</h2>
            <p class="lead-text reveal" style="--d: 160ms">
                Spend from the Meta Marketing and Google Ads APIs is pulled in daily and rolled up
                per campaign, so cost per lead is a number you look up, not a spreadsheet you rebuild
                every Monday. Top ads, keywords and audience segments included.
            </p>
            <ul class="feat-list reveal" style="--d: 240ms">
                <li>KPI cards, trend charts, campaign breakdown</li>
                <li>One connector per client, or one shared account</li>
                <li>Scheduled report emails over your own SMTP</li>
            </ul>
        </div>

        <div class="viz viz-report reveal" style="--d: 120ms" aria-hidden="true">
            <div class="kpis">
                <div class="vz-card">
                    <small>Ad spend</small>
                    <b data-count="4820" data-prefix="€">€4,820</b>
                    <em class="up">▲ 12%</em>
                </div>
                <div class="vz-card">
                    <small>Leads</small>
                    <b data-count="312">312</b>
                    <em class="up">▲ 18%</em>
                </div>
                <div class="vz-card">
                    <small>Cost per lead</small>
                    <b data-count="15.45" data-decimals="2" data-prefix="€">€15.45</b>
                    <em class="up">▼ 5%</em>
                </div>
            </div>

            <div class="vz-card chart">
                <div class="chart-head">
                    <span>Leads · last 30 days</span>
                    <span class="legend"><i style="--c: #60a5fa"></i>Meta <i style="--c: #e879f9"></i>Google Ads</span>
                </div>
                <svg viewBox="0 0 400 120" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="vz-area" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#60a5fa" stop-opacity=".35"/>
                            <stop offset="1" stop-color="#60a5fa" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path class="gl" d="M0 30H400M0 60H400M0 90H400"/>
                    <path class="area" d="M0 96 L40 88 L80 92 L120 74 L160 78 L200 60 L240 64 L280 46 L320 50 L360 32 L400 22 L400 120 L0 120Z" fill="url(#vz-area)"/>
                    <path class="line l-meta" d="M0 96 L40 88 L80 92 L120 74 L160 78 L200 60 L240 64 L280 46 L320 50 L360 32 L400 22"/>
                    <path class="line l-google" d="M0 104 L40 100 L80 96 L120 98 L160 88 L200 86 L240 78 L280 80 L320 70 L360 66 L400 58"/>
                </svg>
            </div>

            <div class="vz-card bars">
                @foreach ([
                    ['Spring launch', 'Meta', 142, 100, '€12.80', '#60a5fa'],
                    ['Search · brand', 'Google', 96, 68, '€14.10', '#e879f9'],
                    ['Retargeting', 'Meta', 74, 52, '€22.28', '#60a5fa'],
                ] as $n => [$campaign, $platform, $leads, $width, $cpl, $color])
                    <div class="bar" style="--w: {{ $width }}%; --c: {{ $color }}; --i: {{ $n }}">
                        <span class="bar-label">{{ $campaign }} <small>{{ $platform }}</small></span>
                        <span class="bar-track"><span class="bar-fill"></span></span>
                        <span class="bar-val">{{ $leads }} <small>· {{ $cpl }} CPL</small></span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- 03 · Client portals ────────────────────────────────────────────── --}}
<section class="sec feat" style="--a: #e879f9;">
    <div class="feat-grid">
        <div class="feat-copy">
            <span class="eyebrow reveal"><span class="n">03</span> Client portals</span>
            <h2 class="reveal" style="--d: 80ms">One install.<br>A lane per client.</h2>
            <p class="lead-text reveal" style="--d: 160ms">
                Operators see every lead. Each client signs in to a view scoped to their own —
                they set status and priority, add notes, export CSV and read their reports
                without filing a ticket with the agency.
            </p>
            <ul class="feat-list reveal" style="--d: 240ms">
                <li>Scoping enforced server-side on every query</li>
                <li>Destructive and import actions stay operator-only</li>
                <li>Wording presets for B2B, jobs, B2C and inquiries</li>
            </ul>
        </div>

        <div class="viz viz-clients reveal" style="--d: 120ms" aria-hidden="true">
            @php
                $clientLeads = [
                    ['Lena Hoffmann', 'n'], ['Marco Rossi', 'a'], ['Sophie Bauer', 'n'],
                    ['Tom Becker', 'b'], ['Aylin Demir', 'a'], ['Jonas Klein', 'n'],
                ];
                $clientNames = ['n' => 'Northwind', 'a' => 'Acme', 'b' => 'Brightside'];
            @endphp
            <div class="vz-card op">
                <div class="vz-head"><span>Operator · all clients</span><span class="mono">{{ count($clientLeads) }} leads</span></div>
                <ul class="cl-rows">
                    @foreach ($clientLeads as [$name, $client])
                        <li class="c-{{ $client }}"><i></i>{{ $name }}<span>{{ $clientNames[$client] }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="clients">
                @foreach (['n' => 'Northwind Studio', 'a' => 'Acme Wellness'] as $key => $label)
                    <div class="vz-card cl cl-{{ $key }}">
                        <div class="vz-head">
                            <span>
                                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 015 0v2"/></svg>
                                {{ $label }}
                            </span>
                            <span class="mono">client</span>
                        </div>
                        <ul class="cl-rows">
                            @foreach ($clientLeads as [$name, $client])
                                @if ($client === $key)
                                    <li class="c-{{ $client }}"><i></i>{{ $name }}</li>
                                @endif
                            @endforeach
                        </ul>
                        <span class="cl-export">Export CSV ↓</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- 04 · Privacy & self-hosting ────────────────────────────────────── --}}
<section class="sec feat" style="--a: #34d399;">
    <div class="feat-grid flip">
        <div class="feat-copy">
            <span class="eyebrow reveal"><span class="n">04</span> GDPR &amp; self-hosted</span>
            <h2 class="reveal" style="--d: 80ms">Compliant because<br>it’s yours.</h2>
            <p class="lead-text reveal" style="--d: 160ms">
                Every lead carries a retention date and an audit trail of who touched it; a purge
                command clears what has expired. Secrets are encrypted at rest, backups can be too,
                and nothing phones home — every outbound integration is opt-in.
            </p>
            <ul class="feat-list reveal" style="--d: 240ms">
                <li>Retention dates honored on every intake path</li>
                <li>Audit log for status, outreach, notes and exports</li>
                <li>GPL-3.0, Docker-first, no telemetry</li>
            </ul>
        </div>

        <div class="viz viz-privacy reveal" style="--d: 120ms" aria-hidden="true">
            <div class="vz-card ret">
                <div class="vz-head"><span>Lead #1042 · Lena Hoffmann</span><span class="pill pill-ok">retained</span></div>
                <div class="ret-row">
                    <span>Retained until</span>
                    <b class="mono">{{ now()->addYear()->toDateString() }}</b>
                </div>
                <span class="ret-track"><span class="ret-fill"></span></span>
                <small class="mono">then removed by <span style="color: var(--text)">lodgely:leads:purge</span></small>
            </div>

            <div class="vz-card audit">
                <div class="vz-head"><span>Audit trail</span><span class="mono">append-only</span></div>
                <div class="ticker">
                    <ul>
                        @foreach ([0, 1] as $copy)
                            @foreach ([
                                ['09:12', 'Lead created', 'Meta Lead Ads'],
                                ['09:14', 'Status → Reviewed', 'Pat'],
                                ['09:20', 'Marked called', 'Pat'],
                                ['09:31', 'Note added', 'Pat'],
                                ['10:02', 'CSV export · 41 rows', 'Northwind'],
                                ['11:40', 'Duplicate flagged', '#1187'],
                                ['14:05', 'Priority → High', 'Northwind'],
                                ['03:00', 'Expired leads purged', 'scheduler'],
                            ] as [$time, $event, $who])
                                <li><span class="t">{{ $time }}</span>{{ $event }}<span class="tk-by">{{ $who }}</span></li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="badges">
                <span class="badge live"><i></i>0 telemetry calls</span>
                <span class="badge">Secrets encrypted at rest</span>
                <span class="badge">Encrypted backups</span>
                <span class="badge">Runs on your server</span>
            </div>
        </div>
    </div>
</section>
