<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use UnexpectedValueException;

class GitHubReleaseChecker
{
    /**
     * @return array{tag_name: string, name: string, html_url: string, published_at: string|null}|null
     */
    public function latestRelease(string $repositoryUrl): ?array
    {
        $repository = $this->repositoryPath($repositoryUrl);

        $release = Cache::remember(
            'github.latest_release.'.$repository,
            now()->addHour(),
            function () use ($repository): array|false {
                $response = Http::acceptJson()
                    ->withUserAgent((string) config('app.name', 'ServiceFlow'))
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get("https://api.github.com/repos/{$repository}/releases/latest");

                if ($response->notFound()) {
                    return false;
                }

                $release = $response->throw()->json();

                if (
                    ! is_array($release)
                    || ! is_string($release['tag_name'] ?? null)
                    || $release['tag_name'] === ''
                    || ! is_string($release['html_url'] ?? null)
                ) {
                    throw new UnexpectedValueException('GitHub returned an invalid latest release response.');
                }

                $releaseUrl = parse_url($release['html_url']);
                $expectedReleasePath = '/'.$repository.'/releases/tag/';

                if (
                    ! is_array($releaseUrl)
                    || ($releaseUrl['scheme'] ?? null) !== 'https'
                    || ($releaseUrl['host'] ?? null) !== 'github.com'
                    || isset($releaseUrl['user'])
                    || isset($releaseUrl['pass'])
                    || isset($releaseUrl['port'])
                    || ! str_starts_with($releaseUrl['path'] ?? '', $expectedReleasePath)
                ) {
                    throw new UnexpectedValueException('GitHub returned an invalid release URL.');
                }

                return [
                    'tag_name' => $release['tag_name'],
                    'name' => is_string($release['name'] ?? null) && $release['name'] !== ''
                        ? $release['name']
                        : $release['tag_name'],
                    'html_url' => $release['html_url'],
                    'published_at' => is_string($release['published_at'] ?? null)
                        ? $release['published_at']
                        : null,
                ];
            },
        );

        return $release === false ? null : $release;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function repositoryPath(string $repositoryUrl): string
    {
        if (! preg_match(
            '/\Ahttps:\/\/github\.com\/([A-Za-z0-9_.-]+)\/([A-Za-z0-9_.-]+)(?:\.git)?\/?\z/i',
            trim($repositoryUrl),
            $matches,
        )) {
            throw new InvalidArgumentException('The repository URL must point to a public GitHub repository.');
        }

        $repositoryName = preg_replace('/\.git$/i', '', $matches[2]);

        return $matches[1].'/'.$repositoryName;
    }
}
