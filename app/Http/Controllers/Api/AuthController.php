<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private function find(string $identifier): ?User
    {
        $value = ltrim($identifier, '@');

        return User::query()->where(fn ($q) => $q->where('telegram_id', ctype_digit($value) ? $value : -1)->orWhere('telegram_username', $value))->first();
    }

    /**
     * Cek apakah identifier (Telegram ID/username) sudah terdaftar.
     *
     * CATATAN KEAMANAN: endpoint ini sengaja membocorkan status "terdaftar" untuk UX
     * verifikasi real-time di form (user tahu OTP akan sampai sebelum menunggu). Ini
     * melonggarkan anti-user-enumeration di requestOtp(). Mitigasi: respons hanya boolean
     * (tanpa data lain) + rate limit ketat (throttle:20,1) di definisi route.
     */
    public function check(RequestOtpRequest $request): JsonResponse
    {
        return response()->json(['registered' => $this->find($request->validated('identifier')) !== null]);
    }

    public function requestOtp(RequestOtpRequest $request, OtpService $otp): JsonResponse
    {
        if ($user = $this->find($request->validated('identifier'))) {
            $otp->issue($user);
        }

        return response()->json(['message' => 'Jika akun Telegram terdaftar, kode telah dikirim lewat bot.']);
    }

    public function verify(VerifyOtpRequest $request, OtpService $otp): JsonResponse
    {
        $user = $this->find($request->validated('identifier'));
        if (! $user || ! $otp->verify($user, $request->validated('code'))) {
            return response()->json(['message' => 'Kode tidak valid atau sudah kedaluwarsa.'], 422);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['message' => 'Login berhasil.', 'redirect' => route('dashboard')]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
