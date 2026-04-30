<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
            <div class="text-sm text-gray-500 dark:text-gray-400">Backup terbaru</div>
            <div class="mt-2 text-2xl font-semibold">
                {{ $this->getLatestBackupAge() ?? 'Belum ada backup' }}
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
            <div class="text-sm text-gray-500 dark:text-gray-400">Jumlah file backup</div>
            <div class="mt-2 text-2xl font-semibold">{{ count($this->getBackups()) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-5">
            <div class="text-sm text-gray-500 dark:text-gray-400">Jadwal otomatis</div>
            <div class="mt-2 text-sm font-medium">
                Cleanup 02:00 → Backup DB 02:05 → Backup full 02:30 (WIB)<br>
                Health check 09:00 WIB
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nama File</th>
                    <th class="px-4 py-3">Ukuran</th>
                    <th class="px-4 py-3">Dibuat</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10 text-sm">
                @forelse($this->getBackups() as $b)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $b['name'] }}</td>
                        <td class="px-4 py-3">{{ $b['size_human'] }}</td>
                        <td class="px-4 py-3">{{ $b['modified'] }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click="downloadBackup('{{ $b['name'] }}')"
                                    class="text-primary-600 hover:underline">Download</button>
                            <button wire:click="deleteBackup('{{ $b['name'] }}')"
                                    wire:confirm="Hapus file backup ini?"
                                    class="text-danger-600 hover:underline">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                            Belum ada file backup. Klik <strong>Backup Sekarang</strong> di atas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
        <p><strong>Cara restore database</strong> (di server target):</p>
        <ol class="list-decimal ml-5 mt-2 space-y-1">
            <li>Download file <code>YYYY-MM-DD-HH-mm-ss.zip</code> dan unzip (password = <code>BACKUP_ARCHIVE_PASSWORD</code> kalau diset di <code>.env</code>).</li>
            <li>Di dalam ZIP ada folder <code>db-dumps/</code> — cari <code>{databasename}.sql.gz</code> (atau .sql).</li>
            <li>Decompress: <code>gunzip mysql-{databasename}.sql.gz</code>.</li>
            <li>Restore: <code>mysql -u DBUSER -p DBNAME &lt; mysql-{databasename}.sql</code>.</li>
            <li>Salin folder <code>storage/app/public</code> dan <code>storage/app/private</code> dari ZIP ke <code>storage/app/</code> aplikasi target.</li>
            <li>Jalankan <code>php artisan storage:link</code> + <code>php artisan config:clear</code>.</li>
        </ol>
    </div>
</x-filament-panels::page>
