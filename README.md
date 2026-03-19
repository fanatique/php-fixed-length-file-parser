# php-fixed-length-file-parser

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://www.php.net/)
[![CI](https://github.com/fanatique/php-fixed-length-file-parser/actions/workflows/ci.yml/badge.svg)](https://github.com/fanatique/php-fixed-length-file-parser/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A parser class for handling fixed length text files in PHP.

Fixed Length Files (aka poor man's CSV) are plain text files with one data set per row
_but without any delimiter_:

```
01Amy  BLUES
02Bob  REDS
```

## Installation

```bash
composer require fanatique/php-fixed-length-file-parser
```

## Features

- Register a **chopping map** to define field positions and lengths
- Register a **pre-flight check** to filter lines before parsing
- Register a **callback** to transform each parsed line
- Supports any `callable` (closures, invokable objects, static methods, etc.)
- Memory-efficient line-by-line reading — safe for very large files

## Usage

The following example shows how to transform a fixed length file into an associative array.
A working example can be found in [`example/parsing.php`](example/parsing.php).

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$parser = new \Fanatique\Parser\FixedLengthFileParser();

// Set the chopping map (aka where to extract the fields)
$parser->setChoppingMap([
    ['field_name' => 'id', 'start' => 0, 'length' => 2],
    ['field_name' => 'name', 'start' => 2, 'length' => 5],
    ['field_name' => 'team', 'start' => 7, 'length' => 5],
]);

// Set the absolute path to the file
$parser->setFilePath(__DIR__ . '/example.dat');

// Parse the file
try {
    $parser->parse();
} catch (\Fanatique\Parser\ParserException $e) {
    echo 'ERROR - ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Get the content
var_dump($parser->getContent());
```

`field_name` and `length` are required. `start` is optional — if omitted, it is
calculated from the previous entry's `start + length`.

### Registering a pre-flight check

A pre-flight check can be registered to filter each row _before_ it is parsed.
The callable receives the raw line as a string and must return a boolean:

- `true` → parse the line
- `false` → skip the line

```php
$parser->setPreflightCheck(function (string $line): bool {
    // Skip lines starting with a comment character
    return !str_starts_with($line, '#');
});
```

### Registering a callback

A callback is applied to each line _after_ parsing. It receives the parsed line
as an associative array and must return an array of the same format:

```php
$parser->setCallback(function (array $line): array {
    $line['team'] = ucwords(strtolower($line['team']));
    return $line;
});
```

### Using invokable objects

Since the parser accepts any `callable`, you can use invokable objects for
more complex or reusable logic:

```php
class TeamNormalizer
{
    public function __invoke(array $line): array
    {
        $line['team'] = ucwords(strtolower($line['team']));
        return $line;
    }
}

$parser->setCallback(new TeamNormalizer());
```

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan
```

## License

MIT — see [LICENSE](LICENSE) for details.
