@extends('layouts.app')

@section('title', $product->name . ' — ' . ($site?->store_name ?? 'Akhpremium Store'))

@section('content')
<section class="py-8 md:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="text-sm text-slate-500 mb-5">
            <a href="{{ route('home') }}" class="hover:text-brand">Home</a>
            <span class="mx-1.5">/</span>
            @if ($product->category)
                <a href="{{ route('home', ['kategori' => $product->category->slug]) }}" class="hover:text-brand">{{ $product->category->name }}</a>
                <span class="mx-1.5">/</span>
            @endif
            <span class="text-slate-700 font-semibold">{{ $product->name }}</span>
        </nav>

        <div class="grid md:grid-cols-2 gap-8 lg:gap-10">
            {{-- Gambar --}}
            <div class="rounded-3xl bg-white border border-slate-200 overflow-hidden shadow-card">
                <div class="aspect-[4/3] md:aspect-[5/4] bg-gradient-to-br from-slate-50 to-slate-100">
                    @if ($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-5xl text-slate-300">🎬</div>
                    @endif
                </div>
            </div>

            {{-- Detail + varian --}}
            <div>
                @if ($product->category)
                    <span class="inline-block uppercase text-xs tracking-widest text-brand font-bold mb-2">{{ $product->category->name }}</span>
                @endif
                <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">{{ $product->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    @php $hasAuto = $product->variants->contains(fn ($v) => $v->isAutoSend()); @endphp
                    @if ($hasAuto)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 font-semibold">⚡ Auto-Delivery</span>
                    @endif
                    @if ($product->is_best_seller)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 px-2 py-0.5 font-semibold">★ Best Seller</span>
                    @endif
                    @php $displaySold = $product->displaySoldCount(); @endphp
                    @if ($displaySold > 0)
                        <span>· {{ number_format($displaySold, 0, ',', '.') }} terjual</span>
                    @endif
                    @if (! empty($reviewStats['avg']))
                        <span>· ⭐ {{ $reviewStats['avg'] }} ({{ $reviewStats['count'] }} ulasan)</span>
                    @endif
                </div>

                @if ($product->short_description)
                    <p class="mt-4 text-slate-600">{{ $product->short_description }}</p>
                @endif

                {{-- Varian --}}
                <div class="mt-6 rounded-2xl border border-slate-200 bg-white divide-y">
                    <div class="px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-700">Pilih Paket</div>
                    @forelse ($product->variants as $variant)
                        @php
                            $isOOS = $variant->isAutoSend() && $variant->available_stocks_count <= 0;
                            $fs = $variant->activeFlashsale();
                            $effective = $variant->effectivePrice();
                        @endphp
                        <div class="px-5 py-4 flex items-center gap-4">
                            <div class="flex-1">
                                <div class="font-semibold flex items-center gap-2 flex-wrap">
                                    {{ $variant->name }}
                                    @if ($fs)
                                        <span class="inline-flex items-center text-[10px] font-bold rounded px-1.5 py-0.5 bg-rose-600 text-white">FLASH -{{ $fs->discountPercent() }}%</span>
                                    @endif
                                    @if ($variant->warranty_days)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold rounded-full px-2 py-0.5 bg-emerald-50 text-emerald-700">🛡 Garansi {{ $variant->warranty_days }} Hari</span>
                                    @endif
                                    @if ($variant->shareTypeLabel())
                                        @php
                                            $shareCls = match ($variant->share_type) {
                                                'private' => 'bg-violet-50 text-violet-700',
                                                'sharing_antilimit' => 'bg-cyan-50 text-cyan-700',
                                                default => 'bg-sky-50 text-sky-700',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center text-[10px] font-bold rounded-full px-2 py-0.5 {{ $shareCls }}">{{ $variant->shareTypeLabel() }}</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    @if ($variant->isAutoSend())
                                        Stok: {{ $variant->available_stocks_count }} · Auto-delivery
                                    @else
                                        Manual delivery (admin kirim setelah pembayaran)
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                @if ($fs)
                                    <div class="text-rose-600 font-extrabold">Rp {{ number_format($effective, 0, ',', '.') }}</div>
                                    <div class="price-strike">Rp {{ number_format($variant->price, 0, ',', '.') }}</div>
                                @else
                                    <div class="font-extrabold">Rp {{ number_format($effective, 0, ',', '.') }}</div>
                                @endif
                            </div>
                            <div class="flex flex-col gap-1">
                                @if ($isOOS)
                                    <button type="button" disabled class="rounded-xl bg-slate-100 text-slate-400 cursor-not-allowed font-semibold px-4 py-2.5 text-sm">Habis</button>
                                @else
                                    <a href="{{ route('checkout.show', [$product, $variant]) }}"
                                       class="inline-flex justify-center rounded-xl btn-brand font-semibold px-4 py-2.5 text-sm">Beli Sekarang</a>
                                    @auth
                                        <button type="button"
                                                data-add-to-cart
                                                data-variant-id="{{ $variant->id }}"
                                                class="w-full rounded-xl border border-brand text-brand font-semibold px-4 py-2 text-xs hover:bg-brand hover:text-white transition">
                                            + Keranjang
                                        </button>
                                    @else
                                        <a href="{{ route('login') }}" class="text-[10px] text-slate-400 text-center hover:text-brand">Login untuk pakai keranjang</a>
                                    @endauth
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-sm text-slate-500">Belum ada varian.</div>
                    @endforelse
                </div>

                {{-- Deskripsi panjang --}}
                @if ($product->description)
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="font-bold mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                            Deskripsi
                        </h3>
                        <div class="prose-content text-slate-700 text-sm">{!! nl2br(e($product->description)) !!}</div>
                    </div>
                @endif

                {{-- Syarat & Ketentuan --}}
                @if ($product->terms_html)
                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/50 p-5">
                        <h3 class="font-bold mb-2 flex items-center gap-2 text-amber-900">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                            Syarat &amp; Ketentuan
                        </h3>
                        <div class="prose-content text-slate-700 text-sm">{!! $product->terms_html !!}</div>
                    </div>
                @endif

                <div class="mt-6 flex items-center gap-4 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1">🛡️ Garansi penuh</span>
                    <span>·</span>
                    <span class="inline-flex items-center gap-1">💳 Pembayaran aman via Pakasir</span>
                    <span>·</span>
                    <span class="inline-flex items-center gap-1">⚡ Auto delivery</span>
                </div>
            </div>
        </div>

        {{-- Reviews dari pelanggan --}}
        <div class="mt-12">
            <h2 class="text-2xl font-extrabold tracking-tight">Ulasan Pelanggan</h2>
            <div class="mt-2 flex items-center gap-2 text-sm text-slate-500">
                @if ($reviewStats['avg'])
                    <span class="text-amber-500 font-bold">⭐ {{ $reviewStats['avg'] }}</span>
                    <span>·</span>
                    <span>{{ $reviewStats['count'] }} ulasan</span>
                @else
                    <span>Belum ada ulasan untuk produk ini.</span>
                @endif
            </div>

            @if ($reviews->isNotEmpty())
                <div class="mt-5 grid md:grid-cols-2 gap-4">
                    @foreach ($reviews as $review)
                        <div class="rounded-2xl bg-white border border-slate-200 p-5">
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-slate-900">{{ $review->reviewer_name }}</div>
                                <div class="text-amber-500 text-sm">
                                    @for ($i = 1; $i <= 5; $i++){{ $i <= $review->rating ? '★' : '☆' }}@endfor
                                </div>
                            </div>
                            <div class="text-[11px] text-slate-400 mt-0.5">{{ $review->created_at->format('d M Y') }}</div>
                            @if ($review->comment)
                                <p class="mt-3 text-sm text-slate-700">{{ $review->comment }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
