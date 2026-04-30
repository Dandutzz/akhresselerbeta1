<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderFulfillment;
use App\Services\PakasirService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook receiver untuk Pakasir.
 *
 * Pakasir TIDAK mengirim signature/HMAC pada webhook, maka verifikasi WAJIB
 * dilakukan dengan memanggil Transaction Detail API (dokumentasi Pakasir
 * menyebutkan hal ini secara eksplisit).
 */
class PakasirWebhookController extends Controller
{
    public function __construct(
        protected PakasirService $pakasir,
        protected OrderFulfillment $fulfillment,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Pakasir webhook received', [
            'payload' => $payload,
            'ip' => $request->ip(),
        ]);

        $orderCode = (string) ($payload['order_id'] ?? '');
        $project = (string) ($payload['project'] ?? '');
        $amount = (int) ($payload['amount'] ?? 0);
        $status = (string) ($payload['status'] ?? '');

        if ($orderCode === '' || $amount <= 0) {
            return response()->json(['ok' => false, 'error' => 'invalid_payload'], 400);
        }

        // Pastikan project sesuai dengan konfigurasi — mencegah webhook dari
        // project lain yang secara tidak sengaja/malicious menabrak sistem kita.
        $configuredProject = $this->pakasir->projectSlug();
        if ($configuredProject && $project !== $configuredProject) {
            Log::warning('Pakasir webhook: project mismatch', compact('project', 'orderCode'));

            return response()->json(['ok' => false, 'error' => 'project_mismatch'], 403);
        }

        $order = Order::where('order_code', $orderCode)->first();
        if (! $order) {
            return response()->json(['ok' => false, 'error' => 'order_not_found'], 404);
        }

        // Hanya proses status yang menyatakan sukses.
        if ($status !== 'completed') {
            Audit::log('webhook.non_completed', $order, compact('status'));

            return response()->json(['ok' => true, 'note' => 'status_ignored']);
        }

        // Cegah webhook membangkitkan order yang sudah di-cancel/refund/expired/failed.
        // Hanya order PENDING (transisi normal) atau PAID (idempotent retry) yang
        // boleh diproses. Manual override tetap bisa lewat admin Filament.
        if (! $order->isPending() && ! $order->isPaid()) {
            Audit::log('webhook.order_not_processable', $order, [
                'current_status' => $order->status,
                'incoming_status' => $status,
            ]);

            return response()->json(['ok' => true, 'note' => 'order_not_processable']);
        }

        // Verifikasi via API — jangan percaya payload saja.
        if (! $this->pakasir->verifyWebhook($order, $payload)) {
            Audit::log('webhook.verification_failed', $order, compact('amount', 'status'));

            return response()->json(['ok' => false, 'error' => 'verification_failed'], 422);
        }

        $this->fulfillment->markPaidAndAssignStock($order, [
            'amount' => $amount,
            'payment_method' => $payload['payment_method'] ?? null,
            'source' => 'webhook',
        ]);

        Audit::log('webhook.processed', $order->refresh(), [
            'amount' => $amount,
            'payment_method' => $payload['payment_method'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
