<header x-data="{ open: false }" class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
            @if (! empty($site?->logo_path))
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($site?->logo_path) }}"
                     alt="{{ $site?->store_name }}" class="w-9 h-9 rounded-lg object-cover">
            @else
                <span class="w-9 h-9 rounded-lg flex items-center justify-center text-white font-bold"
                      style="background: var(--brand);">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($site?->store_name ?? 'A', 0, 1)) }}</span>
            @endif
            <span class="font-extrabold tracking-tight text-lg text-slate-900">{{ $site?->store_name ?? 'AKHPREMIUM' }}</span>
        </a>

        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-700 ml-4">
            <a href="{{ route('home') }}" class="hover:text-brand">Produk</a>
            <a href="{{ route('pages.cek-invoice') }}" class="hover:text-brand">Cek Invoice</a>
            <a href="{{ route('articles.index') }}" class="hover:text-brand">Artikel</a>
            <a href="{{ route('pages.faq') }}" class="hover:text-brand">FAQ</a>
            <a href="{{ route('pages.how-to-order') }}" class="hover:text-brand">Cara Pemesanan</a>
            <a href="{{ route('pages.terms') }}" class="hover:text-brand">Ketentuan</a>
        </nav>

        <div class="flex-1"></div>

        <div class="hidden md:flex items-center gap-2">
            @auth
                @php $cartCount = \App\Models\CartItem::where('user_id', auth()->id())->sum('quantity'); @endphp
                <a href="{{ route('cart.index') }}"
                   class="relative inline-flex items-center justify-center rounded-full w-10 h-10 border border-slate-200 hover:border-brand hover:text-brand text-slate-700 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
                    @if ($cartCount > 0)
                        <span class="absolute -top-1 -right-1 bg-rose-600 text-white text-[10px] font-bold rounded-full px-1.5 min-w-[18px] h-[18px] inline-flex items-center justify-center">{{ $cartCount }}</span>
                    @endif
                </a>
                <a href="{{ route('account.index') }}"
                   class="inline-flex items-center gap-2 rounded-full text-sm font-semibold px-4 py-2 border border-slate-200 hover:border-brand hover:text-brand text-slate-700 transition">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    {{ \Illuminate\Support\Str::limit(auth()->user()->name, 16) }}
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="inline-flex items-center gap-1.5 rounded-full text-sm font-semibold px-4 py-2 text-slate-700 hover:text-brand transition">
                    Masuk
                </a>
                <a href="{{ route('register') }}"
                   class="inline-flex items-center gap-1.5 rounded-full text-sm font-semibold px-4 py-2 border border-slate-200 hover:border-brand hover:text-brand text-slate-700 transition">
                    Daftar
                </a>
            @endauth

            @if (! empty($site?->wa_number))
                <a href="{{ $site?->waLink() }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-full text-sm font-semibold px-4 py-2 btn-brand transition">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor" aria-hidden="true"><path d="M19.07 4.93A10 10 0 0 0 4.13 18.4L3 22l3.72-1.1A10 10 0 1 0 19.07 4.93Z"/></svg>
                    Chat Admin
                </a>
            @endif
        </div>

        <button type="button" onclick="document.getElementById('mob-nav').classList.toggle('hidden')"
                class="md:hidden p-2 rounded-lg border border-slate-200 text-slate-700">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
    </div>

    <div id="mob-nav" class="hidden md:hidden border-t border-slate-200 bg-white">
        <div class="px-4 py-3 flex flex-col gap-2 text-sm font-medium text-slate-700">
            <a href="{{ route('home') }}" class="py-2">Produk</a>
            <a href="{{ route('pages.cek-invoice') }}" class="py-2">Cek Invoice</a>
            <a href="{{ route('articles.index') }}" class="py-2">Artikel</a>
            <a href="{{ route('pages.faq') }}" class="py-2">FAQ</a>
            <a href="{{ route('pages.how-to-order') }}" class="py-2">Cara Pemesanan</a>
            <a href="{{ route('pages.terms') }}" class="py-2">Ketentuan Order</a>
            <hr class="my-2 border-slate-200">
            @auth
                <a href="{{ route('account.index') }}" class="py-2 font-semibold">Akun Saya</a>
                <a href="{{ route('cart.index') }}" class="py-2">Keranjang</a>
                <a href="{{ route('account.orders.index') }}" class="py-2">History Pesanan</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="py-2 text-rose-600 text-left w-full">Keluar</button></form>
            @else
                <a href="{{ route('login') }}" class="py-2 font-semibold">Masuk</a>
                <a href="{{ route('register') }}" class="py-2">Daftar</a>
            @endauth
            @if (! empty($site?->wa_number))
                <a href="{{ $site?->waLink() }}" target="_blank" rel="noopener" class="py-2 mt-1 inline-flex items-center justify-center rounded-lg btn-brand font-semibold">Chat Admin</a>
            @endif
        </div>
    </div>
</header>
