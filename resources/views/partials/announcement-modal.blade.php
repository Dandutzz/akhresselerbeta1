@php
    $announcements = \App\Models\Announcement::published()
        ->orderByDesc('published_at')
        ->orderByDesc('id')
        ->limit(3)
        ->get();
    $signature = $announcements->pluck('id')->implode('-');
@endphp

@if ($announcements->isNotEmpty())
    <div id="announcement-modal"
         class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4"
         data-signature="{{ $signature }}"
         role="dialog" aria-modal="true" aria-labelledby="announcement-title">
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
            <button type="button"
                    class="absolute top-3 right-3 z-10 inline-flex items-center justify-center w-8 h-8 rounded-full bg-white/80 hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition"
                    aria-label="Tutup"
                    data-ann-close>
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                    <path d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>

            <div class="relative">
                @foreach ($announcements as $i => $ann)
                    <article class="ann-slide {{ $i === 0 ? '' : 'hidden' }} px-7 pt-10 pb-6 text-center"
                             data-index="{{ $i }}">
                        @if ($url = $ann->imageUrl())
                            <img src="{{ $url }}" alt="" class="mx-auto mb-4 max-h-32 rounded-xl object-contain">
                        @else
                            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 ring-8 ring-amber-50">
                                <svg viewBox="0 0 24 24" class="h-9 w-9 text-amber-500" fill="currentColor">
                                    <path d="M12 2 1 21h22L12 2Zm0 6 7.5 13H4.5L12 8Zm-1 4v4h2v-4h-2Zm0 6v2h2v-2h-2Z"/>
                                </svg>
                            </div>
                        @endif

                        <h3 id="announcement-title-{{ $ann->id }}"
                            class="text-xl font-bold text-slate-900 mb-2 inline-flex items-center gap-2 justify-center">
                            <span aria-hidden="true">📣</span>
                            <span>{{ $ann->title }}</span>
                        </h3>
                        <div class="mx-auto mb-4 h-1 w-20 rounded-full bg-gradient-to-r from-rose-300 via-rose-500 to-rose-300"></div>
                        <div class="text-sm leading-relaxed text-slate-600 whitespace-pre-line">{{ $ann->body }}</div>

                        @if ($ann->cta_label && $ann->cta_url)
                            <a href="{{ $ann->cta_url }}" target="_blank" rel="noopener"
                               class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-b from-rose-700 to-rose-900 hover:from-rose-800 hover:to-rose-950 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-rose-900/30 transition">
                                {{ $ann->cta_label }}
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14M13 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>

            @if ($announcements->count() > 1)
                <div class="flex items-center justify-between px-6 pb-3">
                    <button type="button"
                            class="ann-prev rounded-full p-2 text-slate-500 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed"
                            aria-label="Sebelumnya">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 6l-6 6 6 6"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-2" role="tablist" aria-label="Pengumuman lainnya">
                        @foreach ($announcements as $i => $ann)
                            <button type="button"
                                    class="ann-dot h-2 w-2 rounded-full transition {{ $i === 0 ? 'bg-rose-700 w-6' : 'bg-slate-300 hover:bg-slate-400' }}"
                                    data-target="{{ $i }}"
                                    aria-label="Pengumuman {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                    <button type="button"
                            class="ann-next rounded-full p-2 text-slate-500 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed"
                            aria-label="Berikutnya">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 6l6 6-6 6"/>
                        </svg>
                    </button>
                </div>
            @endif

            <button type="button"
                    class="block w-full text-center text-sm text-slate-500 hover:text-slate-700 py-3 border-t border-slate-100"
                    data-ann-close>Tutup</button>
        </div>
    </div>

    <script>
    (function() {
        const modal = document.getElementById('announcement-modal');
        if (! modal) return;
        const signature = modal.dataset.signature || '';
        const storageKey = 'ann_dismissed_v1';
        const dismissedSig = (() => { try { return localStorage.getItem(storageKey); } catch (e) { return null; } })();
        if (dismissedSig === signature) return;

        const slides = modal.querySelectorAll('.ann-slide');
        const dots = modal.querySelectorAll('.ann-dot');
        const prev = modal.querySelector('.ann-prev');
        const next = modal.querySelector('.ann-next');
        let idx = 0;
        const total = slides.length;

        function render() {
            slides.forEach((s, i) => s.classList.toggle('hidden', i !== idx));
            dots.forEach((d, i) => {
                d.classList.toggle('bg-rose-700', i === idx);
                d.classList.toggle('w-6', i === idx);
                d.classList.toggle('bg-slate-300', i !== idx);
                d.classList.toggle('hover:bg-slate-400', i !== idx);
            });
            if (prev) prev.disabled = idx === 0;
            if (next) next.disabled = idx === total - 1;
        }

        if (prev) prev.addEventListener('click', () => { if (idx > 0) { idx--; render(); } });
        if (next) next.addEventListener('click', () => { if (idx < total - 1) { idx++; render(); } });
        dots.forEach(d => d.addEventListener('click', () => { idx = parseInt(d.dataset.target, 10) || 0; render(); }));

        modal.querySelectorAll('[data-ann-close]').forEach(btn => {
            btn.addEventListener('click', () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                try { localStorage.setItem(storageKey, signature); } catch (e) {}
            });
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                try { localStorage.setItem(storageKey, signature); } catch (e2) {}
            }
        });

        setTimeout(() => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            render();
        }, 700);
    })();
    </script>
@endif
