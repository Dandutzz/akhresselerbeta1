<?php

namespace Tests\Feature\Reseller;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_panel_but_not_reseller_panel(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_ADMIN,
            'is_admin' => true,
        ]);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('reseller')));
    }

    public function test_approved_reseller_can_access_reseller_panel_but_not_admin(): void
    {
        $reseller = User::create([
            'name' => 'R',
            'email' => 'r@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_RESELLER,
            'reseller_slug' => 'r1',
            'approved_at' => now(),
        ]);

        $this->assertTrue($reseller->canAccessPanel(Filament::getPanel('reseller')));
        $this->assertFalse($reseller->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_unapproved_reseller_cannot_access_reseller_panel(): void
    {
        $reseller = User::create([
            'name' => 'R',
            'email' => 'r@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_RESELLER,
            'reseller_slug' => 'r1',
            'approved_at' => null, // belum di-approve admin
        ]);

        $this->assertFalse($reseller->canAccessPanel(Filament::getPanel('reseller')));
    }

    public function test_banned_user_cannot_access_any_panel(): void
    {
        $banned = User::create([
            'name' => 'B',
            'email' => 'b@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_RESELLER,
            'reseller_slug' => 'b',
            'approved_at' => now(),
            'is_banned' => true,
        ]);

        $this->assertFalse($banned->canAccessPanel(Filament::getPanel('reseller')));
        $this->assertFalse($banned->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_customer_cannot_access_any_panel(): void
    {
        $customer = User::create([
            'name' => 'C',
            'email' => 'c@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->assertFalse($customer->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($customer->canAccessPanel(Filament::getPanel('reseller')));
    }
}
