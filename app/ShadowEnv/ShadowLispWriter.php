<?php

namespace App\ShadowEnv;

use RuntimeException;

class ShadowLispWriter
{
    public function __construct(private readonly string $path)
    {
        if (! is_file($this->path)) {
            throw new RuntimeException("File $this->path does not exist for writing");
        }
    }

    public function envSet(string $key, string $value): void
    {
        $line = sprintf('(env/set "%s" "%s")', $key, $value);
        $sedRegex = sprintf('#\(env/set "%s" .*\)#m', $key);

        $oldContent = $this->content();
        if (! preg_match($sedRegex, $oldContent)) {
            $this->append($line);

            return;
        }

        if (! $newContent = preg_replace($sedRegex, $line, $oldContent)) {
            throw new RuntimeException('Failed to write env variable to file: ' . $this->path);
        }

        $this->putContent($newContent);
    }

    private function append(string $line): void
    {
        $result = file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND);
        if (! $result) {
            throw new RuntimeException("Failed to append line to file: $this->path");
        }
    }

    private function putContent(string $content): void
    {
        $result = file_put_contents($this->path, $content);
        if (! $result) {
            throw new RuntimeException("Failed to write content to file: $this->path");
        }
    }

    private function content(): string
    {
        $content = file_get_contents($this->path);
        if (! $content) {
            throw new RuntimeException("Failed to read content of file: $this->path");
        }

        return $content;
    }

    public function prependPath(string $path): void
    {
        $line = sprintf('(env/prepend-to-pathlist "PATH" "%s")', $path);
        $sedRegex = sprintf('#\(env/prepend-to-pathlist "PATH" "%s"\)#m', $path);

        $oldContent = $this->content();
        preg_match($sedRegex, $oldContent, $matches);

        if (! empty($matches)) {
            return;
        }

        $this->append($line);
    }
}
