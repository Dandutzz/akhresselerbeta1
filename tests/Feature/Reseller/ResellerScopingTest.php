<?php

namespace Tests\Feature\Reseller;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi bahwa `ResellerScope` global scope benar-benar memfilter query
 * berdasarkan reseller_id user yang sedang login. Salah satu defense paling
 * penting di multi-tenant — kalau bocor, reseller bisa lihat data reseller lain.
 */
class ResellerScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_only_sees_their_own_products(): void
    {
        [$resellerA, $resellerB] = $this->makeTwoApprovedResellers();

        Product::create([
            'reseller_id' => $resellerA->id,
            'name' => 'Product A',
            'description' => 'Owned by A',
            'price' => 10000,
        ]);
        Product::create([
            'reseller_id' => $resellerB->id,
            'name' => 'Product B',
            'description' => 'Owned by B',
            'price' => 20000,
        ]);

        $this->actingAs($resellerA);

        $products = Product::all();

        $this->assertCount(1, $products);
        $this->assertSame('Product A', $products->first()->name);
    }

    public function test_admin_sees_all_products_across_resellers(): void
    {
        [$resellerA, $resellerB] = $this->makeTwoApprovedResellers();
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_ADMIN,
            'is_admin' => true,
        ]);

        Product::create(['reseller_id' => $resellerA->id, 'name' => 'P-A', 'description' => 'x', 'price' => 1]);
        Product::create(['reseller_id' => $resellerB->id, 'name' => 'P-B', 'description' => 'x', 'price' => 1]);

        $this->actingAs($admin);

        $this->assertCount(2, Product::all());
    }

    public function test_guest_request_is_not_scoped(): void
    {
        // Storefront publik (guest) harus tetap lihat semua produk —
        // di MVP storefront masih single-tenant.
        [$resellerA, $resellerB] = $this->makeTwoApprovedResellers();
        Product::create(['reseller_id' => $resellerA->id, 'name' => 'P-A', 'description' => 'x', 'price' => 1]);
        Product::create(['reseller_id' => $resellerB->id, 'name' => 'P-B', 'description' => 'x', 'price' => 1]);

        // No actingAs — guest
        $this->assertCount(2, Product::all());
    }

    public function test_without_reseller_scope_macro_bypasses_filter(): void
    {
        [$resellerA, $resellerB] = $this->makeTwoApprovedResellers();
        Product::create(['reseller_id' => $resellerA->id, 'name' => 'P-A', 'description' => 'x', 'price' => 1]);
        Product::create(['reseller_id' => $resellerB->id, 'name' => 'P-B', 'description' => 'x', 'price' => 1]);

        $this->actingAs($resellerA);

        $this->assertCount(1, Product::all());
        $this->assertCount(2, Product::withoutResellerScope()->get());
    }

    public function test_creating_model_as_reseller_auto_fills_reseller_id(): void
    {
        [$resellerA] = $this->makeTwoApprovedResellers();

        $this->actingAs($resellerA);

        $cat = Category::create(['name' => 'Streaming', 'slug' => 'streaming']);

        $this->assertSame($resellerA->id, $cat->reseller_id);
    }

    public function test_admin_creating_model_does_not_force_reseller_id(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_ADMIN,
            'is_admin' => true,
        ]);
        $reseller = User::create([
            'name' => 'R',
            'email' => 'r@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_RESELLER,
            'reseller_slug' => 'r1',
            'approved_at' => now(),
        ]);

        $this->actingAs($admin);

        // Admin bisa explicit assign reseller_id ke user lain.
        $cat = Category::create(['reseller_id' => $reseller->id, 'name' => 'X', 'slug' => 'x']);
        $this->assertSame($reseller->id, $cat->reseller_id);
    }

    /**
     * @return array{0: User, 1: User}
     */
    protected function makeTwoApprovedResellers(): array
    {
        $a = User::create([
            'name' => 'Reseller A',
            'email' => 'a@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_RESELLER,
            'reseller_slug' => 'reseller-a',
            'approved_at' => now(),
        ]);
        $b = User::create([
            'name' => 'Reseller B',
            'email' => 'b@test.local',
            'password' => 'rahasia12',
            'role' => User::ROLE_RESELLER,
            'reseller_slug' => 'reseller-b',
            'approved_at' => now(),
        ]);

        return [$a, $b];
    }
}
