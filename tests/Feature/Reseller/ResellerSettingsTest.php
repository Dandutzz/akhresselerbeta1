<?php

namespace Tests\Feature\Reseller;

use App\Models\ResellerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResellerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_credentials_are_encrypted_at_rest(): void
    {
        $reseller = User::create([
            'name' => 'R', 'email' => 'r@test.local', 'password' => 'rahasia12',
            'role' => User::ROLE_RESELLER, 'reseller_slug' => 'r',
        ]);

        $token = '123456789:ABCDEF_super_secret_bot_token';
        $apiKey = 'pk_live_super_secret_pakasir';

        $setting = ResellerSetting::create([
            'user_id' => $reseller->id,
            'tg_bot_token' => $token,
            'pakasir_api_key' => $apiKey,
            'fonnte_api_key' => 'fonnte_xyz',
        ]);

        // Re-fetch from DB to verify decryption works.
        $fresh = ResellerSetting::where('id', $setting->id)->first();
        $this->assertSame($token, $fresh->tg_bot_token);
        $this->assertSame($apiKey, $fresh->pakasir_api_key);

        // Verify ciphertext at rest is NOT plaintext.
        $raw = DB::table('reseller_settings')->where('id', $setting->id)->first();
        $this->assertStringNotContainsString($token, $raw->tg_bot_token);
        $this->assertStringNotContainsString($apiKey, $raw->pakasir_api_key);
    }

    public function test_webhook_secret_is_unique_across_resellers(): void
    {
        $secret1 = ResellerSetting::generateWebhookSecret();
        $secret2 = ResellerSetting::generateWebhookSecret();

        $this->assertNotSame($secret1, $secret2);
        $this->assertSame(48, strlen($secret1));
        $this->assertSame(48, strlen($secret2));
    }
}
