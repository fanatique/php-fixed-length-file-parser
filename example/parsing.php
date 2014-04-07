<?php

/**
 * php-fixed-length-file-parser
 * 
 * This script illustrates how to use the fixed length file parser.
 * 
 * @link       https://github.com/fanatique/php-fixed-length-file-parser A parser class for handling fixed length text files in PHP
 * @license    http://sam.zoy.org/wtfpl/COPYING DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 * @package    Fanatique
 * @subpackage Parser
 */
require_once __DIR__ . '/../library/Fanatique/Parser/ParserInterface.php';
require_once __DIR__ . '/../library/Fanatique/Parser/ParserException.php';
require_once __DIR__ . '/../library/Fanatique/Parser/FixedLengthFileParser.php';

$parser = new \Fanatique\Parser\FixedLengthFileParser();

//## 1. Preparing the parser
//Set the chopping map (aka where to extract the fields)
$parser->setChoppingMap(array(
    array('field_name' => 'id', 'length' => 2),
    array('field_name' => 'name', 'start'=>2, 'length' => 5),
    array('field_name' => 'team', 'length' => 5), // start is the sum of name:start(2) plus name:length(5) = 7
));

//Set the absolute path to the file
$parser->setFilePath(__DIR__ . '/example.dat');

//## 1a. optional features
//Register a closure that determines if a line needs to be parsed
//This example ignores any line which md5 sum is f23f81318ef24f1ba4df4781d79b7849 (which kicks out Gilly)
$linesToIgnore = array('f23f81318ef24f1ba4df4781d79b7849');
$parser->setPreflightCheck(function($currentLineStr) use($linesToIgnore) {
            if (in_array(md5($currentLineStr), $linesToIgnore)) {
                //Ignore line
                $ret = false;
            } else {
                //Parse line
                $ret = true;
            }
            return $ret;
        }
);


//Register a callback which is applied to each parsed line
$parser->setCallback(function(array $currentLine) {
            $currentLine['team'] = ucwords(strtolower($currentLine['team']));
            return $currentLine;
        }
);

//## 2. Parse
try {
    $parser->parse();
} catch (\Fanatique\Parser\ParserException $e) {
    echo 'ERROR - ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

//## 3. Get the content

var_dump($parser->getContent());
