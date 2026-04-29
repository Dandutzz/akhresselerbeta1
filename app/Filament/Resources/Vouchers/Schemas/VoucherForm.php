<?php

namespace App\Filament\Resources\Vouchers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VoucherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode Voucher')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(64)
                    ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                    ->helperText('Contoh: HEMAT10. Tidak case-sensitive (otomatis di-uppercase).'),
                TextInput::make('name')
                    ->label('Nama / Deskripsi Internal')
                    ->maxLength(120),
                Select::make('type')
                    ->label('Tipe Diskon')
                    ->options([
                        'percent' => 'Persen (%)',
                        'fixed' => 'Nominal (Rp)',
                    ])
                    ->required()
                    ->default('percent')
                    ->live(),
                TextInput::make('value')
                    ->label('Nilai Diskon')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('Untuk persen: 0–100. Untuk nominal: rupiah.'),
                TextInput::make('min_purchase')
                    ->label('Min. Pembelian (Rp)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('Rp'),
                TextInput::make('max_discount')
                    ->label('Maks. Diskon (Rp, 0 = tidak ada batas)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('Rp'),
                TextInput::make('usage_limit')
                    ->label('Limit Pemakaian (0 = unlimited)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('used_count')
                    ->label('Sudah Dipakai')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                DateTimePicker::make('starts_at')
                    ->label('Mulai Berlaku')
                    ->seconds(false),
                DateTimePicker::make('expires_at')
                    ->label('Kadaluarsa')
                    ->seconds(false),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
