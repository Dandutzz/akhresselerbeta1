<?php

namespace App\Filament\Reseller\Pages\Auth;

use App\Models\ResellerSetting;
use App\Models\ResellerSubscription;
use App\Models\User;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Custom register page khusus untuk panel reseller. Setelah register:
 *  - Set role='reseller'
 *  - Buat ResellerSetting baris kosong (is_active=false → admin perlu approve)
 *  - Buat ResellerSubscription trial
 *
 * Reseller TIDAK akan bisa langsung login ke /reseller karena
 * `User::isApprovedReseller()` masih false sampai admin approve via panel
 * admin (set `approved_at` + `is_active=true`).
 */
class ResellerRegister extends Register
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getResellerSlugFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getResellerSlugFormComponent(): Component
    {
        return TextInput::make('reseller_slug')
            ->label('Slug Toko (huruf kecil, angka, dan strip saja)')
            ->required()
            ->minLength(3)
            ->maxLength(64)
            ->regex('/^[a-z0-9-]+$/')
            ->helperText('Akan dipakai sebagai identifier unik. Contoh: budiakuun, akhpremium-jakarta.')
            ->unique(table: User::class, column: 'reseller_slug');
    }

    protected function handleRegistration(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // Sengaja buat manual (bukan ::create) agar role di-set eksplisit
            // tanpa melewati $fillable mass-assignment guard.
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = $data['password']; // hashed via cast
            $user->reseller_slug = $data['reseller_slug'];
            $user->role = User::ROLE_RESELLER;
            $user->is_admin = false;
            $user->save();

            ResellerSetting::create(['user_id' => $user->id]);

            ResellerSubscription::create([
                'user_id' => $user->id,
                'plan' => 'flat',
                'monthly_fee' => 250000,
                'status' => ResellerSubscription::STATUS_TRIAL,
                'current_period_start' => now()->toDateString(),
                'current_period_end' => now()->addDays(7)->toDateString(),
            ]);

            return $user;
        });
    }
}
