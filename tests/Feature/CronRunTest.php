<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(fn () => config(['services.cron.secret' => 'cron-secret']));

it('rejects cron trigger without a secret', function () {
    $this->postJson('/api/cron/run')->assertForbidden();
});

it('rejects cron trigger with a wrong secret', function () {
    $this->postJson('/api/cron/run', [], ['X-Cron-Secret' => 'salah'])->assertForbidden();
});

it('rejects cron trigger when no secret is configured', function () {
    config(['services.cron.secret' => '']);
    $this->postJson('/api/cron/run', [], ['X-Cron-Secret' => ''])->assertForbidden();
});

it('runs the scheduler with a valid secret via header', function () {
    Artisan::partialMock()->shouldReceive('call')->once()->with('schedule:run')->andReturn(0);
    $this->postJson('/api/cron/run', [], ['X-Cron-Secret' => 'cron-secret'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});

it('runs the scheduler with a valid secret via query token', function () {
    Artisan::partialMock()->shouldReceive('call')->once()->with('schedule:run')->andReturn(0);
    $this->postJson('/api/cron/run?token=cron-secret')
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});
