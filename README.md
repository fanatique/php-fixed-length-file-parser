php-fixed-length-file-parser
============================

A parser class for handling fixed length text files in PHP.

Fixed Length Files (aka poor man's CSV) are plain text files with one data set per row
_but without any delimiter_.

    01Amy  BLUES
    02Bob  REDS 
    ...

## Features ##

This class provides a rather comfortable way to handle this type of file on PHP.

You can:

- register a pre flight check to determine whether or not a row has to be parsed
- register a callback to handle each line
- register a chopping map which transforms each row into an associative array


## Usage ##

The following example shows how to transform a fixed length file into an associative array.
The working example can be found in `example/parsing.php`.

    $parser = new \Fanatique\Parser\FixedLengthFileParser();

    //Set the chopping map (aka where to extract the fields)
    $parser->setChoppingMap(array(
      array('field_name' => 'id', 'field_type' => 'int', 'start' => 0, 'length' => 2),
      array('field_name' => 'name', 'start' => 2, 'length' => 5),
      array('field_name' => 'team', 'start' => 7, 'length' => 5),
    ));

``field_name`` and ``length`` are required; ``start`` and ``field_type`` are optional parameters.
If ``start`` is omitted, it will be set to the ``start`` plus ``length`` value of the previous map entry.

``field_type`` can currently be one of three values: `string`, `int` or `float`.
`string` is the default, and nothing extra is done. Otherwise, the specified value is casted to `(int)` or `(float)` respectively.

    //Set the absolute path to the file
    $parser->setFilePath(__DIR__ . '/example.dat');
    
    //Parse the file
    try {
      $parser->parse();
    } catch (\Fanatique\Parser\ParserException $e) {
      echo 'ERROR - ' . $e->getMessage() . PHP_EOL;
      exit(1);
    }
    
    //Get the content
    var_dump($parser->getContent());

### Registering a pre flight check ###

A pre flight check can be registered to be applied to each row *before* it is parsed.
The closure needs to return a boolean value with:

- `false`: line needs not to be parsed
- `true`: parse line 

This example ignores any line which md5 sum is `f23f81318ef24f1ba4df4781d79b7849`:

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

### Registering a callback ###

Finally you can register a callback which is applied to each parsed line and allows you to process it.
The closure gets the parsed line as an array and it is expected to return an array of the same format.

    $parser->setCallback(function(array $currentLine) {
                $currentLine['team'] = ucwords(strtolower($currentLine['team']));
                return $currentLine;
            }
    );
