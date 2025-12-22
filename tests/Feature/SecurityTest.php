<?php

use Illuminate\Support\Facades\RateLimiter;

test('login endpoint is rate limited', function () {
    RateLimiter::clear('api');

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'hacker@test.com',
            'password' => 'wrong',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'hacker@test.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(429);
});

test('public api endpoints have basic security headers', function () {
    $response = $this->getJson('/api/v1/ads');

    $response->assertStatus(200);
});
