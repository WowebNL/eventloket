<?php

use App\Settings\WelcomeSettings;

test('Welcome page is publicly accessible', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('Welcome page contains the correct welcome settings data', function () {
    $response = $this->get('/');

    $settings = app(WelcomeSettings::class);
    $response->assertSee($settings->title);
    $response->assertSee($settings->tagline);
    $response->assertSee($settings->intro);
});
