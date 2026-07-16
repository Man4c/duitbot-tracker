<?php

use App\Models\LoginOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;

function otpFor(User $user, string $code = '123456', int $minutes = 5): LoginOtp
{
    return $user->loginOtps()->create(['code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes($minutes)]);
}

it('verifies an otp once and regenerates the authenticated session', function () {
    $user = User::factory()->create();
    otpFor($user);
    $this->postJson('/api/auth/telegram-login', ['identifier' => (string) $user->telegram_id, 'code' => '123456'])->assertOk()->assertJsonPath('redirect', route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->postJson('/api/auth/telegram-login', ['identifier' => (string) $user->telegram_id, 'code' => '123456'])->assertUnprocessable();
});

it('rejects expired otp', function () {
    $user = User::factory()->create();
    otpFor($user, minutes: -1);
    expect(app(OtpService::class)->verify($user, '123456'))->toBeFalse();
});

it('locks an otp after five wrong attempts', function () {
    $user = User::factory()->create();
    $otp = otpFor($user);
    foreach (range(1, 5) as $_) {
        expect(app(OtpService::class)->verify($user, '000000'))->toBeFalse();
    }
    expect($otp->refresh()->attempts)->toBe(5)->and($otp->used_at)->not->toBeNull();
    expect(app(OtpService::class)->verify($user, '123456'))->toBeFalse();
});

it('does not reveal whether the telegram account exists', function () {
    $this->postJson('/api/auth/request-otp', ['identifier' => 'unknown'])->assertOk()->assertJsonPath('message', 'Jika akun Telegram terdaftar, kode telah dikirim lewat bot.');
});

it('rate limits otp requests', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/auth/request-otp', ['identifier' => 'unknown'])->assertOk();
    }
    $this->postJson('/api/auth/request-otp', ['identifier' => 'unknown'])->assertStatus(429);
});

it('reports a registered identifier by id or username', function () {
    $user = User::factory()->create(['telegram_username' => 'cekverif']);

    $this->postJson('/api/auth/check-identifier', ['identifier' => (string) $user->telegram_id])->assertOk()->assertJsonPath('registered', true);
    $this->postJson('/api/auth/check-identifier', ['identifier' => '@cekverif'])->assertOk()->assertJsonPath('registered', true);
    $this->postJson('/api/auth/check-identifier', ['identifier' => 'cekverif'])->assertOk()->assertJsonPath('registered', true);
});

it('reports an unknown identifier as not registered', function () {
    User::factory()->create(['telegram_username' => 'cekverif']);

    $this->postJson('/api/auth/check-identifier', ['identifier' => '@tidakada'])->assertOk()->assertJsonPath('registered', false);
    $this->postJson('/api/auth/check-identifier', ['identifier' => '999999999'])->assertOk()->assertJsonPath('registered', false);
});

it('only leaks the registered boolean, no other user data', function () {
    $user = User::factory()->create(['telegram_username' => 'cekverif']);

    $this->postJson('/api/auth/check-identifier', ['identifier' => (string) $user->telegram_id])
        ->assertOk()
        ->assertExactJson(['registered' => true]);
});

it('rate limits identifier checks at 20 per minute', function () {
    foreach (range(1, 20) as $_) {
        $this->postJson('/api/auth/check-identifier', ['identifier' => 'unknown'])->assertOk();
    }
    $this->postJson('/api/auth/check-identifier', ['identifier' => 'unknown'])->assertStatus(429);
});
