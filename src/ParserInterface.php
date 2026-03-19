<?php

declare(strict_types=1);

namespace Fanatique\Parser;

/**
 * Parser Interface
 *
 * Contract for file parsers.
 */
interface ParserInterface
{
    /**
     * Parse the configured file.
     *
     * @throws ParserException
     */
    public function parse(): void;
}
