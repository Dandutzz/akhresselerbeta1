@extends('layouts.app')

@section('title', 'Beri Review — ' . $order->product?->name)

@section('content')
<section class="py-10 md:py-14">
    <div class="max-w-xl mx-auto px-4">
        <a href="{{ route('account.orders.index') }}" class="text-sm text-slate-500 hover:text-brand">← Kembali ke history</a>

        <div class="mt-3 rounded-2xl bg-white border border-slate-200 p-6 md:p-8 shadow-card">
            <h1 class="text-2xl font-extrabold tracking-tight">Beri Review</h1>
            <p class="text-sm text-slate-500 mt-1">{{ $order->product?->name }} — {{ $order->variant?->name }}</p>

            @if ($errors->any())
                <div class="mt-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('account.reviews.store', $order->order_code) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Rating</label>
                    <div class="flex gap-1.5" id="star-rating">
                        @for ($i = 1; $i <= 5; $i++)
                            <label class="cursor-pointer">
                                <input type="radio" name="rating" value="{{ $i }}" class="sr-only peer" {{ old('rating', 5) == $i ? 'checked' : '' }}>
                                <span class="text-3xl peer-checked:text-amber-400 text-slate-300 hover:text-amber-300 transition">★</span>
                            </label>
                        @endfor
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5" for="comment">Komentar</label>
                    <textarea id="comment" name="comment" rows="4" maxlength="1000"
                              class="w-full rounded-xl border border-slate-200 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand"
                              placeholder="Bagaimana pengalaman kamu dengan produk ini?">{{ old('comment') }}</textarea>
                </div>
                <button type="submit" class="w-full rounded-xl btn-brand font-semibold py-2.5">Kirim Review</button>
                <p class="text-[11px] text-slate-400 text-center">Review akan tampil setelah disetujui admin.</p>
            </form>
        </div>
    </div>
</section>
@endsection
