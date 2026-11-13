<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#14261f">
    <title>{{ $pageTitle }} · Sanctum Scan</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #17251f;
            --muted: #64736c;
            --line: #e3e9e4;
            --paper: #f5f7f3;
            --green: #1d5b42;
            --green-soft: #e7f0e9;
            --gold: #b38a42;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background:
                radial-gradient(circle at 10% 0%, rgba(179,138,66,.10), transparent 34rem),
                var(--paper);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.55;
        }
        .shell { width: min(100% - 32px, 720px); margin: 0 auto; padding: 30px 0 44px; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .mark {
            width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center;
            background: var(--green); color: white; font-weight: 800; letter-spacing: -.05em;
        }
        .brand-name { font-size: 13px; font-weight: 800; letter-spacing: .15em; }
        .brand-sub { font-size: 11px; color: var(--muted); letter-spacing: .16em; text-transform: uppercase; }
        .card {
            background: #fff; border: 1px solid var(--line); border-radius: 24px;
            box-shadow: 0 20px 65px rgba(24, 45, 34, .07); overflow: hidden;
        }
        .hero { padding: 34px 34px 26px; border-bottom: 1px solid var(--line); }
        .eyebrow { color: var(--green); font-size: 11px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
        h1 { font-size: clamp(28px, 6vw, 42px); line-height: 1.08; letter-spacing: -.045em; margin: 12px 0 12px; }
        .description { color: var(--muted); margin: 0; max-width: 58ch; white-space: pre-line; }
        .content { padding: 26px 34px 34px; }
        .pill {
            display: inline-flex; align-items: center; gap: 7px; border-radius: 999px;
            padding: 7px 11px; background: var(--green-soft); color: var(--green);
            font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
        }
        .section-title { margin: 0 0 13px; font-size: 13px; letter-spacing: .09em; text-transform: uppercase; }
        .details { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px; margin: 20px 0 26px; }
        .detail { padding: 13px 14px; border: 1px solid var(--line); border-radius: 13px; min-width: 0; }
        .detail-label { display: block; color: var(--muted); font-size: 11px; margin-bottom: 3px; }
        .detail-value { display: block; font-size: 14px; font-weight: 700; overflow-wrap: anywhere; }
        .seller {
            display: flex; justify-content: space-between; gap: 18px; align-items: center;
            padding: 17px; background: #f7f9f6; border: 1px solid var(--line); border-radius: 15px; margin-top: 22px;
        }
        .seller-label { display:block; color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.1em; }
        .seller-name { display:block; font-weight:750; margin-top:3px; }
        .seller-handle { color:var(--green); font-size:13px; overflow-wrap:anywhere; }
        .provenance {
            margin-top: 24px; border: 1px solid #eadfca; background: #fffaf0;
            border-radius: 16px; padding: 18px;
        }
        .provenance-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; }
        .declared { color:#805d1f; background:#f7edda; border-radius:999px; padding:5px 9px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
        .event { border-left: 2px solid #d8c294; padding: 1px 0 1px 14px; margin: 16px 0 0 3px; }
        .event-title { font-size:13px; font-weight:750; margin-bottom:3px; }
        .event-copy { color:#655b49; font-size:12px; }
        .disclaimer { color:var(--muted); font-size:12px; margin: 14px 0 0; }
        .footer { text-align:center; color:var(--muted); font-size:11px; margin-top:22px; }
        .footer strong { color:var(--green); letter-spacing:.08em; }
        @media (max-width:560px) {
            .shell { width:min(100% - 20px, 720px); padding-top:18px; }
            .hero { padding:26px 21px 22px; }
            .content { padding:22px 21px 25px; }
            .details { grid-template-columns:1fr 1fr; }
            .seller { align-items:flex-start; flex-direction:column; }
        }
    </style>
</head>
<body>
@php
    $resource = $scan['resource'] ?? [];
    $details = $resource['details'] ?? [];
    $profile = $resource['profile'] ?? [];
    $provenance = $resource['provenance'] ?? null;
    $resourceType = $resource['type'] ?? 'resource';
    $typeLabel = $resourceType === 'agricultural_batch'
        ? 'Agricultural batch'
        : ($resourceType === 'merchant' ? 'Merchant identity' : ucfirst(str_replace('_', ' ', $resourceType)));
@endphp
<main class="shell">
    <header class="brand">
        <div class="mark" aria-hidden="true">AS</div>
        <div>
            <div class="brand-name">ATLAS SANCTUM</div>
            <div class="brand-sub">Sanctum Scan · Verified exchange infrastructure</div>
        </div>
    </header>

    <article class="card">
        <section class="hero">
            <span class="pill">{{ $typeLabel }}</span>
            <h1>{{ $resource['display_name'] ?? 'Public resource' }}</h1>
            @if (!empty($resource['description']))
                <p class="description">{{ $resource['description'] }}</p>
            @else
                <p class="description">A public record shared through the Sanctum Scan protocol.</p>
            @endif
        </section>

        <section class="content">
            @if (!empty($details))
                <h2 class="section-title">Resource details</h2>
                <div class="details">
                    @if (!empty($details['category']))
                        <div class="detail"><span class="detail-label">Category</span><span class="detail-value">{{ $details['category'] }}</span></div>
                    @endif
                    @if (!empty($details['reference_code']))
                        <div class="detail"><span class="detail-label">Reference</span><span class="detail-value">{{ $details['reference_code'] }}</span></div>
                    @endif
                    @if (!empty($details['variety']))
                        <div class="detail"><span class="detail-label">Variety</span><span class="detail-value">{{ $details['variety'] }}</span></div>
                    @endif
                    @if (!empty($details['origin']))
                        <div class="detail"><span class="detail-label">Declared origin</span><span class="detail-value">{{ $details['origin'] }}</span></div>
                    @endif
                    @if (!empty($details['harvest_date']))
                        <div class="detail"><span class="detail-label">Harvest date</span><span class="detail-value">{{ $details['harvest_date'] }}</span></div>
                    @endif
                </div>
            @endif

            @if (!empty($profile['seller_name']) || !empty($profile['handle']))
                <div class="seller">
                    <div>
                        <span class="seller-label">Merchant / record publisher</span>
                        <span class="seller-name">{{ $profile['seller_name'] ?? $resource['display_name'] }}</span>
                        @if (!empty($profile['seller_handle']))
                            <span class="seller-handle">{{ '@' . $profile['seller_handle'] }}</span>
                        @elseif (!empty($profile['handle']))
                            <span class="seller-handle">{{ '@' . $profile['handle'] }}</span>
                        @endif
                    </div>
                    <span class="pill">Public profile</span>
                </div>
            @endif

            @if (!empty($provenance))
                <section class="provenance" aria-labelledby="provenance-heading">
                    <div class="provenance-head">
                        <h2 id="provenance-heading" class="section-title" style="margin:0">Origin & provenance</h2>
                        <span class="declared">{{ $provenance['label'] ?? 'Merchant-declared' }}</span>
                    </div>
                    @foreach (($provenance['events'] ?? []) as $event)
                        <div class="event">
                            <div class="event-title">{{ ucfirst(str_replace('_', ' ', $event['type'] ?? 'recorded event')) }}</div>
                            @if (!empty($event['location']))
                                <div class="event-copy">Location declared: {{ $event['location'] }}</div>
                            @endif
                            @if (!empty($event['statement']))
                                <div class="event-copy">{{ $event['statement'] }}</div>
                            @endif
                            @if (!empty($event['recorded_at']))
                                <div class="event-copy">Recorded: {{ $event['recorded_at'] }}</div>
                            @endif
                            <div class="event-copy">Evidence status: {{ str_replace('_', ' ', $event['verification_status'] ?? 'merchant_declared') }}</div>
                        </div>
                    @endforeach
                    <p class="disclaimer">These details were supplied by the merchant. This record is not independent proof of origin, ownership, quality, certification or delivery.</p>
                </section>
            @elseif ($resourceType === 'merchant')
                <p class="disclaimer">This is a public business identifier. Scanning it does not authorize a payment or confirm a transaction.</p>
            @endif

            @if (!empty($scan['expires_at']))
                <p class="disclaimer">This scan reference expires at {{ $scan['expires_at'] }}.</p>
            @endif
        </section>
    </article>

    <footer class="footer">
        <strong>ATLAS SANCTUM</strong> · Setting hearts and minds above.
        <div>Sanctum Scan provides public information only. No payment is initiated by this page.</div>
    </footer>
</main>
</body>
</html>
