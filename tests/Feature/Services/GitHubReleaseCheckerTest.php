<?php

use App\Services\GitHubReleaseChecker;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

it('fetches the latest published release from a public GitHub repository', function () {
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/acme/service-flow/releases/latest' => Http::response([
            'tag_name' => 'v1.2.0',
            'name' => 'Version 1.2.0',
            'html_url' => 'https://github.com/acme/service-flow/releases/tag/v1.2.0',
            'published_at' => '2026-10-01T10:00:00Z',
        ]),
    ]);

    $release = (new GitHubReleaseChecker)->latestRelease('https://github.com/acme/service-flow');

    expect($release)->toBe([
        'tag_name' => 'v1.2.0',
        'name' => 'Version 1.2.0',
        'html_url' => 'https://github.com/acme/service-flow/releases/tag/v1.2.0',
        'published_at' => '2026-10-01T10:00:00Z',
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/acme/service-flow/releases/latest');
});

it('returns no release when the repository has no published releases', function () {
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/acme/service-flow/releases/latest' => Http::response([], 404),
    ]);

    expect((new GitHubReleaseChecker)->latestRelease('https://github.com/acme/service-flow'))->toBeNull();
});

it('throws when GitHub returns an unsuccessful response other than not found', function () {
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/acme/service-flow/releases/latest' => Http::response([], 503),
    ]);

    expect(fn () => (new GitHubReleaseChecker)->latestRelease('https://github.com/acme/service-flow'))
        ->toThrow(RequestException::class);
});

it('rejects a repository URL outside GitHub before making an HTTP request', function () {
    Http::preventStrayRequests();

    expect(fn () => (new GitHubReleaseChecker)->latestRelease('https://example.com/acme/service-flow'))
        ->toThrow(InvalidArgumentException::class);
});
