<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function __construct(private TelegramService $telegram) {}

    public function issue(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($user, $code) {
            $user->loginOtps()->whereNull('used_at')->update(['used_at' => now()]);
            $user->loginOtps()->create(['code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5)]);
        });
        $this->telegram->sendMessage($user->telegram_id, "🔐 Kode login DuitBot kamu: <b>{$code}</b>\nBerlaku 5 menit dan hanya bisa digunakan sekali.");
    }

    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $otp = $user->loginOtps()->whereNull('used_at')->latest()->lockForUpdate()->first();
            if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= 5) {
                return false;
            }
            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');
                if ($otp->attempts >= 5) {
                    $otp->update(['used_at' => now()]);
                }

                return false;
            }
            $otp->update(['used_at' => now()]);

            return true;
        });
    }
}
