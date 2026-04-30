<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ResellerScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait standar yang dipasang di semua model owned-by-reseller. Auto-apply
 * `ResellerScope` agar user reseller hanya melihat datanya sendiri, dan
 * sediakan relasi `reseller()`.
 */
trait BelongsToReseller
{
    public static function bootBelongsToReseller(): void
    {
        static::addGlobalScope(new ResellerScope);

        // Auto-fill reseller_id saat model dibuat oleh user reseller, supaya
        // resource Filament tidak perlu explicit set di setiap form.
        static::creating(function ($model) {
            if (! $model->reseller_id && Auth::hasUser()) {
                $user = Auth::user();
                if (method_exists($user, 'isReseller') && $user->isReseller()) {
                    $model->reseller_id = $user->getKey();
                }
            }
        });
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }
}
