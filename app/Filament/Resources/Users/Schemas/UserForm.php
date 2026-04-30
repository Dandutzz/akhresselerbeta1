<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil User')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Nomor HP / WhatsApp')
                            ->tel(),
                        TextInput::make('password')
                            ->label('Reset Password (kosongkan = tetap)')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->minLength(6),
                    ]),

                Section::make('Role & Status')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_admin')
                            ->label('Admin (akses panel)')
                            ->helperText('Hati-hati: admin bisa kelola seluruh sistem.'),
                        Toggle::make('is_banned')
                            ->label('Banned')
                            ->helperText('User banned tidak bisa login & checkout.'),
                        TextInput::make('balance')
                            ->label('Saldo Wallet')
                            ->prefix('Rp')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Edit saldo lewat aksi "Top-up" / "Kurangi" di list user.'),
                    ]),
            ]);
    }
}
