<?php

namespace App\Listeners;

use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Log;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class NotifyTelegramOnBackupEvent
{
    public function __construct(protected TelegramBotService $telegram) {}

    public function handleBackupSuccess(BackupWasSuccessful $event): void
    {
        $this->send(
            "✅ <b>Backup sukses</b>\n".
            'Aplikasi: <code>'.e($event->backupName)."</code>\n".
            'Disk: <code>'.e($event->diskName).'</code>'
        );
    }

    public function handleBackupFailed(BackupHasFailed $event): void
    {
        $this->send(
            "❌ <b>Backup GAGAL</b>\n".
            'Aplikasi: <code>'.e((string) $event->backupName)."</code>\n".
            'Disk: <code>'.e((string) $event->diskName)."</code>\n".
            'Error: <code>'.e(mb_substr($event->exception->getMessage(), 0, 500)).'</code>'
        );
    }

    public function handleHealthy(HealthyBackupWasFound $event): void
    {
        // Cuma log, gak spam Telegram tiap hari
        Log::info('Backup health: HEALTHY', ['name' => $event->backupDestinationStatus->backupDestination()->backupName()]);
    }

    public function handleUnhealthy(UnhealthyBackupWasFound $event): void
    {
        $status = $event->backupDestinationStatus;
        $reason = method_exists($status, 'getHealthCheckFailure')
            ? optional($status->getHealthCheckFailure())->exception()?->getMessage()
            : null;
        $this->send(
            "⚠️ <b>Backup TIDAK SEHAT</b>\n".
            'Aplikasi: <code>'.e($status->backupDestination()->backupName())."</code>\n".
            'Alasan: <code>'.e($reason ?? 'Cek log').'</code>'
        );
    }

    public function handleCleanupSuccess(CleanupWasSuccessful $event): void
    {
        Log::info('Backup cleanup OK');
    }

    public function handleCleanupFailed(CleanupHasFailed $event): void
    {
        $this->send(
            "⚠️ <b>Cleanup backup gagal</b>\n".
            'Error: <code>'.e(mb_substr($event->exception->getMessage(), 0, 500)).'</code>'
        );
    }

    protected function send(string $html): void
    {
        try {
            // notifyAdmin() lookup admin_chat_id sendiri & otomatis pakai bot
            // notif terpisah (TELEGRAM_NOTIF_BOT_TOKEN) kalau dikonfigurasi.
            $this->telegram->notifyAdmin($html);
        } catch (\Throwable $e) {
            Log::warning('Failed to send Telegram backup notification', ['msg' => $e->getMessage()]);
        }
    }

    public function subscribe($events): array
    {
        return [
            BackupWasSuccessful::class => 'handleBackupSuccess',
            BackupHasFailed::class => 'handleBackupFailed',
            HealthyBackupWasFound::class => 'handleHealthy',
            UnhealthyBackupWasFound::class => 'handleUnhealthy',
            CleanupWasSuccessful::class => 'handleCleanupSuccess',
            CleanupHasFailed::class => 'handleCleanupFailed',
        ];
    }
}
