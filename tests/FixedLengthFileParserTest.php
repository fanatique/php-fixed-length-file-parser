<?php

/**
 * This file is part of the php-fixed-length-file-parser package.
 *
 * @link    https://github.com/fanatique/php-fixed-length-file-parser
 * @license MIT https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace Fanatique\Parser\Tests;

use Fanatique\Parser\FixedLengthFileParser;
use Fanatique\Parser\ParserException;
use PHPUnit\Framework\TestCase;

final class FixedLengthFileParserTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/fixtures/';

    private function createStandardMap(): array
    {
        return [
            ['field_name' => 'id', 'start' => 0, 'length' => 2],
            ['field_name' => 'name', 'start' => 2, 'length' => 5],
            ['field_name' => 'team', 'start' => 7, 'length' => 5],
        ];
    }

    private function createConfiguredParser(?array $map = null, ?string $file = null): FixedLengthFileParser
    {
        $parser = new FixedLengthFileParser();
        $parser->setChoppingMap($map ?? $this->createStandardMap());
        $parser->setFilePath($file ?? self::FIXTURES_DIR . 'example.dat');
        return $parser;
    }

    // -------------------------------------------------------
    // Basic parsing
    // -------------------------------------------------------

    public function testParseBasicFile(): void
    {
        $parser = $this->createConfiguredParser();
        $parser->parse();

        $content = $parser->getContent();
        $this->assertCount(8, $content);
        $this->assertSame('01', $content[0]['id']);
        $this->assertSame('Amy', $content[0]['name']);
        $this->assertSame('BLUES', $content[0]['team']);
    }

    public function testParseWithAutoStart(): void
    {
        $map = [
            ['field_name' => 'id', 'length' => 2],
            ['field_name' => 'name', 'length' => 5],
            ['field_name' => 'team', 'length' => 5],
        ];

        $parser = $this->createConfiguredParser($map);
        $parser->parse();

        $content = $parser->getContent();
        $this->assertCount(8, $content);
        $this->assertSame('01', $content[0]['id']);
        $this->assertSame('Amy', $content[0]['name']);
        $this->assertSame('BLUES', $content[0]['team']);
    }

    public function testParseWithExplicitStart(): void
    {
        $parser = $this->createConfiguredParser();
        $parser->parse();

        $content = $parser->getContent();
        $this->assertSame('Bob', $content[1]['name']);
        $this->assertSame('REDS', $content[1]['team']);
    }

    public function testParseWithMixedStartValues(): void
    {
        $map = [
            ['field_name' => 'id', 'length' => 2],
            ['field_name' => 'name', 'start' => 2, 'length' => 5],
            ['field_name' => 'team', 'length' => 5], // auto: 2+5=7
        ];

        $parser = $this->createConfiguredParser($map);
        $parser->parse();

        $content = $parser->getContent();
        $this->assertSame('Chuck', $content[2]['name']);
        $this->assertSame('BLUES', $content[2]['team']);
    }

    public function testAllRowsParsedCorrectly(): void
    {
        $parser = $this->createConfiguredParser();
        $parser->parse();

        $content = $parser->getContent();

        $expectedNames = ['Amy', 'Bob', 'Chuck', 'Dick', 'Ethel', 'Fred', 'Gilly', 'Hank'];
        $actualNames = array_column($content, 'name');
        $this->assertSame($expectedNames, $actualNames);
    }

    // -------------------------------------------------------
    // Pre-flight check
    // -------------------------------------------------------

    public function testPreflightCheckFiltersLines(): void
    {
        $parser = $this->createConfiguredParser();
        $parser->setPreflightCheck(function (string $line): bool {
            // Only parse lines starting with '01' or '02'
            return str_starts_with($line, '01') || str_starts_with($line, '02');
        });
        $parser->parse();

        $content = $parser->getContent();
        $this->assertCount(2, $content);
        $this->assertSame('Amy', $content[0]['name']);
        $this->assertSame('Bob', $content[1]['name']);
    }

    public function testPreflightCheckRejectingAllLines(): void
    {
        $parser = $this->createConfiguredParser();
        $parser->setPreflightCheck(fn(string $line): bool => false);
        $parser->parse();

        $this->assertSame([], $parser->getContent());
    }

    // -------------------------------------------------------
    // Callback
    // -------------------------------------------------------

    public function testCallbackTransformsLines(): void
    {
        $parser = $this->createConfiguredParser();
        $parser->setCallback(function (array $line): array {
            $line['team'] = strtolower($line['team']);
            return $line;
        });
        $parser->parse();

        $content = $parser->getContent();
        $this->assertSame('blues', $content[0]['team']);
        $this->assertSame('reds', $content[1]['team']);
    }

    public function testCallbackCanAddFields(): void
    {
        $parser = $this->createConfiguredParser();
        $parser->setCallback(function (array $line): array {
            $line['full'] = $line['id'] . ' - ' . $line['name'];
            return $line;
        });
        $parser->parse();

        $this->assertSame('01 - Amy', $parser->getContent()[0]['full']);
    }

    // -------------------------------------------------------
    // Pre-flight check + Callback combined
    // -------------------------------------------------------

    public function testPreflightCheckAndCallbackCombined(): void
    {
        $parser = $this->createConfiguredParser();

        // Only BLUES team lines
        $parser->setPreflightCheck(fn(string $line): bool => str_contains($line, 'BLUES'));

        // Lowercase the team name
        $parser->setCallback(function (array $line): array {
            $line['team'] = ucwords(strtolower($line['team']));
            return $line;
        });

        $parser->parse();
        $content = $parser->getContent();

        $this->assertCount(5, $content); // 5 BLUES members
        foreach ($content as $row) {
            $this->assertSame('Blues', $row['team']);
        }
    }

    // -------------------------------------------------------
    // Callable types (not just Closure)
    // -------------------------------------------------------

    public function testInvokableObjectAsCallback(): void
    {
        $transformer = new class {
            public function __invoke(array $line): array
            {
                $line['name'] = strtoupper($line['name']);
                return $line;
            }
        };

        $parser = $this->createConfiguredParser();
        $parser->setCallback($transformer);
        $parser->parse();

        $this->assertSame('AMY', $parser->getContent()[0]['name']);
    }

    public function testInvokableObjectAsPreflightCheck(): void
    {
        $filter = new class {
            public function __invoke(string $line): bool
            {
                return str_starts_with($line, '01');
            }
        };

        $parser = $this->createConfiguredParser();
        $parser->setPreflightCheck($filter);
        $parser->parse();

        $this->assertCount(1, $parser->getContent());
        $this->assertSame('Amy', $parser->getContent()[0]['name']);
    }

    // -------------------------------------------------------
    // Error handling
    // -------------------------------------------------------

    public function testThrowsExceptionWithoutFile(): void
    {
        $parser = new FixedLengthFileParser();
        $parser->setChoppingMap($this->createStandardMap());

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No file was specified!');
        $parser->parse();
    }

    public function testThrowsExceptionForMissingFile(): void
    {
        $parser = $this->createConfiguredParser(file: self::FIXTURES_DIR . 'nonexistent.dat');

        $this->expectException(\RuntimeException::class);
        $parser->parse();
    }

    // -------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------

    public function testEmptyFile(): void
    {
        $parser = $this->createConfiguredParser(file: self::FIXTURES_DIR . 'empty.dat');
        $parser->parse();

        $this->assertSame([], $parser->getContent());
    }

    public function testGetContentBeforeParse(): void
    {
        $parser = new FixedLengthFileParser();
        $this->assertSame([], $parser->getContent());
    }

    public function testParseCanBeCalledMultipleTimes(): void
    {
        $parser = $this->createConfiguredParser();

        $parser->parse();
        $this->assertCount(8, $parser->getContent());

        // Second parse should replace, not append
        $parser->parse();
        $this->assertCount(8, $parser->getContent());
    }

    public function testEmptyChoppingMapReturnsEmptyRows(): void
    {
        $parser = $this->createConfiguredParser(map: []);
        $parser->parse();

        $content = $parser->getContent();
        // Each line produces an empty array
        foreach ($content as $row) {
            $this->assertSame([], $row);
        }
    }
}
