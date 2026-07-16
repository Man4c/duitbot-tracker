<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    /**
     * Pemicu scheduler untuk deployment free-tier (tanpa cron job Render berbayar).
     * Dipanggil cron eksternal gratis (mis. cron-job.org) tiap menit; menjalankan
     * `schedule:run` yang mengevaluasi task yang jatuh tempo di routes/console.php.
     *
     * Keamanan: bandingkan CRON_SECRET via hash_equals (pola sama seperti webhook).
     * Kalau secret kosong/tidak cocok → 403, jadi aman-by-default.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('services.cron.secret');
        $provided = (string) ($request->header('X-Cron-Secret') ?: $request->query('token', ''));
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(403, 'Cron secret tidak valid.');
        }

        Artisan::call('schedule:run');

        return response()->json(['ok' => true]);
    }
}
