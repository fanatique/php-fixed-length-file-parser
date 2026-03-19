<?php

/**
 * This file is part of the php-fixed-length-file-parser package.
 *
 * @link    https://github.com/fanatique/php-fixed-length-file-parser
 * @license MIT https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

/**
 * This script illustrates how to use the fixed length file parser.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$parser = new \Fanatique\Parser\FixedLengthFileParser();

// ## 1. Preparing the parser
// Set the chopping map (aka where to extract the fields)
$parser->setChoppingMap([
    ['field_name' => 'id', 'length' => 2],
    ['field_name' => 'name', 'start' => 2, 'length' => 5],
    ['field_name' => 'team', 'length' => 5], // start is the sum of name:start(2) plus name:length(5) = 7
]);

// Set the absolute path to the file
$parser->setFilePath(__DIR__ . '/example.dat');

// ## 1a. Optional features
// Register a callable that determines if a line needs to be parsed.
// This example ignores any line whose sha1 hash is 0d4705... (which kicks out Gilly)
$linesToIgnore = ['0d470573afeae83690434c7eddd4f7b755ec5df9'];
$parser->setPreflightCheck(function (string $currentLineStr) use ($linesToIgnore): bool {
    return !in_array(sha1($currentLineStr), $linesToIgnore, true);
});

// Register a callback which is applied to each parsed line
$parser->setCallback(function (array $currentLine): array {
    $currentLine['team'] = ucwords(strtolower($currentLine['team']));
    return $currentLine;
});

// ## 2. Parse
try {
    $parser->parse();
} catch (\Fanatique\Parser\ParserException $e) {
    echo 'ERROR - ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ## 3. Get the content
var_dump($parser->getContent());
