<?php

namespace Tests\Feature\Reseller;

use App\Filament\Reseller\Pages\Auth\ResellerRegister;
use App\Models\ResellerSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResellerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_reseller_with_settings_and_subscription(): void
    {
        Livewire::test(ResellerRegister::class)
            ->set('data.name', 'Budi Reseller')
            ->set('data.reseller_slug', 'budi-toko')
            ->set('data.email', 'budi@test.local')
            ->set('data.password', 'rahasia12')
            ->set('data.passwordConfirmation', 'rahasia12')
            ->call('register');

        $user = User::where('email', 'budi@test.local')->first();
        $this->assertNotNull($user, 'User should be created');
        $this->assertSame(User::ROLE_RESELLER, $user->role);
        $this->assertSame('budi-toko', $user->reseller_slug);
        $this->assertNull($user->approved_at, 'Reseller harus menunggu approval admin');
        $this->assertFalse((bool) $user->is_admin);

        $this->assertNotNull($user->resellerSettings, 'ResellerSetting harus dibuat saat register');
        $this->assertFalse($user->resellerSettings->is_active);

        $sub = $user->subscription;
        $this->assertNotNull($sub);
        $this->assertSame(ResellerSubscription::STATUS_TRIAL, $sub->status);
        $this->assertSame(250000, $sub->monthly_fee);
    }

    public function test_register_rejects_invalid_slug(): void
    {
        Livewire::test(ResellerRegister::class)
            ->set('data.name', 'Budi')
            ->set('data.reseller_slug', 'BAD SLUG dengan spasi')
            ->set('data.email', 'budi2@test.local')
            ->set('data.password', 'rahasia12')
            ->set('data.passwordConfirmation', 'rahasia12')
            ->call('register')
            ->assertHasFormErrors(['reseller_slug']);

        $this->assertDatabaseMissing('users', ['email' => 'budi2@test.local']);
    }

    public function test_register_rejects_duplicate_slug(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'existing@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_RESELLER,
            'reseller_slug' => 'taken-slug',
        ]);

        Livewire::test(ResellerRegister::class)
            ->set('data.name', 'Other')
            ->set('data.reseller_slug', 'taken-slug')
            ->set('data.email', 'other@test.local')
            ->set('data.password', 'rahasia12')
            ->set('data.passwordConfirmation', 'rahasia12')
            ->call('register')
            ->assertHasFormErrors(['reseller_slug']);
    }

    public function test_role_helpers_work(): void
    {
        $admin = User::create([
            'name' => 'A', 'email' => 'a@x.local', 'password' => 'p',
            'role' => User::ROLE_ADMIN, 'is_admin' => true,
        ]);
        $reseller = User::create([
            'name' => 'R', 'email' => 'r@x.local', 'password' => 'p',
            'role' => User::ROLE_RESELLER, 'reseller_slug' => 'r',
        ]);
        $customer = User::create([
            'name' => 'C', 'email' => 'c@x.local', 'password' => 'p',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isReseller());
        $this->assertFalse($admin->isCustomer());

        $this->assertTrue($reseller->isReseller());
        $this->assertFalse($reseller->isAdmin());

        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isAdmin());
    }
}
