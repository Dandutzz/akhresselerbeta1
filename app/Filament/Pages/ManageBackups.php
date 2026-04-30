<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageBackups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowDown;

    protected static ?string $navigationLabel = 'Backup';

    protected static ?string $title = 'Backup & Restore';

    protected static ?int $navigationSort = 100;

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Stok';

    protected string $view = 'filament.pages.manage-backups';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Backup otomatis harian (02:00 WIB) — DB + storage. Simpan di: '.
            storage_path('app/private/akhpremium-backups');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackups(): array
    {
        $disk = Storage::disk('backups');
        $files = collect($disk->allFiles())
            ->filter(fn (string $f) => str_ends_with(strtolower($f), '.zip'))
            ->map(function (string $f) use ($disk): array {
                $size = $disk->size($f);
                $ts = $disk->lastModified($f);

                return [
                    'name' => $f,
                    'size_human' => $this->humanBytes($size),
                    'modified' => Carbon::createFromTimestamp($ts)->setTimezone(config('app.timezone'))->format('d M Y H:i'),
                    'ts' => $ts,
                ];
            })
            ->sortByDesc('ts')
            ->values()
            ->all();

        return $files;
    }

    public function getLatestBackupAge(): ?string
    {
        $backups = $this->getBackups();
        if (empty($backups)) {
            return null;
        }
        $latest = Carbon::createFromTimestamp($backups[0]['ts']);

        return $latest->diffForHumans();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('runBackup')
                ->label('Backup Sekarang')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Jalankan backup sekarang?')
                ->modalDescription('Akan men-dump database lengkap + isi storage/app dan menyimpan ZIP terenkripsi (jika BACKUP_ARCHIVE_PASSWORD diset).')
                ->modalSubmitActionLabel('Ya, Backup Sekarang')
                ->action(function () {
                    try {
                        Artisan::call('backup:run');
                        Notification::make()
                            ->title('Backup selesai')
                            ->body('File ZIP baru tersimpan di disk "backups". Refresh halaman untuk melihat.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Backup gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('runDbOnly')
                ->label('Backup DB Saja')
                ->icon('heroicon-o-circle-stack')
                ->color('gray')
                ->action(function () {
                    try {
                        Artisan::call('backup:run', ['--only-db' => true]);
                        Notification::make()->title('Backup DB selesai')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Backup DB gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('cleanup')
                ->label('Bersihkan Backup Lama')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    Artisan::call('backup:clean');
                    Notification::make()->title('Cleanup selesai')->success()->send();
                }),
        ];
    }

    public function downloadBackup(string $file): StreamedResponse
    {
        abort_unless(Storage::disk('backups')->exists($file), 404);

        return Storage::disk('backups')->download($file);
    }

    public function deleteBackup(string $file): void
    {
        if (Storage::disk('backups')->exists($file)) {
            Storage::disk('backups')->delete($file);
            Notification::make()->title('File backup dihapus')->success()->send();
        }
    }

    protected function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return number_format($size, 2).' '.$units[$i];
    }
}
