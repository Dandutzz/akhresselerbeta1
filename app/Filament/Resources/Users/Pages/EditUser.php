<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\WalletTransaction;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * is_admin sengaja di luar $fillable demi keamanan — set lewat forceFill
     * di sini (admin panel sudah memvalidasi akses).
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $isAdmin = $data['is_admin'] ?? null;
        unset($data['is_admin']);

        $record->fill($data);
        if ($isAdmin !== null) {
            $record->forceFill(['is_admin' => (bool) $isAdmin]);
        }
        $record->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('walletHistory')
                ->label('Riwayat Saldo')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->modalHeading(fn () => "Riwayat Saldo — {$this->record->name}")
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalWidth('4xl')
                ->modalContent(function () {
                    $txs = $this->record->walletTransactions()->latest()->limit(100)->get();
                    if ($txs->isEmpty()) {
                        return new \Illuminate\Support\HtmlString('<div class="py-8 text-center text-slate-400">Belum ada transaksi saldo</div>');
                    }
                    $rows = $txs->map(function (WalletTransaction $tx) {
                        $amt = number_format(abs($tx->amount), 0, ',', '.');
                        $sign = $tx->amount >= 0 ? '+' : '-';
                        $color = $tx->amount >= 0 ? 'green' : 'red';
                        $note = $tx->note ? '<div class="text-xs text-slate-500 mt-1">'.e($tx->note).'</div>' : '';

                        return '<tr class="border-b">'.
                            '<td class="py-2 px-3 text-xs">'.$tx->created_at->format('d M Y H:i').'</td>'.
                            '<td class="py-2 px-3 text-xs"><span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">'.e($tx->typeLabel()).'</span></td>'.
                            '<td class="py-2 px-3 text-right font-bold text-'.$color.'-600">'.$sign.' Rp '.$amt.'</td>'.
                            '<td class="py-2 px-3 text-xs text-slate-500">Rp '.number_format($tx->balance_after, 0, ',', '.').$note.'</td>'.
                            '</tr>';
                    })->implode('');
                    $html = '<div class="overflow-x-auto"><table class="min-w-full text-sm">'.
                        '<thead><tr class="border-b bg-slate-50"><th class="py-2 px-3 text-left">Waktu</th><th class="py-2 px-3 text-left">Tipe</th><th class="py-2 px-3 text-right">Nominal</th><th class="py-2 px-3 text-left">Saldo Setelah</th></tr></thead>'.
                        '<tbody>'.$rows.'</tbody></table></div>';

                    return new \Illuminate\Support\HtmlString($html);
                }),
        ];
    }
}
