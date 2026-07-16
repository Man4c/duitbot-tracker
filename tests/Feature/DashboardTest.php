<?php

use App\Models\User;

test('guests are redirected to the telegram login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('telegram.login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
