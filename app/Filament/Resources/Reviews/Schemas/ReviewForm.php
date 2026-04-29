<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('reviewer_name')->label('Nama Reviewer')->required()->maxLength(120),
                TextInput::make('reviewer_email')->label('Email Reviewer')->email(),
                Select::make('rating')
                    ->label('Rating')
                    ->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★'])
                    ->required(),
                Textarea::make('comment')->label('Komentar')->rows(4)->maxLength(1000)->columnSpanFull(),
                Toggle::make('is_approved')
                    ->label('Disetujui & Tampil di Frontend')
                    ->default(false),
            ]);
    }
}
