<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\Stock;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Order')
                    ->columns(2)
                    ->schema([
                        TextInput::make('order_code')->disabled(),
                        Select::make('source')
                            ->label('Saluran Order')
                            ->options([
                                Order::SOURCE_WEB => 'Web',
                                Order::SOURCE_TELEGRAM => 'Telegram',
                            ])
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Channel asal order (otomatis di-set saat order dibuat).'),
                        Select::make('status')
                            ->options([
                                Order::STATUS_PENDING => 'Pending',
                                Order::STATUS_PAID => 'Paid',
                                Order::STATUS_FAILED => 'Failed',
                                Order::STATUS_EXPIRED => 'Expired',
                                Order::STATUS_CANCELLED => 'Cancelled',
                                Order::STATUS_REFUNDED => 'Refunded',
                            ])
                            ->required(),
                        TextInput::make('customer_email')->email()->disabled(),
                        TextInput::make('customer_phone')->disabled(),
                        TextInput::make('total_payment')->prefix('Rp')->disabled(),
                        TextInput::make('payment_method')->disabled(),
                        TextInput::make('payment_ref')->disabled(),
                        DateTimePicker::make('paid_at')->disabled(),
                        DateTimePicker::make('expired_at')->disabled(),
                    ]),

                // Section "Item Pesanan" — daftar SEMUA item di order ini (multi-item
                // friendly). Untuk tiap item:
                //  • baca-only: produk, varian, qty, harga.
                //  • Akun ter-assign (kalau ada): tampilkan email/password/info.
                //  • Pending delivery: input email/password/info → saat Save, buat
                //    Stock baru + assign ke OrderItem.stock_id (logic di EditOrder
                //    afterSave hook).
                Section::make('Item Pesanan')
                    ->description('Tiap item bisa dikirim akun terpisah. Untuk item yang belum ter-assign akun, isi form di bawah → akun otomatis tersimpan saat klik Save.')
                    ->icon('heroicon-o-shopping-bag')
                    ->visible(fn ($record) => $record && $record->items()->exists())
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull()
                            // Hook PER-ITEM saat Filament saveRelationships() — di sini
                            // kita intercept manual_* dari $itemData (yang TIDAK ada di
                            // $data parent) untuk bikin Stock + assign ke OrderItem.
                            // Ini path resmi karena Repeater::relationship() men-set
                            // dehydrated(false) di komponen → state items TIDAK masuk
                            // $data form parent.
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, $record) {
                                self::assignManualDeliveryToItem($data, $record);

                                // Strip kolom non-fillable agar fill+save Filament bersih.
                                unset(
                                    $data['manual_email_or_phone'],
                                    $data['manual_password'],
                                    $data['manual_additional_info'],
                                    $data['stock_email_view'],
                                    $data['stock_password_view'],
                                    $data['stock_info_view'],
                                    $data['item_label'],
                                );

                                return $data;
                            })
                            ->schema([
                                Placeholder::make('item_label')
                                    ->label('Produk / Varian')
                                    ->columnSpanFull()
                                    ->content(function ($record): string|Htmlable|null {
                                        if (! $record) {
                                            return null;
                                        }
                                        $product = $record->product?->name ?? '—';
                                        $variant = $record->variant?->name ?? '—';
                                        $qty = max(1, (int) $record->qty);
                                        $unit = number_format((int) $record->unit_price, 0, ',', '.');
                                        $line = number_format($record->lineTotal(), 0, ',', '.');
                                        $statusBadge = $record->stock_id
                                            ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">Akun Terkirim</span>'
                                            : '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">Belum Dikirim</span>';

                                        return new HtmlString(
                                            '<div class="text-sm font-bold text-slate-900">'.e($product).' — '.e($variant).$statusBadge.'</div>'.
                                            '<div class="text-xs text-slate-500 mt-1">Qty: '.$qty.' × Rp '.$unit.' = Rp '.$line.'</div>'
                                        );
                                    }),

                                // ===== Display kredensial yang sudah ter-assign =====
                                TextInput::make('stock_email_view')
                                    ->label('Email / No HP Akun (terkirim)')
                                    ->visible(fn ($record) => $record?->stock_id)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn (TextInput $c, $record) => $c->state(optional($record?->stock)->email_or_phone)),
                                TextInput::make('stock_password_view')
                                    ->label('Password Akun (terkirim)')
                                    ->visible(fn ($record) => $record?->stock_id)
                                    ->disabled()
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn (TextInput $c, $record) => $c->state(optional($record?->stock)->password)),
                                Textarea::make('stock_info_view')
                                    ->label('Informasi Tambahan (terkirim)')
                                    ->visible(fn ($record) => $record?->stock_id)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->afterStateHydrated(fn (Textarea $c, $record) => $c->state(optional($record?->stock)->additional_info)),

                                // ===== Input form untuk yang BELUM ter-assign =====
                                // Field-field ini punya nama plain (manual_*), saat Save
                                // di-intercept di EditOrder::mutateFormDataBeforeSave +
                                // afterSave (kita commit di handleRecordUpdate).
                                TextInput::make('manual_email_or_phone')
                                    ->label('Email / No HP Akun untuk dikirim')
                                    ->visible(fn ($record) => $record && ! $record->stock_id)
                                    ->maxLength(255)
                                    ->dehydrated(true)
                                    ->placeholder('Isi kredensial akun yang akan dikirim ke pembeli'),
                                TextInput::make('manual_password')
                                    ->label('Password Akun')
                                    ->visible(fn ($record) => $record && ! $record->stock_id)
                                    ->maxLength(255)
                                    ->dehydrated(true),
                                Textarea::make('manual_additional_info')
                                    ->label('Informasi Tambahan (opsional)')
                                    ->visible(fn ($record) => $record && ! $record->stock_id)
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->dehydrated(true)
                                    ->helperText('Mis. PIN, profil yang dipakai, link aplikasi, instruksi khusus, dll.'),
                            ])
                            ->itemLabel(fn (array $state): ?string => null),
                    ]),

                // Section "Akun Terkirim" — LEGACY untuk order single-stock yang dibuat
                // sebelum migrasi multi-item (Order.stock_id terisi tapi tidak ada
                // OrderItem). Tetap tampil supaya admin bisa lihat order lama.
                Section::make('Akun Terkirim')
                    ->description('Kredensial yang sudah ter-assign ke order ini (legacy single-stock).')
                    ->icon('heroicon-o-key')
                    ->visible(fn ($record) => $record && $record->stock_id && ! $record->items()->exists())
                    ->columns(2)
                    ->schema([
                        TextInput::make('stock_email')
                            ->label('Email / No HP Akun')
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (TextInput $component, $record) => $component->state(optional($record?->stock)->email_or_phone)),
                        TextInput::make('stock_password')
                            ->label('Password Akun')
                            ->disabled()
                            ->dehydrated(false)
                            ->revealable()
                            ->password()
                            ->afterStateHydrated(fn (TextInput $component, $record) => $component->state(optional($record?->stock)->password)),
                        Textarea::make('stock_additional_info')
                            ->label('Informasi Tambahan')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->columnSpanFull()
                            ->afterStateHydrated(fn (Textarea $component, $record) => $component->state(optional($record?->stock)->additional_info)),
                        Placeholder::make('stock_meta')
                            ->label('Tanggal Dikirim')
                            ->columnSpanFull()
                            ->content(fn ($record): string|Htmlable|null => $record?->stock?->sold_at
                                ? $record->stock->sold_at->translatedFormat('d M Y H:i').' WIB · stock #'.$record->stock_id
                                : null),
                    ]),

                // Section LEGACY single-item — hanya untuk order lama tanpa OrderItem.
                Section::make('Kirim Akun Manual ke Pembeli')
                    ->description('Order lama (single-item) yang PAID tapi belum ada akun ter-assign. Klik "Kirim Akun Manual" di header.')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn ($record) => $record && $record->isPaid() && ! $record->stock_id && ! $record->items()->exists())
                    ->columns(2)
                    ->schema([
                        Placeholder::make('manual_delivery_hint')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                '<div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">'.
                                'Klik tombol <strong>"Kirim Akun Manual"</strong> di pojok kanan atas halaman ini untuk membuka form input kredensial.'.
                                '</div>'
                            )),
                    ]),
            ]);
    }

    /**
     * Counter shared antara hook Repeater (per-item) dan EditOrder::afterSave —
     * untuk tahu apakah perlu kirim WA dengan kredensial baru.
     */
    public static int $manualDeliveriesAssignedThisRequest = 0;

    /**
     * Hook PER-ITEM dari Repeater::mutateRelationshipDataBeforeSaveUsing.
     * Terima $data row + $record OrderItem. Kalau admin isi manual_email/password
     * dan item belum punya stock_id, bikin Stock + assign sekarang juga (sebelum
     * Filament fill+save). Counter increment supaya afterSave tahu kirim WA.
     */
    public static function assignManualDeliveryToItem(array $data, $record): void
    {
        if (! $record || ! is_object($record)) {
            return;
        }
        if ($record->stock_id) {
            return;
        }

        $email = trim((string) ($data['manual_email_or_phone'] ?? ''));
        $password = trim((string) ($data['manual_password'] ?? ''));
        if ($email === '' || $password === '') {
            return;
        }

        DB::transaction(function () use ($record, $data, $email, $password) {
            $stock = Stock::create([
                'product_variant_id' => $record->product_variant_id,
                'email_or_phone' => $email,
                'password' => $password,
                'additional_info' => $data['manual_additional_info'] ?? null,
                'is_sold' => true,
                'sold_at' => now(),
            ]);

            $record->stock_id = $stock->id;
            $record->fulfilled_at = now();
            $record->save();
        });

        self::$manualDeliveriesAssignedThisRequest++;
    }

    /**
     * Helper LEGACY: dipanggil saat refactor lama parsing $data['items'].
     * Filament Repeater::relationship() men-set dehydrated(false) sehingga
     * 'items' TIDAK masuk ke $data — method ini effectively no-op untuk
     * multi-item baru. Path resmi: assignManualDeliveryToItem (per-item).
     */
    public static function commitManualDeliveries(Order $order, array $itemsState): int
    {
        $assigned = 0;
        foreach ($itemsState as $itemKey => $itemData) {
            // Filament Repeater dengan relationship() pakai key "record-{id}".
            // Untuk item baru pakai UUID. Kita hanya peduli existing items.
            $orderItemId = null;
            if (is_string($itemKey) && str_starts_with($itemKey, 'record-')) {
                $orderItemId = (int) substr($itemKey, 7);
            } elseif (is_numeric($itemKey)) {
                $orderItemId = (int) $itemKey;
            } elseif (isset($itemData['id']) && is_numeric($itemData['id'])) {
                $orderItemId = (int) $itemData['id'];
            }
            if (! $orderItemId) {
                continue;
            }

            $email = trim((string) ($itemData['manual_email_or_phone'] ?? ''));
            $password = trim((string) ($itemData['manual_password'] ?? ''));
            if ($email === '' || $password === '') {
                continue;
            }

            $item = $order->items()->find($orderItemId);
            if (! $item || $item->stock_id) {
                continue;
            }

            DB::transaction(function () use ($item, $itemData, $email, $password) {
                $stock = Stock::create([
                    'product_variant_id' => $item->product_variant_id,
                    'email_or_phone' => $email,
                    'password' => $password,
                    'additional_info' => $itemData['manual_additional_info'] ?? null,
                    'is_sold' => true,
                    'sold_at' => now(),
                ]);

                $item->stock_id = $stock->id;
                $item->fulfilled_at = now();
                $item->save();
            });

            $assigned++;
        }

        return $assigned;
    }
}
