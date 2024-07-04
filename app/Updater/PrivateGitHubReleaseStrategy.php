<?php

namespace App\Updater;

use App\Exceptions\UserException;
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * @phpstan-type RawRelease array{
 *   tag_name: string,
 *   assets: array{url: string, name: string}[]
 * }
 *
 * @phpstan-type RawAsset array{url: string, name: string}
 */
class PrivateGitHubReleaseStrategy extends GithubStrategy
{
    protected string $baseUrl = 'https://api.github.com/repos/bosunski/dev';

    protected const MACHINE_TYPE_MAP = [
        'arm64' => 'arm64',
    ];

    protected const OS_TYPE_MAP = [
        'Darwin' => 'macOS',
    ];

    /**
     * @var null|RawAsset
     */
    protected ?array $asset = null;

    /**
     * @var null|RawRelease
     */
    protected ?array $release = null;

    protected function buildAssetName(string $version): string
    {
        $machine = php_uname('m');
        $os = php_uname('s');

        if (! isset(static::MACHINE_TYPE_MAP[$machine])) {
            throw new RuntimeException("Unsupported machine type: $machine");
        }

        if (! isset(static::OS_TYPE_MAP[$os])) {
            throw new RuntimeException("Unsupported OS type: $os");
        }

        $os = static::OS_TYPE_MAP[$os];
        $arch = static::MACHINE_TYPE_MAP[$machine];

        return "dev-$version-$os-$arch";
    }

    protected function getLatestReleaseUrl(?string $tag = null): string
    {
        if (! $tag) {
            return "$this->baseUrl/releases/latest";
        }

        return "$this->baseUrl/releases/tags/$tag";
    }

    /**
     * @return RawRelease
     */
    protected function getLatestReleaseFromGitHub(?string $tag = null): array
    {
        if ($this->release) {
            return $this->release;
        }

        $token = env('GITHUB_TOKEN');
        if (! $token || ! is_string($token)) {
            throw new RuntimeException('GITHUB_TOKEN must be set and be a string');
        }

        try {
            // Trusting GitHub blindly here
            // @phpstan-ignore-next-line
            return $this->release = Http::asJson()->withHeaders([
                'Authorization'        => "Bearer $token",
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->get($this->getLatestReleaseUrl($tag))->throw()->json();
        } catch(RequestException $e) {
            if ($e->response->notFound()) {
                throw new UserException("Tag $tag not found on GitHub. Please check the tag name and try again.");
            }

            throw $e;
        }
    }

    /**
     * @return RawAsset
     */
    protected function getLatestReleaseDetailsFromGitHub(?string $tag = null): array
    {
        if ($this->asset) {
            return $this->asset;
        }

        if (! $this->release) {
            $this->release = $this->getLatestReleaseFromGitHub($tag);
        }

        $assets = $this->release['assets'];
        $version = $this->release['tag_name'];
        if (! $version) {
            throw new RuntimeException('Failed to get the latest release version');
        }

        $assetName = $this->buildAssetName($version);
        $this->asset = collect($assets)->first(fn (array $asset) => $asset['name'] === $assetName);

        if (! $this->asset) {
            throw new RuntimeException("Failed to find the asset: $assetName");
        }

        return $this->asset;
    }

    protected function downloadAsset(string $url, string $path): void
    {
        $token = env('GITHUB_TOKEN');
        if (! $token || ! is_string($token)) {
            throw new RuntimeException('GITHUB_TOKEN must be set and be a string');
        }

        Http::sink($path)->withHeaders([
            'Accept'               => 'application/octet-stream',
            'Authorization'        => "Bearer $token",
            'X-GitHub-Api-Version' => '2022-11-28',
        ])->get($url)->throw();

        if (! file_exists($path)) {
            throw new RuntimeException("Failed to download the asset from $url");
        }
    }

    public function getCurrentRemoteVersion(Updater $updater): string
    {
        if ($updater instanceof PharUpdater) {
            $tag = $updater->getTag();
        }

        if (! $this->release) {
            $this->release = $this->getLatestReleaseFromGitHub($tag ?? null);
        }

        return $this->release['tag_name'];
    }

    public function download(Updater $updater): void
    {
        if ($updater instanceof PharUpdater) {
            $tag = $updater->getTag();
        }

        if (! $this->asset) {
            $this->asset = $this->getLatestReleaseDetailsFromGitHub($tag ?? null);
        }

        try {
            $this->downloadAsset($this->asset['url'], $updater->getTempPharFile());
        } catch(RequestException $e) {
            if ($e->response->notFound()) {
                throw new UserException("Tag $tag not found on GitHub. Please check the tag name and try again.");
            }

            throw $e;
        }
    }
}
