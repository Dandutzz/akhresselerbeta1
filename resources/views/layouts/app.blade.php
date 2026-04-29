<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($site?->store_name ?? 'Akhpremium Store') . ' — Akun Premium Legal & Murah')</title>
    <meta name="description" content="@yield('meta_description', $site?->tagline ?? 'Toko akun premium legal dengan auto-delivery 24 jam.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: { DEFAULT: 'var(--brand)', soft: 'var(--brand-soft)', dark: 'var(--brand-dark)' },
                        accent: { DEFAULT: 'var(--accent)' },
                    },
                    boxShadow: {
                        card: '0 8px 24px -12px rgba(15,23,42,0.18)',
                        soft: '0 6px 30px -12px rgba(124,58,237,0.25)',
                    },
                },
            },
        }
    </script>
    <style>
        :root {
            --brand: {{ $site?->brand_color ?? '#7c3aed' }};
            --brand-dark: color-mix(in oklab, {{ $site?->brand_color ?? '#7c3aed' }} 82%, black);
            --brand-soft: color-mix(in oklab, {{ $site?->brand_color ?? '#7c3aed' }} 8%, white);
            --accent: {{ $site?->accent_color ?? '#06b6d4' }};
        }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        .btn-brand { background: var(--brand); color: white; }
        .btn-brand:hover { background: var(--brand-dark); }
        .ring-brand { --tw-ring-color: var(--brand); }
        .text-brand { color: var(--brand); }
        .border-brand { border-color: var(--brand); }
        .gradient-hero {
            background:
                radial-gradient(1100px 480px at 90% -10%, color-mix(in oklab, var(--accent) 18%, transparent), transparent 60%),
                radial-gradient(800px 360px at -10% 110%, color-mix(in oklab, var(--brand) 22%, transparent), transparent 60%),
                linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #312e81 100%);
        }
        .price-strike { text-decoration: line-through; color: #94a3b8; font-size: .8rem; }
        .marquee-mask { mask-image: linear-gradient(90deg, transparent, #000 5%, #000 95%, transparent); }
        details > summary { list-style: none; cursor: pointer; }
        details > summary::-webkit-details-marker { display: none; }
        .prose-content :where(p,ul,ol,h2,h3,h4) { margin-top: .8em; }
        .prose-content :where(h2,h3,h4) { font-weight: 700; }
        .prose-content :where(h2) { font-size: 1.5rem; }
        .prose-content :where(h3) { font-size: 1.25rem; }
        .prose-content :where(ul,ol) { padding-left: 1.5rem; list-style: disc; }
        .prose-content :where(ol) { list-style: decimal; }
        .prose-content :where(a) { color: var(--brand); text-decoration: underline; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">

@include('partials.header')

@if (session('success'))
    <div class="bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-sm py-2 px-4 text-center">
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="bg-rose-50 border-b border-rose-200 text-rose-800 text-sm py-2 px-4 text-center">
        {{ session('error') }}
    </div>
@endif
@if (session('warning'))
    <div class="bg-amber-50 border-b border-amber-200 text-amber-800 text-sm py-2 px-4 text-center">
        {{ session('warning') }}
    </div>
@endif

<main class="flex-1">
    @yield('content')
</main>

@include('partials.footer')

@if (! empty($site?->wa_number))
    <a href="{{ $site?->waLink() }}" target="_blank" rel="noopener"
       class="fixed bottom-5 right-5 z-50 inline-flex items-center gap-2 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold pl-3 pr-4 py-3 shadow-lg shadow-emerald-500/30 transition">
        <svg viewBox="0 0 24 24" class="w-6 h-6" fill="currentColor" aria-hidden="true">
            <path d="M19.07 4.93A10 10 0 0 0 4.13 18.4L3 22l3.72-1.1A10 10 0 1 0 19.07 4.93Zm-7.06 15.13a8.06 8.06 0 0 1-4.1-1.13l-.3-.18-2.21.66.65-2.16-.2-.32a8 8 0 1 1 6.16 3.13Zm4.42-5.84c-.24-.12-1.45-.71-1.67-.79-.22-.08-.39-.12-.55.12s-.63.79-.77.95c-.14.16-.28.18-.52.06a6.5 6.5 0 0 1-1.92-1.18 7.18 7.18 0 0 1-1.33-1.66c-.14-.24 0-.36.1-.48.1-.1.24-.28.36-.42a1.6 1.6 0 0 0 .24-.4.45.45 0 0 0 0-.42c-.06-.12-.55-1.32-.75-1.8-.2-.48-.4-.42-.55-.42h-.47a.9.9 0 0 0-.66.3 2.78 2.78 0 0 0-.86 2.06 4.83 4.83 0 0 0 1 2.55c.12.16 1.74 2.66 4.21 3.73a14.06 14.06 0 0 0 1.4.52 3.36 3.36 0 0 0 1.55.1 2.55 2.55 0 0 0 1.66-1.18 2.06 2.06 0 0 0 .14-1.18c-.06-.1-.22-.16-.46-.28Z"/>
        </svg>
        <span class="text-sm leading-tight hidden sm:inline">Live Chat<br><span class="text-xs opacity-90">Admin online</span></span>
    </a>
@endif

{{-- Floating notif (toast mengambang). Toggle di Site Settings admin. --}}
<div id="floating-notif"
     class="fixed bottom-24 left-5 z-40 hidden max-w-xs rounded-xl bg-white border border-slate-200 shadow-card px-4 py-3 text-sm">
    <div class="flex items-start gap-3">
        <span class="text-2xl">🛍️</span>
        <div class="flex-1">
            <div class="font-semibold text-slate-900" data-notif-name></div>
            <div class="text-xs text-slate-500" data-notif-product></div>
            <div class="text-[11px] text-slate-400 mt-0.5" data-notif-when></div>
        </div>
        <button type="button" class="text-slate-400 hover:text-slate-700 text-lg leading-none" data-notif-close>&times;</button>
    </div>
</div>
<script>
(function() {
    const el = document.getElementById('floating-notif');
    const nameEl = el.querySelector('[data-notif-name]');
    const prodEl = el.querySelector('[data-notif-product]');
    const whenEl = el.querySelector('[data-notif-when]');
    const closeBtn = el.querySelector('[data-notif-close]');

    let items = [];
    let cfg = { interval_min: 20, interval_max: 60 };
    let dismissed = false;
    let queueIdx = 0;
    let hideTimer = null;

    closeBtn.addEventListener('click', () => {
        el.classList.add('hidden');
        dismissed = true;
    });

    function show(item) {
        nameEl.textContent = item.name + ' baru saja membeli';
        prodEl.textContent = item.product;
        whenEl.textContent = item.when || '';
        el.classList.remove('hidden');
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => el.classList.add('hidden'), 6000);
    }

    function pickRandomDelay() {
        const min = cfg.interval_min || 20;
        const max = cfg.interval_max || 60;
        return (Math.floor(Math.random() * (max - min + 1)) + min) * 1000;
    }

    function loop() {
        if (dismissed || items.length === 0) return;
        const item = items[queueIdx % items.length];
        queueIdx++;
        show(item);
        setTimeout(loop, pickRandomDelay());
    }

    fetch('{{ route('api.floating-notifications') }}', { headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (! data.enabled || ! data.items || data.items.length === 0) return;
            items = data.items;
            cfg.interval_min = data.interval_min || 20;
            cfg.interval_max = data.interval_max || 60;
            // Tampilkan pertama setelah 4 detik supaya page sempat load.
            setTimeout(loop, 4000);
        })
        .catch(() => {});
})();
</script>

@stack('scripts')
</body>
</html>
