<?php

namespace App\Updater;

use App\Exceptions\UserException;
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Components\Updater\Strategy\StrategyInterface;
use RuntimeException;
use ZipArchive;

/**
 * @phpstan-type RawRelease array{
 *   tag_name: string,
 *   assets: array{url: string, name: string}[]
 * }
 *
 * @phpstan-type RawAsset array{url: string, name: string}
 */
class PrivateGitHubReleaseStrategy extends GithubStrategy implements StrategyInterface
{
    private const ApiVersion = '2022-11-28';

    protected string $baseUrl = 'https://api.github.com/repos/bosunski/dev';

    protected const MACHINE_TYPE_MAP = [
        'arm64'  => 'arm64',
        'x86_64' => 'x86_64',
    ];

    protected const OS_TYPE_MAP = [
        'Darwin' => 'darwin',
        'Linux'  => 'linux',
    ];

    /**
     * @var null|RawAsset
     */
    protected ?array $asset = null;

    /**
     * @var null|RawRelease
     */
    protected ?array $release = null;

    protected function buildAssetName(): string
    {
        $machine = php_uname('m');
        $os = php_uname('s');

        if (! isset(static::MACHINE_TYPE_MAP[$machine])) {
            throw new RuntimeException("Unsupported machine type: $machine");
        }

        if (! isset(static::OS_TYPE_MAP[$os])) {
            throw new RuntimeException("Unsupported OS type: $os");
        }

        return strtolower(sprintf('dev-%s-%s', static::OS_TYPE_MAP[$os], static::MACHINE_TYPE_MAP[$machine]));
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

        try {
            // Trusting GitHub blindly here
            // @phpstan-ignore-next-line
            return $this->release = Http::asJson()->withHeaders([
                'X-GitHub-Api-Version' => self::ApiVersion,
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

        $assetName = "{$this->buildAssetName()}.zip";
        $this->asset = collect($assets)->first(fn (array $asset) => $asset['name'] === $assetName);

        if (! $this->asset) {
            throw new RuntimeException("Failed to find the asset: $assetName");
        }

        return $this->asset;
    }

    protected function downloadAsset(string $url, string $path): void
    {
        Http::sink($path)->withHeaders([
            'Accept'               => 'application/octet-stream',
            'X-GitHub-Api-Version' => self::ApiVersion,
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
            $execName = $this->buildAssetName();
            $tempZipPath = dirname($updater->getTempPharFile()) . DIRECTORY_SEPARATOR . $this->asset['name'];
            $this->downloadAsset($this->asset['url'], $tempZipPath);

            // Extract the downloaded asset
            $zip = new ZipArchive();
            if (! $zip->open($tempZipPath)) {
                throw new RuntimeException("Failed to open the downloaded asset: $tempZipPath");
            }

            $exePath = dirname($updater->getLocalPharFile()) . DIRECTORY_SEPARATOR . $execName;
            if (! $zip->extractTo(dirname($updater->getLocalPharFile()))) {
                throw new RuntimeException("Failed to extract the downloaded asset: $tempZipPath");
            }

            @$zip->close();

            // move the extracted files to the correct location
            if (! File::move($exePath, $updater->getTempPharFile())) {
                throw new RuntimeException("Failed to move the extracted asset to temp location: {$updater->getTempPharFile()}");
            }

            // cleanup
            File::delete($tempZipPath);
        } catch(RequestException $e) {
            if (isset($tag) && $e->response->notFound()) {
                throw new UserException("Tag $tag not found on GitHub. Please check the tag name and try again.");
            }

            throw $e;
        }
    }
}
