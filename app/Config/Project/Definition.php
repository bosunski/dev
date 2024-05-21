<?php

namespace App\Config\Project;

use App\Exceptions\UserException;
use Stringable;

class Definition implements Stringable
{
    public readonly string $repo;

    public readonly string|null $ref;

    public readonly string $url;

    public readonly string $source;

    public function __construct(public readonly string $project, public readonly string $host = 'github.com')
    {
        [$ref, $fullName, $cloneUrl, $source] = $this->parse($this->project);

        $this->ref = $ref;
        $this->repo = $fullName;
        $this->url = $cloneUrl;
        $this->source = $source;
    }

    public function __toString(): string
    {
        return $this->repo;
    }

    /**
     * @param string $projectUrl
     * @return array{string|null, string, string, string}
     */
    protected function parse(string $projectUrl): array
    {
        if (empty($projectUrl)) {
            throw new UserException('Cannot provide an empty project name');
        }

        $projectUrl = str_replace('.git', '', $projectUrl);
        $parsedUrl = parse_url($projectUrl);
        if ($parsedUrl === false) {
            throw new UserException("Malformed project URL $projectUrl cannot be parsed");
        }

        if (! isset($parsedUrl['path'])) {
            throw new UserException("Malformed project URL $projectUrl cannot be parsed");
        }

        $path = $parsedUrl['path'];
        $userRepo = explode('/', trim($path, '/'));

        if (count($userRepo) != 2) {
            throw new UserException("Malformed project URL $projectUrl cannot be parsed");
        }

        $ref = null;
        if (isset($parsedUrl['fragment'])) {
            $ref = $parsedUrl['fragment'];
        }

        $fullName = implode('/', $userRepo);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $cloneUrl = "$scheme://";
        if (isset($parsedUrl['host'])) {
            $cloneUrl .= $parsedUrl['host'];
        } else {
            $cloneUrl .= $this->host;
        }

        $cloneUrl .= '/' . $fullName . '.git';

        return [$ref, $fullName, $cloneUrl, $parsedUrl['host'] ?? $this->host];
    }
}
