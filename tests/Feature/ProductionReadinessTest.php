<?php

use Illuminate\Support\Facades\Cache;

test('health check endpoint returns healthy status and 200 OK', function () {
    $response = $this->get('/health');

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'healthy',
        'checks' => [
            'database' => ['status' => 'ok'],
            'storage' => ['status' => 'ok'],
            'cache' => ['status' => 'ok'],
        ],
    ]);
});

test('health check returns 503 degraded when a core dependency fails', function () {
    Cache::shouldReceive('put')->andThrow(new Exception('Cache failure'));

    $response = $this->get('/health');

    $response->assertStatus(503);
    $response->assertJson([
        'status' => 'degraded',
        'checks' => [
            'cache' => ['status' => 'error'],
        ],
    ]);
});

test('verify-backup artisan command succeeds and verifies database and storage integrity', function () {
    $this->artisan('serviceflow:verify-backup')
        ->expectsOutputToContain('Starting ServiceFlow Backup & Operational Recovery Verification')
        ->expectsOutputToContain('[OK] Database connection established.')
        ->expectsOutputToContain('[OK] Storage read/write operational.')
        ->expectsOutputToContain('Backup verification completed successfully.')
        ->assertExitCode(0);
});
