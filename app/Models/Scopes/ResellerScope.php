<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Scope global yang otomatis filter row by `reseller_id` ketika user yang
 * sedang login adalah seorang reseller. Admin, request publik (guest), dan
 * worker (CLI/queue) bypass scope sehingga tetap melihat seluruh data.
 *
 * Untuk paksa lihat semua data, panggil `Model::withoutResellerScope()`
 * (dipakai di webhook handler, queue, dan storefront publik).
 */
class ResellerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Hanya apply scope di context request HTTP dengan user reseller login.
        // Saat CLI / job / guest, biarkan query polos (tidak filter).
        if (! Auth::hasUser()) {
            return;
        }

        $user = Auth::user();
        if (! method_exists($user, 'isReseller') || ! $user->isReseller()) {
            return;
        }

        $builder->where($model->qualifyColumn('reseller_id'), $user->getKey());
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutResellerScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(self::class);
        });
    }
}
