<?php

namespace App\Contracts\Solution;

class BaseSolution implements Solution
{
    /**
     * @var array<string, string>
     */
    protected array $links = [];

    public function __construct(protected string $title, protected string $description)
    {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function links(): array
    {
        return $this->links;
    }

    public function withLink(string $title, string $url): self
    {
        $this->links[$title] = $url;

        return $this;
    }

    /**
     * @param array<string, string> $links
     * @return BaseSolution
     */
    public function withLinks(array $links): self
    {
        $this->links = array_merge($this->links, $links);

        return $this;
    }

    public function withDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
}
