<?php

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(150)
                    ->columnSpanFull(),
                Textarea::make('body')
                    ->label('Isi pengumuman')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('cta_label')
                    ->label('Label tombol (opsional)')
                    ->maxLength(50)
                    ->placeholder('Misal: JOIN CHANNEL WA'),
                TextInput::make('cta_url')
                    ->label('URL tombol (opsional)')
                    ->url()
                    ->maxLength(500)
                    ->placeholder('https://...'),
                FileUpload::make('image')
                    ->label('Gambar (opsional)')
                    ->image()
                    ->directory('announcements')
                    ->disk('public')
                    ->columnSpanFull(),
                DateTimePicker::make('published_at')
                    ->label('Mulai tampil pada (kosongkan = langsung)')
                    ->seconds(false),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
