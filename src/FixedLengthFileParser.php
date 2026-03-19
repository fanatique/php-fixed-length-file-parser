<?php

/**
 * This file is part of the php-fixed-length-file-parser package.
 *
 * @link    https://github.com/fanatique/php-fixed-length-file-parser
 * @license MIT https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace Fanatique\Parser;

use SplFileObject;

/**
 * Fixed Length File Parser
 *
 * Parses fixed-length text files where each row contains fields at defined
 * positions and lengths, without any delimiter.
 */
class FixedLengthFileParser implements ParserInterface
{
    /**
     * Arrays describing field_name, start, and length for each value
     * encoded in a line of the file to be parsed.
     *
     * @var array<int, array{field_name: string, start?: int, length: int, align?: 'left'|'right'|'both'}>
     */
    protected array $choppingMap = [];

    /**
     * Absolute path to the file to be parsed.
     */
    protected ?string $file = null;

    /**
     * Optional callback applied to each line after parsing.
     *
     * Receives the parsed line as an associative array and must return
     * an associative array in the same format.
     *
     * @var ?callable(array<string, string>): array<string, string>
     */
    protected mixed $callback = null;

    /**
     * Optional pre-flight check applied to each raw line before parsing.
     *
     * Receives the raw line string and must return true if the line
     * should be parsed, false to skip it.
     *
     * @var ?callable(string): bool
     */
    protected mixed $preflightCheck = null;

    /**
     * Parsed content (array of associative arrays).
     *
     * @var array<int, array<string, string>>
     */
    protected array $content = [];

    /**
     * Set the chopping map that defines how to extract fields from each line.
     *
     * Each entry must contain 'field_name' and 'length'. The 'start' key is
     * optional — if omitted, it is calculated from the previous entry's
     * start + length. The 'align' key is optional and can be 'left', 'right',
     * or 'both' (default is 'both').
     *
     * @param array<int, array{field_name: string, start?: int, length: int, align?: 'left'|'right'|'both'}> $map
     */
    public function setChoppingMap(array $map): void
    {
        $this->choppingMap = $map;
    }

    /**
     * Set the absolute path to the file to be parsed.
     */
    public function setFilePath(string $pathToFile): void
    {
        $this->file = $pathToFile;
    }

    /**
     * Register a pre-flight check to determine if a line should be parsed.
     *
     * The callable receives the raw line as a string and must return
     * a boolean: true to parse, false to skip.
     */
    public function setPreflightCheck(callable $preflightCheck): void
    {
        $this->preflightCheck = $preflightCheck;
    }

    /**
     * Register a callback to process each line after parsing.
     *
     * The callable receives the parsed line as an associative array
     * and must return an associative array in the same format.
     */
    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    /**
     * Return all lines of the parsed content.
     *
     * @return array<int, array<string, string>>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * Parse the configured file line by line.
     *
     * Uses SplFileObject for memory-efficient, line-by-line reading,
     * which is safe for very large files.
     *
     * @throws ParserException If no file path was set.
     */
    public function parse(): void
    {
        if ($this->file === null) {
            throw new ParserException('No file was specified!');
        }

        $this->content = [];
        $file = new SplFileObject($this->file, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);

        foreach ($file as $line) {
            if (!is_string($line) || $line === '') {
                continue;
            }

            // Apply pre-flight check if registered
            if ($this->preflightCheck !== null && ($this->preflightCheck)($line) !== true) {
                continue;
            }

            $this->content[] = $this->parseLine($line);
        }
    }

    /**
     * Parse a single line according to the chopping map.
     *
     * @return array<string, string>
     */
    protected function parseLine(string $buffer): array
    {
        $currentLine = [];
        $lastPosition = 0;
        $mapEntryCount = count($this->choppingMap);

        for ($i = 0; $i < $mapEntryCount; $i++) {
            $start = $this->choppingMap[$i]['start'] ?? $lastPosition;

            // Reset position at last entry, otherwise advance
            $lastPosition = ($i === $mapEntryCount - 1)
                ? 0
                : $start + $this->choppingMap[$i]['length'];

            $name = $this->choppingMap[$i]['field_name'];
            $rawValue = substr($buffer, $start, $this->choppingMap[$i]['length']);

            $align = $this->choppingMap[$i]['align'] ?? 'both';

            $currentLine[$name] = match ($align) {
                'left' => rtrim($rawValue),
                'right' => ltrim($rawValue),
                default => trim($rawValue),
            };
        }

        // Apply callback if registered
        if ($this->callback !== null) {
            $currentLine = ($this->callback)($currentLine);
        }

        return $currentLine;
    }
}
