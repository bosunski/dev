<?php

namespace App\Exceptions\Config;

use App\Exceptions\UserException;
use NunoMaduro\Collision\Highlighter;
use Symfony\Component\Yaml\Exception\ParseException;

class InvalidConfigException extends UserException
{
    protected Highlighter $highlighter;

    public function __construct(protected ParseException $exception)
    {
        parent::__construct($exception->getMessage(), $exception->getCode(), $exception);
        $this->highlighter = new Highlighter();
    }

    /**
     * Renders the editor containing the code that was the
     * origin of the exception.
     */
    public function getSourceHighlight(): string
    {
        return $this->highlighter->highlight(
            (string) file_get_contents($this->exception->getParsedFile()),
            $this->exception->getParsedLine()
        );
    }
}
