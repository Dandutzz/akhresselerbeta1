<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Endpoint JSON untuk notifikasi mengambang (social proof).
 *
 * Menggabungkan order PAID asli + (opsional) order palsu yang dihasilkan
 * dari produk yang stoknya MASIH ADA — supaya user tidak merasa ditipu
 * (kalau produk yang muncul di notif sebenarnya sudah out-of-stock).
 */
class FloatingNotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $site = SiteSetting::current();

        if (! $site->floating_notif_enabled) {
            return response()->json([
                'enabled' => false,
                'items' => [],
            ]);
        }

        $items = [];

        // ---- Notif ASLI dari order PAID 24 jam terakhir ----
        if ($site->floating_notif_use_real) {
            $real = Order::with(['product:id,name'])
                ->where('status', Order::STATUS_PAID)
                ->where('paid_at', '>=', now()->subHours(24))
                ->orderByDesc('paid_at')
                ->limit(15)
                ->get();

            foreach ($real as $o) {
                $items[] = [
                    'name' => $this->maskName($o->customer_email ?? 'User'),
                    'product' => $o->product?->name ?? 'Produk',
                    'when' => $this->relativeTime($o->paid_at),
                    'real' => true,
                ];
            }
        }

        // ---- Notif PALSU yang sinkron dengan stok aktual ----
        // Hanya keluarkan untuk produk yang MASIH PUNYA varian dengan stok atau
        // varian manual delivery, agar tidak menipu user (claim sold-out yet
        // showing "baru saja membeli").
        if ($site->floating_notif_use_fake) {
            $availableProducts = Product::with(['variants' => function ($q) {
                $q->withCount(['stocks as available_stocks_count' => fn ($s) => $s->where('is_sold', false)]);
            }])->get()->filter(function ($p) {
                return $p->variants->contains(function ($v) {
                    // OK kalau auto + ada stok, ATAU manual delivery (admin akan kirim)
                    if ($v->isAutoSend()) {
                        return $v->available_stocks_count > 0;
                    }

                    return true;
                });
            })->values();

            $names = ['Andi', 'Budi', 'Citra', 'Dewi', 'Eka', 'Fajar', 'Gita', 'Hadi', 'Indah', 'Joko', 'Kiki', 'Lutfi', 'Maya', 'Nia', 'Oka', 'Putri', 'Rian', 'Sari', 'Toni', 'Umi', 'Vina', 'Wira', 'Yusuf', 'Zaki'];
            $cities = ['Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang', 'Yogyakarta', 'Makassar', 'Denpasar', 'Bekasi', 'Bogor'];

            foreach ($availableProducts->take(20) as $p) {
                $items[] = [
                    'name' => $names[array_rand($names)].' di '.$cities[array_rand($cities)],
                    'product' => $p->name,
                    'when' => 'baru saja',
                    'real' => false,
                ];
            }
        }

        // Acak supaya real & fake bercampur
        shuffle($items);

        return response()->json([
            'enabled' => true,
            'interval_min' => max(5, (int) ($site->floating_notif_interval_min ?? 20)),
            'interval_max' => max(10, (int) ($site->floating_notif_interval_max ?? 60)),
            'items' => array_slice($items, 0, 30),
        ]);
    }

    private function maskName(string $email): string
    {
        $local = strstr($email, '@', true) ?: $email;
        if (mb_strlen($local) <= 3) {
            return $local.'***';
        }

        return mb_substr($local, 0, 2).str_repeat('*', max(2, mb_strlen($local) - 3)).mb_substr($local, -1);
    }

    private function relativeTime(?Carbon $t): string
    {
        if (! $t) {
            return 'baru saja';
        }
        $diffMin = $t->diffInMinutes(now());
        if ($diffMin < 1) {
            return 'baru saja';
        }
        if ($diffMin < 60) {
            return $diffMin.' menit lalu';
        }
        $h = (int) floor($diffMin / 60);

        return $h.' jam lalu';
    }
}
