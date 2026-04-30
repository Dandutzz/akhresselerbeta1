<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TelegramBotState;
use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bot menu utama — layout reply keyboard berdasarkan referensi user
 * (Febrian Store-style).
 */
class TelegramBotService
{
    protected string $apiBase;

    /** Bot terpisah untuk notif internal admin. Null kalau tidak dikonfigurasi. */
    protected ?string $notifApiBase = null;

    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        $this->apiBase = "https://api.telegram.org/bot{$token}";

        $notifToken = config('services.telegram.notif_bot_token');
        if (! empty($notifToken)) {
            $this->notifApiBase = "https://api.telegram.org/bot{$notifToken}";
        }
    }

    /* =======================================================================
     * HTTP helpers
     * ======================================================================= */

    public function call(string $method, array $params = []): array
    {
        return $this->callOn($this->apiBase, $method, $params);
    }

    /** Versi `call()` yang pakai base URL tertentu (mis. bot notif terpisah). */
    protected function callOn(string $apiBase, string $method, array $params = []): array
    {
        try {
            $res = Http::asJson()
                ->timeout(15)
                ->post("{$apiBase}/{$method}", $params);

            $data = $res->json() ?? [];

            if (! ($data['ok'] ?? false)) {
                Log::warning('Telegram API error', ['method' => $method, 'response' => $data, 'params' => $params]);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('Telegram API exception', ['method' => $method, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendMessage(string $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $extra));
    }

    public function sendPhoto(string $chatId, string $photoUrl, string $caption = '', array $extra = []): array
    {
        return $this->call('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public function answerCallbackQuery(string $callbackId, string $text = '', bool $showAlert = false): array
    {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    /* =======================================================================
     * Webhook entry — dispatch update ke handler
     * ======================================================================= */

    public function handleUpdate(array $update): void
    {
        try {
            // Callback query (klik inline keyboard)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);

                return;
            }

            // Pesan biasa (text / command / button reply keyboard)
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);

                return;
            }
        } catch (\Throwable $e) {
            Log::error('Telegram handleUpdate exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /* =======================================================================
     * Message handlers
     * ======================================================================= */

    protected function handleMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        if (! $chatId) {
            return;
        }

        $text = trim($message['text'] ?? '');
        $username = $message['from']['username'] ?? null;

        // Update telegram_username kalau user sudah linked
        $user = User::where('telegram_chat_id', $chatId)->first();
        if ($user && $username && $user->telegram_username !== $username) {
            $user->update(['telegram_username' => $username]);
        }

        // Command-based dispatch
        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, $text, $message);

            return;
        }

        if ($text === '/menu' || $text === '🏠 Menu Utama') {
            $this->showMainMenu($chatId);

            return;
        }

        if ($text === '/produk' || $text === '📦 List Produk' || $text === '/list') {
            $this->showProductList($chatId, 1);

            return;
        }

        if ($text === '/history' || $text === '📜 Riwayat Order') {
            $this->showOrderHistory($chatId);

            return;
        }

        if ($text === '/saldo' || $text === '💰 Cek Saldo') {
            $this->showBalance($chatId);

            return;
        }

        if ($text === '/support' || $text === '🆘 Support') {
            $this->showSupport($chatId);

            return;
        }

        if ($text === '/cara_order' || $text === '❓ Cara Order') {
            $this->showHowToOrder($chatId);

            return;
        }

        if ($text === '/keranjang' || $text === '🛒 Keranjang') {
            $this->showCart($chatId);

            return;
        }

        if ($text === '/cancel' || $text === '/batal') {
            TelegramBotState::for($chatId)->reset();
            $this->sendMessage($chatId, 'Sesi dibatalkan. Pilih menu di bawah:', $this->mainMenuKeyboard());

            return;
        }

        if ($text === '/broadcast') {
            $this->handleBroadcastStart($chatId);

            return;
        }

        // State-based handler: kalau admin sedang menyusun broadcast,
        // semua pesan teks berikutnya jadi konten broadcast.
        $state = TelegramBotState::for($chatId);
        if ($state->state === TelegramBotState::STATE_AWAITING_BROADCAST && $this->isAdminChat($chatId)) {
            $this->handleBroadcastDraft($chatId, $text, $state);

            return;
        }

        // Default: balas dengan main menu
        $this->sendMessage($chatId, 'Hai! Aku gak ngerti perintah <i>'.htmlspecialchars($text).'</i>. Pilih dari menu di bawah ya.', $this->mainMenuKeyboard());
    }

    /* =======================================================================
     * /start handler — auto-link akun via token
     * ======================================================================= */

    protected function handleStart(string $chatId, string $text, array $message): void
    {
        // /start <TOKEN> → coba linking
        $parts = explode(' ', $text, 2);
        $token = isset($parts[1]) ? strtoupper(trim($parts[1])) : null;

        $existing = User::where('telegram_chat_id', $chatId)->first();

        if ($token) {
            $linkToken = TelegramLinkToken::where('token', $token)->first();
            if (! $linkToken || ! $linkToken->isValid()) {
                $this->sendMessage($chatId, "❌ Token tidak valid atau sudah kedaluwarsa.\n\nSilakan generate ulang di /akun/telegram.");

                return;
            }

            // Pastikan token belum dipakai di akun Telegram lain
            $other = User::where('telegram_chat_id', $chatId)->first();
            if ($other && $other->id !== $linkToken->user_id) {
                $this->sendMessage($chatId, '⚠ Akun Telegram ini sudah ter-link ke user lain. Hubungi support kalau butuh bantuan.');

                return;
            }

            $user = $linkToken->user;
            $user->update([
                'telegram_chat_id' => $chatId,
                'telegram_username' => $message['from']['username'] ?? null,
            ]);
            $linkToken->update(['used_at' => now()]);

            $this->sendMessage(
                $chatId,
                "✅ <b>Akun berhasil di-link!</b>\n\nHalo, <b>".htmlspecialchars($user->name)."</b> 👋\n\nKamu sekarang bisa order langsung lewat bot ini.",
                $this->mainMenuKeyboard()
            );

            return;
        }

        // /start tanpa token
        if ($existing) {
            $this->sendMessage(
                $chatId,
                '👋 Selamat datang kembali, <b>'.htmlspecialchars($existing->name)."</b>!\n\nPilih menu di bawah untuk mulai belanja:",
                $this->mainMenuKeyboard()
            );
        } else {
            $welcomeText = "🎉 <b>Selamat datang di Akhpremium Store!</b>\n\n"
                ."Toko digital akun premium dengan harga terjangkau & garansi.\n\n"
                ."🔗 <b>Mau order?</b> Hubungkan akun web kamu dulu:\n"
                ."1. Buka website → login → menu <b>Akun → Hubungkan Telegram</b>\n"
                ."2. Klik <b>Generate Token</b>\n"
                ."3. Kirim <code>/start TOKEN</code> ke bot ini\n\n"
                .'Atau langsung browse produk dulu di menu di bawah 👇';

            $this->sendMessage($chatId, $welcomeText, $this->mainMenuKeyboard());
        }
    }

    /* =======================================================================
     * Main menu (reply keyboard)
     * ======================================================================= */

    protected function mainMenuKeyboard(): array
    {
        return [
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => '📦 List Produk'], ['text' => '🛒 Keranjang']],
                    [['text' => '📜 Riwayat Order'], ['text' => '💰 Cek Saldo']],
                    [['text' => '❓ Cara Order'], ['text' => '🆘 Support']],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ]),
        ];
    }

    public function showMainMenu(string $chatId): void
    {
        $this->sendMessage($chatId, '🏠 <b>Menu Utama</b>', $this->mainMenuKeyboard());
    }

    /* =======================================================================
     * /produk — list produk dengan inline keyboard
     * ======================================================================= */

    public function showProductList(string $chatId, int $page = 1, ?int $messageId = null): void
    {
        $perPage = 10;
        $totalProducts = Product::count();
        $totalPages = max(1, (int) ceil($totalProducts / $perPage));
        $page = max(1, min($page, $totalPages));

        $products = Product::query()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->withCount(['variants as available_stocks' => function ($q) {
                $q->join('stocks', 'stocks.product_variant_id', '=', 'product_variants.id')
                    ->where('stocks.is_sold', false);
            }])
            ->get();

        if ($products->isEmpty()) {
            $this->sendMessage($chatId, '⚠ Belum ada produk tersedia saat ini.');

            return;
        }

        $text = "<b>LIST PRODUCT</b>\n\n";
        $rows = [];
        $i = ($page - 1) * $perPage + 1;

        foreach ($products as $product) {
            $count = (int) $product->available_stocks;
            $badge = $count > 0 ? '✅' : '❌';
            $text .= "[<b>{$i}</b>]. ".htmlspecialchars($product->name)
                ." ( {$count} ) {$badge}\n";
            $rows[] = [['text' => "{$i}. ".Str::limit($product->name, 25), 'callback_data' => "product:{$product->id}"]];
            $i++;
        }

        $text .= "\nHal {$page}/{$totalPages}";

        // Pagination row
        $pagination = [];
        if ($page > 1) {
            $pagination[] = ['text' => '« Prev', 'callback_data' => 'plist:'.($page - 1)];
        }
        $pagination[] = ['text' => "{$page}/{$totalPages}", 'callback_data' => 'noop'];
        if ($page < $totalPages) {
            $pagination[] = ['text' => 'Next »', 'callback_data' => 'plist:'.($page + 1)];
        }
        if (count($pagination) > 1) {
            $rows[] = $pagination;
        }
        $rows[] = [['text' => '🏠 Menu Utama', 'callback_data' => 'menu']];

        $extra = [
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ];

        if ($messageId) {
            $this->call('editMessageText', array_merge([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ], $extra));
        } else {
            $this->sendMessage($chatId, $text, $extra);
        }
    }

    /* =======================================================================
     * Product detail — foto + varian
     * ======================================================================= */

    public function showProductDetail(string $chatId, int $productId): void
    {
        $product = Product::with(['variants' => function ($q) {
            $q->withCount(['stocks as available_count' => fn ($qq) => $qq->where('is_sold', false)]);
        }])->find($productId);

        if (! $product) {
            $this->sendMessage($chatId, '⚠ Produk tidak ditemukan.');

            return;
        }

        $totalSold = (int) ($product->sold_count ?? 0) + (int) ($product->fake_sold_count ?? 0);
        $desk = trim((string) ($product->short_description ?? strip_tags((string) $product->description)));
        $desk = $desk !== '' ? Str::limit($desk, 200) : '-';

        $caption = "<b>tambahkan jumlah pembelian:</b>\n";
        $caption .= "╭───────────────\n";
        $caption .= '┊・Produk : '.htmlspecialchars($product->name)."\n";
        $caption .= "┊・Stok Terjual : {$totalSold}\n";
        $caption .= '┊・Desk : '.htmlspecialchars($desk)."\n";
        $caption .= "╰───────────────\n";
        $caption .= "╭───────────────\n";
        $caption .= "┊ Variasi, Harga - (Stok):\n";

        $rows = [];
        foreach ($product->variants as $v) {
            $stock = (int) $v->available_count;
            $priceStr = 'Rp. '.number_format($v->effectivePrice(), 0, ',', '.');
            $caption .= '┊・'.htmlspecialchars($v->name).": {$priceStr} - ({$stock})\n";
            $btnText = Str::limit($v->name, 28).' — Rp'.number_format($v->effectivePrice(), 0, ',', '.');
            if ($stock > 0) {
                $rows[] = [['text' => $btnText, 'callback_data' => "variant:{$v->id}"]];
            } else {
                $rows[] = [['text' => "❌ {$btnText} HABIS", 'callback_data' => 'noop']];
            }
        }
        $caption .= "╰───────────────\n\n";
        $caption .= '<i>Current Date: '.now()->format('h:i:s A').'</i>';

        $rows[] = [
            ['text' => '« List Produk', 'callback_data' => 'plist:1'],
            ['text' => '🛒 Keranjang', 'callback_data' => 'cart'],
        ];

        $extra = [
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ];

        $img = $product->image;
        if ($img && file_exists(public_path('storage/'.$img))) {
            $url = url('storage/'.$img);
            $this->sendPhoto($chatId, $url, $caption, $extra);
        } else {
            $this->sendMessage($chatId, $caption, $extra);
        }
    }

    /* =======================================================================
     * Variant detail dengan qty selector
     * ======================================================================= */

    public function showVariantDetail(string $chatId, int $variantId, ?int $messageId = null): void
    {
        $variant = ProductVariant::with('product')
            ->withCount(['stocks as available_count' => fn ($q) => $q->where('is_sold', false)])
            ->find($variantId);

        if (! $variant) {
            $this->sendMessage($chatId, '⚠ Varian tidak ditemukan.');

            return;
        }

        $stock = (int) $variant->available_count;
        if ($stock < 1) {
            $this->sendMessage($chatId, '❌ Stok varian ini habis.');

            return;
        }

        $state = TelegramBotState::for($chatId);
        $qty = max(1, min($state->getPendingQty($variantId), $stock));
        $state->setPendingQty($variantId, $qty);

        $price = (int) $variant->effectivePrice();
        $totalPrice = $price * $qty;
        $product = $variant->product;
        $desk = trim((string) ($product->short_description ?? strip_tags((string) $product->description)));
        $desk = $desk !== '' ? Str::limit($desk, 200) : '-';
        $kode = (string) ($variant->sku ?? $variant->code ?? ('VAR-'.$variant->id));

        $text = "<b>tambahkan jumlah pembelian:</b>\n";
        $text .= "╭───────────────\n";
        $text .= '┊・Produk : '.htmlspecialchars($product->name)."\n";
        $text .= '┊・Variasi : '.htmlspecialchars($variant->name)."\n";
        $text .= '┊・Kode : '.htmlspecialchars($kode)."\n";
        $text .= "┊・Sisa Produk : {$stock}\n";
        $text .= '┊・Desk : '.htmlspecialchars($desk)."\n";
        $text .= "╰───────────────\n";
        $text .= "╭───────────────\n";
        $text .= "┊・Jumlah : {$qty}\n";
        $text .= '┊・Harga : Rp. '.number_format($price, 0, ',', '.')."\n";
        $text .= '┊・Total Harga : Rp. '.number_format($totalPrice, 0, ',', '.')."\n";
        $text .= '╰───────────────';

        $rows = [
            [
                ['text' => '➖', 'callback_data' => "vqty_dec:{$variantId}"],
                ['text' => "{$qty}", 'callback_data' => 'noop'],
                ['text' => '➕', 'callback_data' => "vqty_inc:{$variantId}"],
            ],
            [['text' => '🛒 Tambah ke Keranjang', 'callback_data' => "vadd:{$variantId}"]],
            [
                ['text' => '« Varian', 'callback_data' => "product:{$product->id}"],
                ['text' => '🛒 Keranjang', 'callback_data' => 'cart'],
            ],
        ];

        $extra = [
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ];

        if ($messageId) {
            $this->call('editMessageText', array_merge([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
            ], $extra));
        } else {
            $this->sendMessage($chatId, $text, $extra);
        }
    }

    /* =======================================================================
     * Add to bot cart
     * ======================================================================= */

    public function addVariantToCart(string $chatId, int $variantId, int $qty = 1): void
    {
        $variant = ProductVariant::with('product')->find($variantId);
        if (! $variant) {
            $this->sendMessage($chatId, '⚠ Varian tidak ditemukan.');

            return;
        }

        $qty = max(1, $qty);
        $availableStock = $variant->stocks()->where('is_sold', false)->count();
        if ($availableStock < 1) {
            $this->sendMessage($chatId, '❌ Stok varian ini habis.');

            return;
        }

        $state = TelegramBotState::for($chatId);
        $cart = $state->getCart();

        $existingIdx = null;
        foreach ($cart as $idx => $item) {
            if ($item['variant_id'] === $variantId) {
                $existingIdx = $idx;
                break;
            }
        }

        if ($existingIdx !== null) {
            $newQty = $cart[$existingIdx]['qty'] + $qty;
            if ($newQty > $availableStock) {
                $this->sendMessage($chatId, "⚠ Stok tidak cukup. Stok tersedia: {$availableStock}, sudah di-cart: {$cart[$existingIdx]['qty']}.");

                return;
            }
            $cart[$existingIdx]['qty'] = $newQty;
        } else {
            if ($qty > $availableStock) {
                $this->sendMessage($chatId, "⚠ Stok tidak cukup. Stok tersedia: {$availableStock}.");

                return;
            }
            $cart[] = [
                'variant_id' => $variantId,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'price' => $variant->effectivePrice(),
                'qty' => $qty,
            ];
        }

        $state->setCart($cart);
        $state->clearPendingQty($variantId);

        $this->showCart($chatId, "✅ Ditambahkan {$qty}× <b>".htmlspecialchars($variant->product->name).' — '.htmlspecialchars($variant->name)."</b>\n\n");
    }

    public function showCart(string $chatId, string $prefix = ''): void
    {
        $state = TelegramBotState::for($chatId);
        $cart = $state->getCart();

        if (empty($cart)) {
            $this->sendMessage($chatId, $prefix."🛒 <b>Keranjang kosong</b>\n\nKlik 📦 List Produk untuk mulai belanja.", $this->mainMenuKeyboard());

            return;
        }

        $text = $prefix."🛒 <b>Keranjang Bot</b>\n\n";
        $total = 0;
        $rows = [];
        foreach ($cart as $idx => $item) {
            $line = $item['price'] * $item['qty'];
            $total += $line;
            $text .= '<b>'.($idx + 1).'.</b> '.htmlspecialchars($item['product_name']).' — '.htmlspecialchars($item['variant_name'])."\n";
            $text .= "   {$item['qty']} × Rp ".number_format($item['price'], 0, ',', '.').' = Rp '.number_format($line, 0, ',', '.')."\n\n";
            $rows[] = [
                ['text' => "➖ {$item['variant_name']}", 'callback_data' => 'cart_dec:'.$idx],
                ['text' => '❌ Hapus', 'callback_data' => 'cart_rm:'.$idx],
                ['text' => '➕', 'callback_data' => 'cart_inc:'.$idx],
            ];
        }
        $text .= '<b>Total: Rp '.number_format($total, 0, ',', '.').'</b>';

        $rows[] = [['text' => '🧹 Kosongkan', 'callback_data' => 'cart_clear']];
        $rows[] = [['text' => '✅ Bayar Sekarang', 'callback_data' => 'checkout']];
        $rows[] = [['text' => '« Lanjut Belanja', 'callback_data' => 'plist:1']];

        $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ]);
    }

    /* =======================================================================
     * Checkout via bot
     * ======================================================================= */

    public function checkoutFromBot(string $chatId): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (! $user) {
            $this->sendMessage(
                $chatId,
                "🔗 <b>Akun belum di-link</b>\n\nUntuk checkout via bot, link akun web kamu dulu:\n\n1. Login di website\n2. Buka /akun/telegram → klik Generate Token\n3. Kirim <code>/start TOKEN</code> ke bot ini",
                $this->mainMenuKeyboard()
            );

            return;
        }

        if ($user->is_banned) {
            $this->sendMessage($chatId, '⚠ Akun kamu di-banned. Hubungi support.');

            return;
        }

        $state = TelegramBotState::for($chatId);
        $cart = $state->getCart();
        if (empty($cart)) {
            $this->sendMessage($chatId, '🛒 Keranjang kosong.', $this->mainMenuKeyboard());

            return;
        }

        // Buat Order pakai email user + phone, expand qty>1 jadi N OrderItem
        try {
            $order = DB::transaction(function () use ($user, $cart) {
                $subtotal = 0;
                $expandedItems = [];

                foreach ($cart as $item) {
                    $variant = ProductVariant::lockForUpdate()->find($item['variant_id']);
                    if (! $variant) {
                        throw new \RuntimeException('Varian tidak ada lagi.');
                    }
                    $availStock = $variant->stocks()->where('is_sold', false)->count();
                    if ($availStock < $item['qty']) {
                        throw new \RuntimeException("Stok '{$item['variant_name']}' tidak cukup ({$availStock} tersedia).");
                    }
                    $price = (int) $variant->effectivePrice();

                    for ($q = 0; $q < $item['qty']; $q++) {
                        $expandedItems[] = [
                            'product_id' => $variant->product_id,
                            'product_variant_id' => $variant->id,
                            'qty' => 1,
                            'unit_price' => $price,
                        ];
                        $subtotal += $price;
                    }
                }

                $order = Order::create([
                    'order_code' => Order::generateOrderCode(),
                    'user_id' => $user->id,
                    'product_id' => $expandedItems[0]['product_id'],
                    'product_variant_id' => $expandedItems[0]['product_variant_id'],
                    'amount' => $subtotal,
                    'total_payment' => $subtotal,
                    'status' => Order::STATUS_PENDING,
                    'source' => Order::SOURCE_TELEGRAM,
                    'customer_email' => $user->email,
                    'customer_phone' => $user->phone,
                    // Bot order tetap punya TTL agar dipungut oleh job auto-expire,
                    // konsisten dengan checkout web/cart.
                    'expired_at' => now()->addMinutes(
                        PakasirService::orderExpiryMinutes()
                    ),
                ]);

                foreach ($expandedItems as $item) {
                    OrderItem::create(array_merge($item, [
                        'order_id' => $order->id,
                    ]));
                }

                return $order;
            });
        } catch (\Throwable $e) {
            $this->sendMessage($chatId, '❌ Gagal checkout: '.$e->getMessage());

            return;
        }

        $state->reset();

        $invoiceUrl = url('/invoice/'.$order->order_code);

        // Coba ambil QRIS string dari Pakasir lalu kirim sebagai gambar QR ke
        // chat. Kalau gagal (Pakasir belum dikonfigurasi / API error), fallback
        // ke text + tombol "Buka Invoice" agar flow tetap jalan.
        $qris = null;
        try {
            $pakasir = app(PakasirService::class);
            if ($pakasir->isConfigured()) {
                $qris = $pakasir->createQrisTransaction($order->fresh());
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram bot: gagal fetch QRIS', [
                'order_code' => $order->order_code,
                'message' => $e->getMessage(),
            ]);
        }

        $rows = [
            [['text' => '🔗 Buka Invoice', 'url' => $invoiceUrl]],
            [['text' => '🏠 Menu Utama', 'callback_data' => 'menu']],
        ];

        if (! empty($qris['payment_number'])) {
            $caption = "✅ <b>Scan QRIS untuk bayar</b>\n\n"
                ."🆔 Kode: <code>{$order->order_code}</code>\n"
                .'💵 Total: <b>Rp '.number_format($order->total_payment, 0, ',', '.')."</b>\n\n"
                .'Scan QR di atas pakai e-wallet / m-banking favorit kamu (GoPay, OVO, Dana, BCA, dll). '
                .'Setelah pembayaran berhasil, akun premium akan otomatis dikirim ke chat ini ✨';

            // Render QR via public QR-image service (api.qrserver.com) supaya
            // bisa langsung dikirim sebagai foto Telegram tanpa dep server-side.
            // QRIS payload (EMVCo) bukan data sensitif — sama dengan QR yang
            // ditampilkan di halaman Pakasir.
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&margin=10&data='
                .rawurlencode($qris['payment_number']);

            $resp = $this->sendPhoto($chatId, $qrUrl, $caption, [
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $rows]),
            ]);

            // Simpan message_id biar bisa di-delete saat order paid (rapi).
            $msgId = $resp['result']['message_id'] ?? null;
            if ($msgId) {
                $order->forceFill(['telegram_qr_message_id' => (int) $msgId])->save();
            }
        } else {
            $text = "✅ <b>Order berhasil dibuat!</b>\n\n"
                ."🆔 Kode: <code>{$order->order_code}</code>\n"
                .'💵 Total: <b>Rp '.number_format($order->amount, 0, ',', '.')."</b>\n\n"
                ."🔗 <b>Buka link untuk bayar:</b>\n{$invoiceUrl}\n\n"
                .'Setelah pembayaran berhasil, akun premium akan otomatis dikirim ke chat ini ✨';

            $this->sendMessage($chatId, $text, [
                'reply_markup' => json_encode(['inline_keyboard' => $rows]),
            ]);
        }

        $this->notifyAdminOrderCreated($order, 'Telegram Bot');
    }

    /* =======================================================================
     * /history /saldo /support /cara_order
     * ======================================================================= */

    public function showOrderHistory(string $chatId): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (! $user) {
            $this->sendMessage($chatId, '🔗 Akun belum di-link. Kirim /start TOKEN dari /akun/telegram.');

            return;
        }

        $orders = $user->orders()->orderByDesc('id')->limit(5)->get();
        if ($orders->isEmpty()) {
            $this->sendMessage($chatId, '📜 Belum ada order.');

            return;
        }

        $text = "📜 <b>5 Order Terakhir</b>\n\n";
        foreach ($orders as $o) {
            $statusEmoji = match ($o->status) {
                'paid' => '✅',
                'pending' => '⏳',
                'failed' => '❌',
                'expired' => '⏰',
                default => '🔘',
            };
            $text .= "{$statusEmoji} <code>{$o->order_code}</code> — Rp ".number_format($o->amount, 0, ',', '.')."\n";
            $text .= "   {$o->status} · ".$o->created_at->diffForHumans()."\n";
            $text .= '   🔗 '.url('/invoice/'.$o->order_code)."\n\n";
        }

        $this->sendMessage($chatId, $text);
    }

    public function showBalance(string $chatId): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (! $user) {
            $this->sendMessage($chatId, '🔗 Akun belum di-link.');

            return;
        }

        $text = "💰 <b>Saldo Dompet</b>\n\n"
            .'Saldo aktif: <b>Rp '.number_format($user->balance, 0, ',', '.')."</b>\n\n"
            .'Hubungi admin untuk top-up saldo.';
        $this->sendMessage($chatId, $text);
    }

    public function showSupport(string $chatId): void
    {
        $waNumber = config('app.support_wa_number', '6281234567890');
        $text = "🆘 <b>Support</b>\n\nButuh bantuan? Hubungi admin:\n\n"
            ."📱 WhatsApp: +{$waNumber}\n"
            .'💬 Telegram: @'.config('services.telegram.bot_username');

        $rows = [[['text' => '💬 Chat WA', 'url' => "https://wa.me/{$waNumber}"]]];

        $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ]);
    }

    public function showHowToOrder(string $chatId): void
    {
        $text = "❓ <b>Cara Order via Bot</b>\n\n"
            ."1. Klik <b>📦 List Produk</b>\n"
            ."2. Pilih produk yang kamu mau\n"
            ."3. Pilih varian (paket) → otomatis masuk keranjang\n"
            ."4. Klik <b>🛒 Keranjang</b> → atur qty\n"
            ."5. Klik <b>✅ Bayar Sekarang</b>\n"
            ."6. Bot kasih link invoice — buka & bayar\n"
            ."7. Setelah pembayaran sukses, akun otomatis dikirim ke chat ini\n\n"
            ."<b>📌 Wajib link akun web dulu:</b>\n"
            .'Login di website → /akun/telegram → Generate Token → kirim ke bot dengan format: <code>/start TOKEN</code>';
        $this->sendMessage($chatId, $text);
    }

    /* =======================================================================
     * Callback query handler (klik inline button)
     * ======================================================================= */

    protected function handleCallbackQuery(array $cb): void
    {
        $chatId = (string) ($cb['message']['chat']['id'] ?? '');
        $messageId = $cb['message']['message_id'] ?? null;
        $cbId = $cb['id'];
        $data = $cb['data'] ?? '';

        if (! $chatId || ! $data) {
            $this->answerCallbackQuery($cbId);

            return;
        }

        // Quick ack supaya gak loading di tombol
        $this->answerCallbackQuery($cbId);

        if ($data === 'noop') {
            return;
        }
        if ($data === 'menu') {
            $this->showMainMenu($chatId);

            return;
        }
        if ($data === 'cart') {
            $this->showCart($chatId);

            return;
        }
        if ($data === 'cart_clear') {
            TelegramBotState::for($chatId)->setCart([]);
            $this->sendMessage($chatId, '🧹 Keranjang dikosongkan.');

            return;
        }
        if ($data === 'checkout') {
            $this->checkoutFromBot($chatId);

            return;
        }
        if (str_starts_with($data, 'plist:')) {
            $page = (int) substr($data, 6);
            $this->showProductList($chatId, $page, $messageId);

            return;
        }
        if (str_starts_with($data, 'product:')) {
            $this->showProductDetail($chatId, (int) substr($data, 8));

            return;
        }
        if (str_starts_with($data, 'variant:')) {
            $this->showVariantDetail($chatId, (int) substr($data, 8));

            return;
        }
        if (str_starts_with($data, 'vqty_inc:') || str_starts_with($data, 'vqty_dec:')) {
            [$action, $vidStr] = explode(':', $data, 2);
            $variantId = (int) $vidStr;
            $state = TelegramBotState::for($chatId);
            $current = $state->getPendingQty($variantId);
            $delta = $action === 'vqty_inc' ? 1 : -1;
            $next = max(1, $current + $delta);
            $state->setPendingQty($variantId, $next);
            $this->showVariantDetail($chatId, $variantId, $messageId);

            return;
        }
        if (str_starts_with($data, 'vadd:')) {
            $variantId = (int) substr($data, 5);
            $state = TelegramBotState::for($chatId);
            $qty = $state->getPendingQty($variantId);
            $this->addVariantToCart($chatId, $variantId, $qty);

            return;
        }
        if (str_starts_with($data, 'cart_inc:') || str_starts_with($data, 'cart_dec:') || str_starts_with($data, 'cart_rm:')) {
            $this->cartItemAction($chatId, $data);

            return;
        }
        if ($data === 'bcast_confirm' || $data === 'bcast_cancel') {
            $this->handleBroadcastConfirm($chatId, $data === 'bcast_confirm');

            return;
        }
    }

    protected function cartItemAction(string $chatId, string $data): void
    {
        $state = TelegramBotState::for($chatId);
        $cart = $state->getCart();
        [$action, $idx] = explode(':', $data, 2);
        $idx = (int) $idx;

        if (! isset($cart[$idx])) {
            $this->sendMessage($chatId, '⚠ Item tidak ditemukan.');

            return;
        }

        if ($action === 'cart_rm') {
            unset($cart[$idx]);
            $cart = array_values($cart);
        } elseif ($action === 'cart_inc') {
            $variant = ProductVariant::find($cart[$idx]['variant_id']);
            $avail = $variant ? $variant->stocks()->where('is_sold', false)->count() : 0;
            if ($cart[$idx]['qty'] + 1 > $avail) {
                $this->sendMessage($chatId, "⚠ Stok tersisa hanya {$avail}.");

                return;
            }
            $cart[$idx]['qty']++;
        } elseif ($action === 'cart_dec') {
            $cart[$idx]['qty']--;
            if ($cart[$idx]['qty'] <= 0) {
                unset($cart[$idx]);
                $cart = array_values($cart);
            }
        }

        $state->setCart($cart);
        $this->showCart($chatId);
    }

    /* =======================================================================
     * Helpers untuk dipanggil dari OrderFulfillment / event
     * ======================================================================= */

    /** Kirim kredensial ke user via Telegram (saat order PAID & user linked). */
    public function sendCredentialsForOrder(Order $order): void
    {
        if (! $order->user || ! $order->user->telegram_chat_id) {
            return;
        }

        $chatId = $order->user->telegram_chat_id;

        // Hapus message QR sebelumnya supaya chat rapi setelah paid.
        if ($order->telegram_qr_message_id) {
            try {
                $this->call('deleteMessage', [
                    'chat_id' => $chatId,
                    'message_id' => (int) $order->telegram_qr_message_id,
                ]);
            } catch (\Throwable $e) {
                // Telegram batas delete 48 jam — kalau gagal, abaikan saja.
            }
            $order->forceFill(['telegram_qr_message_id' => null])->save();
        }

        $items = $order->items()->with(['variant.product', 'stock'])->get();

        if ($items->isEmpty()) {
            return;
        }

        $first = $items->first();
        $firstProductName = $first->variant?->product?->name ?? $first->product?->name ?? 'Produk';
        $firstVariantName = $first->variant?->name ?? '';
        $totalQty = (int) $items->sum('qty');
        $accountsAssigned = $items->filter(fn ($i) => $i->stock)->sum('qty');
        $price = (int) ($order->amount ?? $order->total_payment);
        $fee = (int) ($order->fee ?? 0);
        $totalDibayar = $price + $fee;
        $paidAt = $order->paid_at ?? now();
        $tanggal = $this->formatTanggalIndo($paidAt);

        $text = "╭────〔 <b>TRANSAKSI SUKSES</b> 〕─\n";
        $text .= "\n";
        $text .= '┊・Invoice ID : <code>'.htmlspecialchars($order->order_code)."</code>\n";
        $text .= '┊・Nama Produk : '.htmlspecialchars($firstProductName)."\n";
        $text .= '┊・Nama Variasi : '.htmlspecialchars($firstVariantName)."\n";
        $text .= "┊・ID Buyer : {$order->user->id}\n";
        $text .= '┊・Nomor Buyer : '.htmlspecialchars((string) $chatId)."\n";
        $text .= "┊・Jumlah Beli : {$totalQty}\n";
        $text .= "┊・Jumlah Akun didapat : {$accountsAssigned}\n";
        $text .= '┊・Harga : '.number_format($price, 0, ',', '.')."\n";
        $text .= '┊・Fee : '.number_format($fee, 0, ',', '.')."\n";
        $text .= '┊・Total Dibayar : '.number_format($totalDibayar, 0, ',', '.')."\n";
        $text .= "┊・Methode Pay : QRIS auto\n";
        $text .= "┊・Tanggal/Jam Transaksi : {$tanggal}\n";
        $text .= "╰┈┈┈┈┈┈┈┈\n\n";
        $text .= "<b>Akun premium kamu:</b>\n\n";

        $hasAny = false;
        foreach ($items as $i => $item) {
            $no = $i + 1;
            $productName = $item->variant?->product?->name ?? $item->product?->name ?? 'Produk';
            $variantName = $item->variant?->name ?? '';
            $text .= "<b>{$no}. ".htmlspecialchars($productName).' — '.htmlspecialchars($variantName)."</b>\n";

            if ($item->stock) {
                $hasAny = true;
                $text .= '📧 Email: <code>'.htmlspecialchars($item->stock->email_or_phone)."</code>\n";
                $text .= '🔑 Password: <code>'.htmlspecialchars($item->stock->password)."</code>\n";
                if ($item->stock->additional_info) {
                    $text .= 'ℹ Info: '.htmlspecialchars($item->stock->additional_info)."\n";
                }
            } else {
                $text .= "⏳ Akun masih diproses oleh admin. Cek kembali nanti.\n";
            }
            $text .= "\n";
        }

        if (! $hasAny) {
            $text .= '<i>Sedang diproses oleh admin. Tunggu sebentar ya.</i>';
        }

        $rows = [
            [['text' => '🔗 Buka Invoice', 'url' => url('/invoice/'.$order->order_code)]],
            [['text' => '🏠 Menu Utama', 'callback_data' => 'menu']],
        ];

        $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode(['inline_keyboard' => $rows]),
        ]);
    }

    protected function formatTanggalIndo(\DateTimeInterface $dt): string
    {
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $d = (int) $dt->format('j');
        $m = (int) $dt->format('n');
        $y = $dt->format('Y');
        $jam = $dt->format('H.i');

        return "{$d} {$bulan[$m]} {$y} pukul {$jam}";
    }

    public function notifyAdmin(string $text): void
    {
        $adminChatId = config('services.telegram.admin_chat_id');
        if (! $adminChatId) {
            return;
        }

        // Pakai bot notif terpisah kalau dikonfigurasi (TELEGRAM_NOTIF_BOT_TOKEN),
        // fallback ke bot utama supaya backward-compatible.
        $apiBase = $this->notifApiBase ?? $this->apiBase;

        $this->callOn($apiBase, 'sendMessage', [
            'chat_id' => (string) $adminChatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    /**
     * Push notif ke admin saat order baru dibuat (PENDING).
     * Sumber: web checkout, web cart, atau Telegram bot. Tidak throw —
     * gagal kirim hanya logged supaya tidak block flow checkout.
     */
    public function notifyAdminOrderCreated(Order $order, string $sourceLabel): void
    {
        try {
            $order->loadMissing(['user', 'product', 'variant', 'items.product', 'items.variant']);

            if ($order->items->isNotEmpty()) {
                $lines = $order->items->map(fn ($i) => '• '.htmlspecialchars((string) ($i->product?->name ?? '-'))
                    .' — '.htmlspecialchars((string) ($i->variant?->name ?? '-'))
                    .' (×'.(int) $i->qty.')')->all();
            } else {
                $lines = ['• '.htmlspecialchars((string) ($order->product?->name ?? '-'))
                    .' — '.htmlspecialchars((string) ($order->variant?->name ?? '-'))];
            }

            $customer = $order->user
                ? ($order->user->name.' ('.$order->user->email.')')
                : ((string) $order->customer_email.($order->customer_phone ? ' / '.$order->customer_phone : ''));

            $this->notifyAdmin("🆕 <b>Order baru</b> via {$sourceLabel}\n\n"
                .'🆔 <code>'.htmlspecialchars((string) $order->order_code)."</code>\n"
                .'👤 '.htmlspecialchars($customer)."\n"
                .implode("\n", $lines)."\n"
                .'💵 Rp '.number_format((int) $order->total_payment, 0, ',', '.'));
        } catch (\Throwable $e) {
            Log::warning('notifyAdminOrderCreated failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /** Push notif ke admin saat order transisi ke PAID (semua source). */
    public function notifyAdminOrderPaid(Order $order): void
    {
        try {
            $order->loadMissing(['user']);
            $customer = $order->user
                ? ($order->user->name.' ('.$order->user->email.')')
                : (string) $order->customer_email;

            $this->notifyAdmin("💚 <b>Order PAID</b>\n\n"
                .'🆔 <code>'.htmlspecialchars((string) $order->order_code)."</code>\n"
                .'👤 '.htmlspecialchars($customer)."\n"
                .'💵 Rp '.number_format((int) $order->total_payment, 0, ',', '.')."\n"
                .'💳 '.htmlspecialchars((string) ($order->payment_method ?: 'pakasir')));
        } catch (\Throwable $e) {
            Log::warning('notifyAdminOrderPaid failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /* =======================================================================
     * Broadcast (admin only)
     * ======================================================================= */

    protected function isAdminChat(string $chatId): bool
    {
        $adminChatId = (string) config('services.telegram.admin_chat_id');

        return $adminChatId !== '' && $chatId === $adminChatId;
    }

    /**
     * Daftar chat_id penerima broadcast: gabungan user yang sudah linked +
     * chat yang pernah berinteraksi dengan bot. Filter unique.
     *
     * @return array<int,string>
     */
    public function broadcastRecipients(): array
    {
        $linked = User::whereNotNull('telegram_chat_id')->pluck('telegram_chat_id')->all();
        $interacted = TelegramBotState::pluck('chat_id')->all();
        $all = array_unique(array_filter(array_map('strval', array_merge($linked, $interacted))));

        return array_values($all);
    }

    protected function handleBroadcastStart(string $chatId): void
    {
        if (! $this->isAdminChat($chatId)) {
            $this->sendMessage($chatId, '⛔ Perintah ini hanya untuk admin.');

            return;
        }

        TelegramBotState::for($chatId)->setState(TelegramBotState::STATE_AWAITING_BROADCAST, []);

        $count = count($this->broadcastRecipients());
        $this->sendMessage(
            $chatId,
            "📢 <b>Broadcast Mode</b>\n\n".
            "Kirim pesan yang ingin di-broadcast (boleh HTML: <code>&lt;b&gt;</code>, <code>&lt;i&gt;</code>, <code>&lt;a&gt;</code>).\n".
            "Penerima saat ini: <b>{$count}</b> chat.\n\n".
            'Ketik /batal untuk membatalkan.'
        );
    }

    protected function handleBroadcastDraft(string $chatId, string $text, TelegramBotState $state): void
    {
        if ($text === '') {
            $this->sendMessage($chatId, '⚠ Pesan kosong. Kirim teks yang ingin di-broadcast atau /batal.');

            return;
        }

        $count = count($this->broadcastRecipients());
        $payload = $state->payload ?? [];
        $payload['broadcast_text'] = $text;
        $state->setState(TelegramBotState::STATE_AWAITING_BROADCAST, $payload);

        $preview = "📢 <b>Preview Broadcast</b>\n\n".
            "──────────────\n".
            $text."\n".
            "──────────────\n\n".
            "Akan dikirim ke <b>{$count}</b> chat. Konfirmasi?";

        $this->sendMessage($chatId, $preview, [
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Kirim', 'callback_data' => 'bcast_confirm'],
                        ['text' => '❌ Batal', 'callback_data' => 'bcast_cancel'],
                    ],
                ],
            ],
        ]);
    }

    protected function handleBroadcastConfirm(string $chatId, bool $confirmed): void
    {
        if (! $this->isAdminChat($chatId)) {
            return;
        }

        $state = TelegramBotState::for($chatId);
        $text = (string) ($state->payload['broadcast_text'] ?? '');

        if (! $confirmed || $text === '') {
            $state->reset();
            $this->sendMessage($chatId, '❌ Broadcast dibatalkan.');

            return;
        }

        $recipients = $this->broadcastRecipients();
        $sent = 0;
        $failed = 0;
        foreach ($recipients as $rid) {
            $res = $this->sendMessage((string) $rid, $text);
            if ($res['ok'] ?? false) {
                $sent++;
            } else {
                $failed++;
            }
            // Hindari rate-limit Telegram (~30 msg/sec)
            usleep(50_000);
        }

        $state->reset();
        $this->sendMessage(
            $chatId,
            "✅ <b>Broadcast selesai</b>\n\n".
            "Terkirim: <b>{$sent}</b>\n".
            "Gagal: <b>{$failed}</b>\n".
            'Total: '.count($recipients)
        );
    }
}
