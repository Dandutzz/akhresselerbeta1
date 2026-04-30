<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Pesanan Terbaru';

    public function getColumnSpan(): int|string|array
    {
        return [
            'default' => 'full',
            'md' => 2,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pesanan Terbaru')
            ->description('10 transaksi paling baru')
            ->query(Order::query()->latest()->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('order_code')
                    ->label('Kode')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold'),
                TextColumn::make('source')
                    ->label('Saluran')
                    ->badge()
                    ->icon(fn (?string $state) => $state === Order::SOURCE_TELEGRAM ? 'heroicon-o-paper-airplane' : 'heroicon-o-globe-alt')
                    ->colors([
                        'info' => Order::SOURCE_TELEGRAM,
                        'gray' => Order::SOURCE_WEB,
                    ])
                    ->formatStateUsing(fn (?string $state) => $state === Order::SOURCE_TELEGRAM ? 'Telegram' : 'Web'),
                TextColumn::make('customer_email')
                    ->label('Customer')
                    ->limit(28)
                    ->placeholder('-'),
                TextColumn::make('total_payment')
                    ->label('Total')
                    ->money('IDR', locale: 'id'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => Order::STATUS_PAID,
                        'warning' => Order::STATUS_PENDING,
                        'gray' => Order::STATUS_EXPIRED,
                        'danger' => [Order::STATUS_FAILED, Order::STATUS_CANCELLED],
                        'info' => Order::STATUS_REFUNDED,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Order::STATUS_PAID => 'Sukses',
                        Order::STATUS_PENDING => 'Menunggu',
                        Order::STATUS_EXPIRED => 'Kadaluarsa',
                        Order::STATUS_FAILED => 'Gagal',
                        Order::STATUS_CANCELLED => 'Dibatalkan',
                        Order::STATUS_REFUNDED => 'Refund',
                        default => ucfirst($state),
                    }),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->since()
                    ->tooltip(fn (Order $record) => $record->created_at?->format('d M Y H:i')),
            ]);
    }
}
