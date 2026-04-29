<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Models\Stock;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items.product', 'items.variant', 'items.stock', 'product', 'variant', 'stock']))
            ->columns([
                TextColumn::make('order_code')->label('Kode')->searchable()->copyable(),
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        $count = $record->items()->count();
                        if ($count <= 1) {
                            return $state ?: ($record->product?->name ?? '—');
                        }
                        $names = $record->items()->with('product')->get()
                            ->map(fn ($i) => $i->product?->name)
                            ->filter()
                            ->unique()
                            ->take(2)
                            ->implode(', ');

                        return $names.' +'.($count - 2 > 0 ? ($count - 2).' lain' : '').' ('.$count.' item)';
                    })
                    ->wrap(),
                TextColumn::make('variant.name')
                    ->label('Paket')
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        $items = $record->items;
                        if ($items->isEmpty()) {
                            return $state ?: ($record->variant?->name ?? '—');
                        }
                        if ($items->count() === 1) {
                            return $items->first()->variant?->name ?? '—';
                        }

                        return $items->count().' varian';
                    }),
                TextColumn::make('customer_email')->label('Email Pembeli')->searchable()->copyable(),
                TextColumn::make('customer_phone')->label('No HP Pembeli')->searchable()->copyable()->toggleable(),
                TextColumn::make('stock.email_or_phone')
                    ->label('Akun Terkirim')
                    ->copyable()
                    ->placeholder('— belum dikirim —')
                    ->formatStateUsing(function ($state, $record) {
                        $items = $record->items()->with('stock')->get();
                        if ($items->isNotEmpty()) {
                            $delivered = $items->filter(fn ($i) => $i->stock !== null);
                            if ($delivered->isEmpty()) {
                                return null;
                            }
                            if ($delivered->count() === 1) {
                                return $delivered->first()->stock->email_or_phone ?? '—';
                            }

                            return $delivered->count().'/'.$items->count().' akun';
                        }

                        return $state;
                    })
                    ->color(function ($record) {
                        $items = $record->items;
                        if ($items->isNotEmpty()) {
                            $allDelivered = $items->every(fn ($i) => $i->stock_id !== null);

                            return $allDelivered ? 'success' : 'warning';
                        }

                        return $record?->stock_id ? 'success' : 'warning';
                    })
                    ->tooltip(function ($record) {
                        if ($record->items->isNotEmpty()) {
                            return 'Multi-item order — buka detail order untuk lihat semua akun';
                        }

                        return $record?->stock_id ? 'Stock #'.$record->stock_id : null;
                    }),
                TextColumn::make('total_payment')
                    ->label('Total')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => Order::STATUS_PENDING,
                        'success' => Order::STATUS_PAID,
                        'danger' => [Order::STATUS_FAILED, Order::STATUS_EXPIRED, Order::STATUS_CANCELLED],
                        'secondary' => Order::STATUS_REFUNDED,
                    ]),
                TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable(),
                TextColumn::make('paid_at')->label('Dibayar')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Order::STATUS_PENDING => 'Pending',
                    Order::STATUS_PAID => 'Paid',
                    Order::STATUS_FAILED => 'Failed',
                    Order::STATUS_EXPIRED => 'Expired',
                    Order::STATUS_CANCELLED => 'Cancelled',
                    Order::STATUS_REFUNDED => 'Refunded',
                ]),

                Filter::make('needs_manual_delivery')
                    ->label('Perlu Kirim Akun Manual')
                    ->toggle()
                    ->query(fn (Builder $query) => $query
                        ->where('status', Order::STATUS_PAID)
                        ->whereNull('stock_id')),

                // Search akun terjual by email/phone. Kolom ter-encrypt jadi gak bisa
                // pakai LIKE — decrypt di PHP dulu (sama pattern dengan Stocks resource).
                Filter::make('stock_email')
                    ->label('Cari berdasar email/no HP akun terjual')
                    ->schema([
                        TextInput::make('value')
                            ->label('Email / No HP')
                            ->placeholder('contoh: akun123@gmail.com')
                            ->helperText('Untuk melacak siapa pembeli yang dapat akun ini.'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $needle = trim((string) ($data['value'] ?? ''));
                        if ($needle === '') {
                            return $query;
                        }

                        // Decrypt semua stocks di PHP, filter yang match (case-insensitive partial),
                        // lalu filter orders by stock_id IN (...).
                        $matchingStockIds = Stock::query()
                            ->whereNotNull('id')
                            ->get(['id', 'email_or_phone'])
                            ->filter(fn (Stock $s) => stripos((string) $s->email_or_phone, $needle) !== false)
                            ->pluck('id')
                            ->all();

                        if (empty($matchingStockIds)) {
                            // Force empty result instead of returning unfiltered query.
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereIn('stock_id', $matchingStockIds);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
