{{-- Mini-cart popup: dipanggil dari tombol [data-add-to-cart] di product page.
     Submit AJAX ke /keranjang/add → JSON balik dengan summary cart → tampilkan
     drawer dari kanan tanpa pindah halaman. --}}
<div id="mini-cart-overlay" class="fixed inset-0 bg-black/50 z-[60] hidden" data-mini-cart-close></div>
<aside id="mini-cart-drawer"
       class="fixed top-0 right-0 z-[61] h-full w-full sm:w-[420px] bg-white shadow-2xl translate-x-full transition-transform duration-200 flex flex-col">
    <header class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-brand text-white">
        <div>
            <div class="font-extrabold text-lg flex items-center gap-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-13z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6L4 2H2"/></svg>
                Keranjang
            </div>
            <div class="text-xs opacity-90" data-mini-cart-count>0 item</div>
        </div>
        <button type="button" class="text-white/80 hover:text-white text-2xl leading-none" data-mini-cart-close>&times;</button>
    </header>

    <div class="flex-1 overflow-y-auto px-4 py-3" data-mini-cart-list>
        <div class="text-sm text-slate-400 text-center py-12" data-mini-cart-empty>Keranjang masih kosong.</div>
    </div>

    <footer class="border-t border-slate-200 p-4 bg-slate-50">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-semibold text-slate-700">Total</span>
            <span class="text-lg font-extrabold text-slate-900" data-mini-cart-subtotal>Rp 0</span>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" class="rounded-xl border border-slate-300 bg-white text-slate-700 font-semibold px-4 py-2.5 text-sm hover:bg-slate-100" data-mini-cart-close>
                Lanjut Belanja
            </button>
            <a href="{{ route('cart.index') }}" class="inline-flex justify-center rounded-xl btn-brand font-semibold px-4 py-2.5 text-sm">
                Bayar Semua →
            </a>
        </div>
    </footer>
</aside>

<script>
(function () {
    const overlay = document.getElementById('mini-cart-overlay');
    const drawer = document.getElementById('mini-cart-drawer');
    const list = drawer.querySelector('[data-mini-cart-list]');
    const empty = drawer.querySelector('[data-mini-cart-empty]');
    const subtotalEl = drawer.querySelector('[data-mini-cart-subtotal]');
    const countEl = drawer.querySelector('[data-mini-cart-count]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    function open() {
        overlay.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        overlay.classList.add('hidden');
        drawer.classList.add('translate-x-full');
        document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-mini-cart-close]').forEach(b => b.addEventListener('click', close));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

    function render(cart) {
        countEl.textContent = (cart.distinct || 0) + ' jenis · ' + (cart.count || 0) + ' pcs';
        subtotalEl.textContent = cart.subtotal_formatted || 'Rp 0';
        list.innerHTML = '';
        if (! cart.items || cart.items.length === 0) {
            list.appendChild(empty);
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');
        cart.items.forEach(it => {
            const row = document.createElement('div');
            row.className = 'flex items-start gap-3 py-3 border-b border-slate-100 last:border-0';
            row.innerHTML = `
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-slate-900 truncate">${escape(it.product || '—')}</div>
                    <div class="text-xs text-slate-500">Paket: ${escape(it.variant || '—')} · ${it.unit_price_formatted}</div>
                    <div class="text-xs text-slate-400 mt-0.5">Qty ${it.qty}</div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-sm text-slate-900 whitespace-nowrap">${it.line_total_formatted}</div>
                    <button type="button" class="text-[11px] text-rose-500 hover:underline mt-1" data-remove="${it.id}">Hapus</button>
                </div>
            `;
            list.appendChild(row);
        });

        list.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', () => removeItem(btn.dataset.remove));
        });
    }

    function escape(s) {
        return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    }

    async function removeItem(id) {
        const fd = new FormData();
        fd.set('_method', 'DELETE');
        fd.set('_token', csrf);
        await fetch('/keranjang/' + id, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        }).catch(() => {});
        const r = await fetch('{{ route('cart.summary') }}', { headers: { Accept: 'application/json' } });
        const data = await r.json();
        render(data);
    }

    document.querySelectorAll('[data-add-to-cart]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            btn.disabled = true;
            btn.classList.add('opacity-60');
            const original = btn.innerHTML;
            btn.innerHTML = 'Menambahkan...';
            try {
                const fd = new FormData();
                fd.set('_token', csrf);
                fd.set('product_variant_id', btn.dataset.variantId);
                fd.set('json', '1');
                const res = await fetch('{{ route('cart.add') }}', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                const data = await res.json();
                if (data.ok) {
                    render(data.cart);
                    open();
                } else {
                    alert(data.message || 'Gagal menambahkan ke keranjang.');
                }
            } catch (err) {
                alert('Gagal menambahkan ke keranjang. Coba lagi.');
            } finally {
                btn.disabled = false;
                btn.classList.remove('opacity-60');
                btn.innerHTML = original;
            }
        });
    });
})();
</script>
