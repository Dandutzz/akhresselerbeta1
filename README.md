# Akhpremium Store

Toko digital untuk penjualan akun premium (Netflix, CapCut, Spotify, dll) dengan
auto-delivery kredensial setelah pembayaran sukses lewat **Pakasir Payment
Gateway** (QRIS, Virtual Account, E-Wallet).

Stack: Laravel 13 · Filament 5 · PHP 8.3 · Tailwind (CDN) · MySQL 8 / MariaDB 10.6+.

## Fitur

### Sisi pembeli (publik)
- Katalog dinamis dengan filter kategori + pencarian.
- Halaman detail produk dengan multi-varian (durasi/paket).
- Checkout instan tanpa keranjang (langsung "Beli Sekarang").
- Halaman invoice publik (URL via `order_code` random) yang menampilkan
  status pembayaran dan kredensial akun setelah PAID.

### Sisi admin (Filament `/admin`)
- Manajemen kategori, produk, varian, stok kredensial.
- Manajemen pesanan (orders) dengan filter status & kolom dapat di-edit.
- Dashboard ringkasan: pendapatan harian/bulanan, jumlah order, alert stok menipis.
- Audit log untuk aksi penting.

### Security
- Kredensial akun di tabel `stocks` (`email_or_phone`, `password`,
  `additional_info`) **dienkripsi otomatis** dengan `APP_KEY` (AES-256-CBC via
  Laravel `encrypted` cast) — tidak pernah disimpan plain-text di DB.
- `protected $fillable` eksplisit di semua model — bebas dari mass assignment.
- `is_admin` flag + `FilamentUser::canAccessPanel()` → hanya admin yang boleh
  buka `/admin`.
- Security headers global (X-Frame-Options, X-Content-Type-Options, Referrer
  Policy, Permissions Policy, HSTS untuk koneksi HTTPS).
- Webhook Pakasir diverifikasi via Transaction Detail API (Pakasir tidak
  mengirim signature) — webhook idempotent + cek `amount`+`order_id`+`project`.
- Atomic stock assignment dengan `lockForUpdate()` agar tidak ada dua user yang
  dapat akun yang sama secara race-condition.
- Throttle 10 request/menit per IP di endpoint `/checkout`.
- Audit log untuk event penting (`order.created`, `order.paid`,
  `stock.delivered`, `webhook.processed`, dst).

## Setup

Database default sekarang **MySQL**. Pastikan MySQL 8 / MariaDB 10.6+ aktif,
lalu siapkan database & user (sesuaikan password):

```bash
mysql -uroot -p -e "CREATE DATABASE akhpremium CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -p -e "CREATE USER 'akhpremium'@'localhost' IDENTIFIED BY 'ganti_password_kuat';"
mysql -uroot -p -e "GRANT ALL PRIVILEGES ON akhpremium.* TO 'akhpremium'@'localhost'; FLUSH PRIVILEGES;"
```

Lalu install aplikasinya:

```bash
composer install
cp .env.example .env
php artisan key:generate
# isi DB_DATABASE / DB_USERNAME / DB_PASSWORD di .env sesuai kredensial di atas
php artisan migrate --seed
```

Akun admin default dari seeder:

- Email: `admin@akhpremium.test`
- Password: `password`

**Wajib ganti password admin segera setelah deploy production.**

### Konfigurasi Pakasir

Tambahkan di `.env`:

```env
PAKASIR_PROJECT=slug-project-pakasir-kamu
PAKASIR_API_KEY=api-key-dari-dashboard-pakasir
PAKASIR_QRIS_ONLY=false           # true = paksa hanya QRIS
PAKASIR_ORDER_EXPIRY_MINUTES=60
```

Di dashboard Pakasir, set Webhook URL ke:

```
https://domain-kamu.com/webhooks/pakasir
```

## Menjalankan

```bash
php artisan serve
# Atau pakai script "dev" yang menjalankan server + queue + vite + pail bersamaan:
composer dev
```

Buka:

- Frontend toko: http://127.0.0.1:8000/
- Admin Filament: http://127.0.0.1:8000/admin

## Testing

```bash
php artisan test
```

Tes feature mencakup: katalog, validasi & pembuatan order, fulfillment yang
atomik & idempotent, dan pengamanan halaman invoice (kredensial hanya muncul
setelah status `paid`).

## Lint

```bash
./vendor/bin/pint
```

## Roadmap selanjutnya

- Voucher / kode promo (validasi server-side: max usage, expiry, min purchase).
- Flashsale (countdown + quota).
- Email notifikasi order sukses (kredensial dikirim ke email pembeli).
- Filament action: import/export CSV stok.
- 2FA admin (TOTP).
- IP allowlist untuk panel `/admin`.
