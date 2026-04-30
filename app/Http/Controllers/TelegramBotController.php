<?php

namespace App\Http\Controllers;

use App\Models\TelegramLinkToken;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TelegramBotController extends Controller
{
    public function __construct(protected TelegramBotService $bot) {}

    /**
     * POST /webhooks/telegram/{secret}
     * Webhook dipanggil oleh Telegram saat ada update.
     * Secret di URL untuk auth — harus match config.
     */
    public function webhook(Request $request, string $secret): JsonResponse
    {
        $expected = config('services.telegram.webhook_secret');

        if (! $expected || ! hash_equals($expected, $secret)) {
            Log::warning('Telegram webhook unauthorized', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();
        Log::info('Telegram webhook update received', ['update_id' => $update['update_id'] ?? null]);

        $this->bot->handleUpdate($update);

        // Telegram cuma butuh 200 OK
        return response()->json(['ok' => true]);
    }

    /**
     * GET /akun/telegram — halaman link akun web ke Telegram.
     */
    public function showLinkPage(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $activeToken = TelegramLinkToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return view('account.telegram', [
            'user' => $user,
            'token' => $activeToken,
            'botUsername' => config('services.telegram.bot_username'),
        ]);
    }

    /**
     * POST /akun/telegram/generate — generate token baru.
     */
    public function generateToken(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $token = TelegramLinkToken::generateFor($user, 15);

        return back()->with('success', 'Token berhasil dibuat. Berlaku 15 menit.');
    }

    /**
     * POST /akun/telegram/unlink — unlink akun.
     */
    public function unlink(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $user->update(['telegram_chat_id' => null, 'telegram_username' => null]);

        return back()->with('success', 'Akun Telegram berhasil di-unlink.');
    }
}
