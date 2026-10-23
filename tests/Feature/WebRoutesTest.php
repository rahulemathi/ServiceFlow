<?php

use App\Models\User;

test('home page returns successful response for guests', function () {
    $this->get('/')
        ->assertOk();
});

test('home page redirects authenticated users to admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/admin');
});

test('login route redirects to filament admin login', function () {
    $this->get('/login')
        ->assertRedirect('/admin/login');
});

test('dashboard route redirects to admin panel', function () {
    $this->get('/dashboard')
        ->assertRedirect('/admin');
});
