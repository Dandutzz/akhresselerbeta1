<?php

namespace App\Filament\Reseller\Pages;

use App\Models\ResellerSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Form pengaturan per-reseller — branding, kredensial Pakasir/Fonnte/Telegram bot.
 * Mirror struktur `ManageSiteSettings` tapi scope-nya ResellerSetting milik
 * user yang sedang login.
 */
class ManageResellerSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan Toko';

    protected static ?string $title = 'Pengaturan Toko';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.reseller.pages.manage-reseller-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = $this->settings();
        $this->form->fill($settings->toArray());
    }

    protected function settings(): ResellerSetting
    {
        $user = Auth::user();

        return ResellerSetting::firstOrCreate(['user_id' => $user->getKey()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identitas Toko')
                    ->description('Branding storefront milik Anda. Akan dipakai di halaman invoice & bot Telegram.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('store_name')->label('Nama Toko'),
                        TextInput::make('tagline')->label('Tagline'),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('reseller-logos')
                            ->visibility('public')
                            ->columnSpanFull(),
                        ColorPicker::make('brand_color')->label('Warna Brand'),
                        TextInput::make('contact_email')->label('Email Kontak')->email(),
                        TextInput::make('wa_number')
                            ->label('WA Number (62xxx, tanpa +)')
                            ->maxLength(32)
                            ->columnSpanFull(),
                    ]),

                Section::make('Telegram Bot')
                    ->description('Token bot Telegram Anda dari @BotFather. Webhook akan otomatis di-set saat Anda klik tombol "Aktifkan Bot" di bawah.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('tg_bot_token')
                            ->label('Bot Token')
                            ->password()
                            ->revealable()
                            ->placeholder('123456789:ABC...')
                            ->columnSpanFull(),
                        TextInput::make('tg_bot_username')
                            ->label('Bot Username (cache, opsional)')
                            ->prefix('@'),
                        TextInput::make('tg_admin_chat_id')
                            ->label('Admin Chat ID (untuk notif)')
                            ->numeric(false),
                        TextInput::make('tg_notif_bot_token')
                            ->label('Bot Notif Token (opsional, terpisah)')
                            ->password()
                            ->revealable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Pakasir Payment Gateway')
                    ->description('Kredensial Pakasir Anda sendiri. Dapatkan di dashboard Pakasir.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pakasir_project')->label('Project Slug'),
                        TextInput::make('pakasir_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable(),
                        Toggle::make('pakasir_qris_only')
                            ->label('Hanya QRIS (paksa metode QRIS)'),
                        TextInput::make('pakasir_order_expiry_minutes')
                            ->label('Order Expiry (menit)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(1440)
                            ->default(60),
                    ]),

                Section::make('Fonnte WhatsApp Gateway')
                    ->description('Auto-kirim kredensial via WA setelah order PAID. Daftar di https://fonnte.com.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('fonnte_auto_send_credentials')
                            ->label('Aktifkan auto-kirim WA')
                            ->columnSpanFull(),
                        TextInput::make('fonnte_api_key')
                            ->label('Fonnte API Token')
                            ->password()
                            ->revealable(),
                        TextInput::make('fonnte_admin_number')
                            ->label('Admin WA Number (62xxx)'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Field yang TIDAK boleh diubah lewat form ini (anti privilege escalation).
        unset($data['user_id'], $data['is_active'], $data['activated_at'], $data['tg_webhook_secret']);

        $this->settings()->update($data);

        Notification::make()
            ->title('Pengaturan tersimpan')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan')
                ->submit('save'),
        ];
    }
}
