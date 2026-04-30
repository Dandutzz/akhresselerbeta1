<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Site Settings';

    protected static ?string $title = 'Site Settings';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.manage-site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identitas Toko')
                    ->columns(2)
                    ->schema([
                        TextInput::make('store_name')->label('Nama Toko')->required(),
                        TextInput::make('tagline')->label('Tagline'),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('site')
                            ->visibility('public')
                            ->columnSpanFull(),
                        ColorPicker::make('brand_color')->label('Warna Brand Utama'),
                        ColorPicker::make('accent_color')->label('Warna Aksen'),
                    ]),

                Section::make('Hero (Halaman Depan)')
                    ->columns(1)
                    ->schema([
                        TextInput::make('hero_title')->label('Judul Hero'),
                        Textarea::make('hero_subtitle')->label('Subjudul Hero')->rows(2),
                    ]),

                Section::make('Live Chat & Kontak')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_email')
                            ->label('Email Kontak')
                            ->email(),
                        TextInput::make('wa_number')
                            ->label('Nomor WhatsApp (format internasional, tanpa +)')
                            ->placeholder('6281234567890')
                            ->helperText('Tombol live chat akan membuka wa.me/<nomor>.')
                            ->maxLength(32),
                        Textarea::make('wa_default_message')
                            ->label('Pesan Otomatis WhatsApp')
                            ->placeholder('Halo admin, saya butuh bantuan tentang...')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('support_hours')
                            ->label('Jam Operasional Support')
                            ->placeholder('Senin–Minggu, 09.00 – 22.00 WIB')
                            ->columnSpanFull(),
                    ]),

                Section::make('Media Sosial')
                    ->columns(2)
                    ->schema([
                        TextInput::make('instagram_url')->label('Instagram URL')->url(),
                        TextInput::make('tiktok_url')->label('TikTok URL')->url(),
                        TextInput::make('telegram_url')->label('Telegram URL')->url(),
                        TextInput::make('facebook_url')->label('Facebook URL')->url(),
                        TextInput::make('whatsapp_channel_url')->label('WhatsApp Channel URL')->url()->columnSpanFull(),
                    ]),

                Section::make('Fonnte WhatsApp Gateway (Auto-Kirim Akun)')
                    ->description('Auto-kirim kredensial akun ke WhatsApp customer setelah pembayaran PAID. Daftar di https://fonnte.com untuk dapat API token.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('fonnte_auto_send_credentials')
                            ->label('Aktifkan auto-kirim WA')
                            ->helperText('Kalau ON, kredensial akan otomatis dikirim ke customer.customer_phone via Fonnte setelah order PAID & stok ter-assign.')
                            ->columnSpanFull(),
                        TextInput::make('fonnte_api_key')
                            ->label('Fonnte API Token')
                            ->password()
                            ->revealable()
                            ->placeholder('Token dari fonnte.com → Device → API')
                            ->columnSpanFull(),
                        TextInput::make('fonnte_admin_number')
                            ->label('Nomor Admin (notifikasi order baru)')
                            ->placeholder('085211923457 atau 6285211923457')
                            ->helperText('Setiap order PAID, admin akan dapat notif singkat di WA. Kosongkan kalau tidak perlu.'),
                        TextInput::make('fonnte_webhook_secret')
                            ->label('Webhook Secret (opsional)')
                            ->password()
                            ->revealable()
                            ->placeholder('Random string panjang')
                            ->helperText('Set kalau kamu mau aktifkan inbound webhook. Tambahkan header `X-Fonnte-Token` dengan nilai ini di setting Fonnte → Webhook.'),
                        Textarea::make('fonnte_credentials_template')
                            ->label('Template Pesan ke Customer (opsional)')
                            ->rows(8)
                            ->placeholder('Kosongkan untuk pakai template default. Placeholder: {{order_code}} {{product}} {{variant}} {{email}} {{password}} {{additional_info}}')
                            ->helperText('Setiap placeholder akan diganti dengan data order saat pesan dikirim.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pakasir Payment Gateway')
                    ->description('Slug project & API key Pakasir. Daftar di https://pakasir.com/p/docs untuk dapat kredensial. Akan override ENV (PAKASIR_PROJECT/PAKASIR_API_KEY) kalau diisi.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pakasir_project')
                            ->label('Project Slug')
                            ->placeholder('akhpremium')
                            ->helperText('Slug project di dashboard Pakasir (bagian setelah /p/ di URL).')
                            ->maxLength(64),
                        TextInput::make('pakasir_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('Paste API Key dari Pakasir → Project → API Key')
                            ->helperText('Disimpan terenkripsi (AES-256-CBC) di DB.'),
                        Toggle::make('pakasir_qris_only')
                            ->label('Batasi metode pembayaran ke QRIS saja')
                            ->helperText('Kalau ON, halaman pembayaran Pakasir hanya menampilkan QRIS. Kalau OFF, semua metode (QRIS, VA, Wallet) ditampilkan.')
                            ->default(false),
                        TextInput::make('pakasir_order_expiry_minutes')
                            ->label('Masa Berlaku Order (menit)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(1440)
                            ->default(60)
                            ->helperText('Order yang belum dibayar dalam waktu ini akan otomatis di-expire.'),
                        TextInput::make('pakasir_base_url')
                            ->label('Base URL (opsional)')
                            ->placeholder('https://app.pakasir.com')
                            ->helperText('Kosongkan untuk pakai default. Hanya isi kalau Pakasir kasih URL khusus / sandbox.')
                            ->url()
                            ->columnSpanFull(),
                    ]),

                Section::make('Sosial Proof — Fake Terjual')
                    ->description('Jumlah terjual palsu dijumlahkan dengan terjual asli. Atur per produk di Resource Produk → Fake Sold Count. Hanya naikkan secara konsisten — jangan diturunkan.')
                    ->columns(1)
                    ->schema([
                        Toggle::make('fake_sold_enabled')
                            ->label('Aktifkan Fake Terjual')
                            ->helperText('Kalau ON, jumlah terjual yang ditampilkan ke publik = sold_count + fake_sold_count produk.')
                            ->default(false),
                    ]),

                Section::make('Notifikasi Mengambang (Floating Notif)')
                    ->description('Toast kecil di pojok kiri bawah yang menampilkan "User X baru saja membeli produk Y". Dapat berasal dari order asli ATAU order palsu. Notif palsu hanya muncul untuk produk yang stoknya masih tersedia agar tidak menipu user.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('floating_notif_enabled')
                            ->label('Aktifkan Notif Mengambang')
                            ->columnSpanFull()
                            ->default(false),
                        Toggle::make('floating_notif_use_real')
                            ->label('Sertakan order ASLI (24 jam terakhir)')
                            ->default(true),
                        Toggle::make('floating_notif_use_fake')
                            ->label('Sertakan order PALSU (sinkron stok)')
                            ->default(true),
                        TextInput::make('floating_notif_interval_min')
                            ->label('Interval min (detik)')
                            ->numeric()
                            ->minValue(5)
                            ->default(20),
                        TextInput::make('floating_notif_interval_max')
                            ->label('Interval max (detik)')
                            ->numeric()
                            ->minValue(10)
                            ->default(60),
                    ]),

                Section::make('Konten Halaman Statis')
                    ->columns(1)
                    ->schema([
                        RichEditor::make('how_to_order_html')->label('Tambahan Cara Pemesanan (opsional)'),
                        RichEditor::make('terms_html')->label('Ketentuan Order'),
                        Textarea::make('footer_about')->label('Tagline Footer')->rows(2),
                    ]),

                Section::make('Custom SEO')
                    ->description('Custom meta tags untuk SEO + sosial media (Open Graph). Kosongkan field untuk pakai default (nama toko + tagline).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('seo_meta_title')
                            ->label('Meta Title')
                            ->maxLength(255)
                            ->placeholder('Akhpremium Store — Akun Premium Legal & Murah')
                            ->helperText('Tag <title> halaman. Idealnya 50–60 karakter.')
                            ->columnSpanFull(),
                        Textarea::make('seo_meta_description')
                            ->label('Meta Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Toko akun premium legal dengan auto-delivery 24 jam. Netflix, Spotify, CapCut, dan lainnya — harga termurah.')
                            ->helperText('Tag <meta name="description">. Idealnya 120–160 karakter.')
                            ->columnSpanFull(),
                        TextInput::make('seo_meta_keywords')
                            ->label('Meta Keywords')
                            ->maxLength(1000)
                            ->placeholder('akun premium, netflix murah, spotify premium, capcut pro')
                            ->helperText('Pisahkan dengan koma. Modern Google sebagian besar mengabaikan ini, tapi search engine lain masih membaca.')
                            ->columnSpanFull(),
                        FileUpload::make('seo_og_image_path')
                            ->label('Open Graph Image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('site/seo')
                            ->visibility('public')
                            ->helperText('Gambar 1200x630 px untuk preview saat link di-share di sosial media (FB, WA, Telegram, X). Kosongkan = pakai logo.')
                            ->columnSpanFull(),
                        TextInput::make('seo_canonical_url')
                            ->label('Canonical URL')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://akhpremium.test')
                            ->helperText('URL utama website. Penting untuk SEO kalau site bisa diakses dari banyak domain.')
                            ->columnSpanFull(),
                        TextInput::make('seo_robots')
                            ->label('Meta Robots')
                            ->maxLength(64)
                            ->placeholder('index,follow')
                            ->helperText('Atur bagaimana search engine meng-crawl/index website. Kosongkan = default index,follow.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = SiteSetting::current();
        $settings->fill($data)->save();

        Notification::make()
            ->title('Pengaturan disimpan')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->submit('save'),
        ];
    }
}
