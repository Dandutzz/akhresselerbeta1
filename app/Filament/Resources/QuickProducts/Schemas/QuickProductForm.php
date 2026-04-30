<?php

namespace App\Filament\Resources\QuickProducts\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Form ringkas Quick Tools Product. 1 halaman — semua input untuk produk +
 * varian + stok akun ada di sini. Sinkron pakai model Product yang sama.
 */
class QuickProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Info Produk')
                    ->columns(['default' => 1, 'md' => 6])
                    ->schema([
                        FileUpload::make('image')
                            ->label('Foto')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('produk-images')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->columnSpan(['default' => 6, 'md' => 2]),

                        TextInput::make('name')
                            ->label('Nama Produk')
                            ->required()
                            ->columnSpan(['default' => 6, 'md' => 4]),

                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(['default' => 6, 'md' => 2]),

                        TextInput::make('short_description')
                            ->label('Deskripsi Singkat')
                            ->maxLength(255)
                            ->columnSpan(['default' => 6, 'md' => 4]),

                        Textarea::make('description')
                            ->label('Deskripsi Lengkap')
                            ->rows(3)
                            ->columnSpan(6),

                        Textarea::make('terms_html')
                            ->label('Syarat & Ketentuan (S&K)')
                            ->helperText('Boleh teks polos atau HTML sederhana. Tampil di halaman detail produk.')
                            ->rows(4)
                            ->columnSpan(6),

                        Toggle::make('is_best_seller')
                            ->label('Best Seller')
                            ->columnSpan(['default' => 3, 'md' => 2]),

                        TextInput::make('fake_sold_count')
                            ->label('Fake Terjual')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->columnSpan(['default' => 3, 'md' => 2]),

                        Toggle::make('is_auto_send')
                            ->label('Default Auto-Delivery')
                            ->helperText('Kalau di varian tidak di-set, ikut sini.')
                            ->default(true)
                            ->columnSpan(['default' => 6, 'md' => 2]),
                    ]),

                Section::make('Varian / Paket + Stok Akun')
                    ->description('Tiap varian: harga, mode kirim (auto/manual), garansi, dan stok akun. Untuk auto-delivery isi stok di kolom "Bulk Stok Akun" — format per baris: email|password atau email|password|info.')
                    ->schema([
                        Repeater::make('variants')
                            ->label('')
                            // Sengaja TIDAK pakai ->relationship('variants').
                            // Page-level handleRecordCreation / handleRecordUpdate
                            // yang mengurus sync varian + parsing bulk stok.
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('name')
                                    ->label('Nama Paket (cth: 1 Bulan)')
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                TextInput::make('price')
                                    ->label('Harga')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                Select::make('is_auto_send')
                                    ->label('Mode Kirim')
                                    ->options([
                                        '1' => 'Stok Otomatis',
                                        '0' => 'Manual (admin input setelah PAID)',
                                        '' => 'Default (ikut produk)',
                                    ])
                                    ->placeholder('Default (ikut produk)')
                                    ->dehydrateStateUsing(fn ($state) => ($state === '' || $state === null) ? null : (bool) $state)
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                TextInput::make('warranty_days')
                                    ->label('Garansi (hari)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(3650)
                                    ->columnSpan(['default' => 6, 'md' => 3]),

                                Select::make('share_type')
                                    ->label('Tipe Akun')
                                    ->options([
                                        'sharing' => 'Sharing',
                                        'private' => 'Private',
                                        'sharing_antilimit' => 'Sharing Antilimit',
                                    ])
                                    ->placeholder('— Tidak Ada —')
                                    ->columnSpan(['default' => 6, 'md' => 3]),

                                Toggle::make('_replace_stock')
                                    ->label('Mode Edit Stok')
                                    ->helperText('Aktif: textarea isi stok yang ada — bisa edit/hapus per baris. Save = sync semua. Stok TERJUAL tetap aman.')
                                    ->default(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        if ($state) {
                                            // Aktifkan: load semua stok BELUM TERJUAL ke textarea
                                            $variantId = $get('id');
                                            if (! $variantId) {
                                                return;
                                            }
                                            $variant = \App\Models\ProductVariant::find($variantId);
                                            if (! $variant) {
                                                return;
                                            }
                                            $lines = $variant->stocks()
                                                ->where('is_sold', false)
                                                ->get()
                                                ->map(function ($s) {
                                                    $base = $s->email_or_phone.'|'.$s->password;
                                                    return $s->additional_info ? $base.'|'.$s->additional_info : $base;
                                                })->implode("\n");
                                            $set('_bulk_stock', $lines);
                                        } else {
                                            // Matikan: kosongkan textarea (kembali ke append mode)
                                            $set('_bulk_stock', '');
                                        }
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 6]),

                                Textarea::make('_bulk_stock')
                                    ->label(fn (callable $get) => $get('_replace_stock')
                                        ? 'Edit Semua Stok (Mode Edit ON)'
                                        : 'Tambah Stok Akun')
                                    ->placeholder("Format per baris:\nemail@example.com|password123\nemail2@example.com|password456|info opsional")
                                    ->helperText(function (callable $get) {
                                        $isEdit = (bool) $get('_replace_stock');
                                        $variantId = $get('id');
                                        $variant = $variantId ? \App\Models\ProductVariant::find($variantId) : null;
                                        $available = $variant ? $variant->stocks()->where('is_sold', false)->count() : 0;
                                        $sold = $variant ? $variant->stocks()->where('is_sold', true)->count() : 0;
                                        $stats = $variant ? " (saat ini: {$available} tersedia, {$sold} terkirim)" : '';

                                        if ($isEdit) {
                                            return 'EDIT MODE'.$stats.'. Edit/hapus baris untuk update stok. Hapus baris = stok itu dihapus. Tambah baris = stok baru. Save sync semua.';
                                        }

                                        return 'TAMBAH MODE'.$stats.'. Baris di textarea ini akan DITAMBAH ke stok yang sudah ada. Aktifkan Mode Edit untuk lihat & ubah stok existing.';
                                    })
                                    ->rows(6)
                                    ->columnSpan(12),
                            ])
                            ->columns(12)
                            ->itemLabel(function (array $state): ?string {
                                $name = $state['name'] ?? null;
                                $price = isset($state['price']) ? (int) $state['price'] : null;
                                if (! $name) {
                                    return 'Varian Baru';
                                }

                                return $price
                                    ? "{$name} — Rp ".number_format($price, 0, ',', '.')
                                    : $name;
                            })
                            ->collapsible()
                            ->reorderable(false)
                            ->createItemButtonLabel('+ Tambah Varian')
                            ->minItems(1)
                            ->defaultItems(1),
                    ]),
            ]);
    }
}
