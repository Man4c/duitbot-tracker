<?php

use App\Jobs\ReconcileBudgets;
use App\Jobs\SendWeeklyDigest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ReconcileBudgets)->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::job(new SendWeeklyDigest)->weeklyOn(0, '20:00')->timezone('Asia/Jakarta')->withoutOverlapping();
