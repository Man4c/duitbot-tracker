<?php

use Illuminate\Support\Facades\Http;

it('registers the bot command menu with Telegram', function () {
    config(['services.telegram.bot_token' => 'test-token']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

    $this->artisan('telegram:set-commands')->assertSuccessful();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/setMyCommands')) {
            return false;
        }
        $commands = $request['commands'];
        $names = array_map(fn ($c) => $c['command'], $commands);

        // Semua command bot terdaftar, tanpa garis miring, dan tiap command punya deskripsi.
        return $names === ['start', 'help', 'login', 'hari_ini', 'ringkasan', 'total', 'ubah', 'hapus']
            && collect($commands)->every(fn ($c) => $c['description'] !== '');
    });
});

it('does not leak the bot token when registration fails', function () {
    config(['services.telegram.bot_token' => 'very-secret-token']);
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException(
        'Request failed for https://api.telegram.org/botvery-secret-token/setMyCommands'
    ));

    $this->artisan('telegram:set-commands')
        ->doesntExpectOutputToContain('very-secret-token')
        ->assertFailed();
});
