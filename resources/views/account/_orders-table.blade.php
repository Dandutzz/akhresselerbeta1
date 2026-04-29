@if ($orders->isEmpty())
    <div class="px-5 py-10 text-center text-sm text-slate-500">
        <p class="text-3xl mb-2">📦</p>
        Belum ada pesanan.
        <a href="{{ route('home') }}" class="text-brand font-semibold hover:underline">Mulai belanja →</a>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600 text-left">
                <tr>
                    <th class="px-5 py-2.5 font-semibold">Order Code</th>
                    <th class="px-5 py-2.5 font-semibold">Produk</th>
                    <th class="px-5 py-2.5 font-semibold">Total</th>
                    <th class="px-5 py-2.5 font-semibold">Status</th>
                    <th class="px-5 py-2.5 font-semibold">Tanggal</th>
                    <th class="px-5 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($orders as $order)
                    @php
                        $statusClass = match ($order->status) {
                            'paid' => 'bg-emerald-100 text-emerald-700',
                            'pending' => 'bg-amber-100 text-amber-700',
                            'cancelled', 'expired', 'failed' => 'bg-slate-200 text-slate-600',
                            'refunded' => 'bg-rose-100 text-rose-700',
                            default => 'bg-slate-100 text-slate-700',
                        };
                        $statusLabel = match ($order->status) {
                            'paid' => 'Lunas',
                            'pending' => 'Menunggu',
                            'cancelled' => 'Dibatalkan',
                            'expired' => 'Kadaluarsa',
                            'failed' => 'Gagal',
                            'refunded' => 'Refund',
                            default => $order->status,
                        };
                    @endphp
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $order->order_code }}</td>
                        <td class="px-5 py-3">
                            <div class="font-semibold text-slate-900">{{ $order->product?->name ?? '—' }}</div>
                            @if ($order->variant)
                                <div class="text-xs text-slate-500">{{ $order->variant->name }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 font-semibold">Rp {{ number_format($order->total_payment, 0, ',', '.') }}</td>
                        <td class="px-5 py-3"><span class="inline-flex items-center gap-1 rounded-full text-[11px] font-bold px-2.5 py-1 {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td class="px-5 py-3 text-slate-600">{{ $order->created_at->format('d M Y, H:i') }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('invoice.show', $order->order_code) }}"
                               class="text-brand font-semibold text-xs hover:underline">Detail</a>
                            @if ($order->isPaid() && ! $order->review()->exists())
                                <a href="{{ route('account.reviews.create', $order->order_code) }}"
                                   class="ml-2 text-amber-600 font-semibold text-xs hover:underline">★ Review</a>
                            @elseif ($order->review()->exists())
                                <span class="ml-2 text-slate-400 text-xs">Sudah direview</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (! empty($paginated))
        <div class="px-5 py-3 border-t border-slate-100">
            {{ $orders->links() }}
        </div>
    @endif
@endif
