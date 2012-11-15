<?php

/**
 * php-fixed-length-file-parser
 * 
 * @link       https://github.com/fanatique/php-fixed-length-file-parser A parser class for handling fixed length text files in PHP
 * @license    http://sam.zoy.org/wtfpl/COPYING DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 * @package    Fanatique
 * @subpackage Parser
 */

namespace Fanatique\Parser;

/**
 * Fixed Length File Parser
 *
 * @category   Fanatique
 * @package    Parser
 * @subpackage FixedLengthFileParser
 */
class FixedLengthFileParser implements ParserInterface
{

    /**
     * contains arrays describing start, length and name
     * for each value encoded in a line of the file to be parsed.
     *
     * @var array
     */
    protected $choppingMap = array();

    /**
     * file to be parsed
     *
     * @var string
     */
    protected $file;

    /**
     *
     * @var Closure
     */
    protected $callback = null;

    /**
     *
     * @var Closure
     */
    protected $preflightCheck = null;

    /**
     *
     * @var array
     */
    protected $content = array();

    /**
     * Expects an array with n arrays containing :
     * 'field_name', 'start', 'length'
     * 
     * => array(
     *     array('field_name' => 'id,' 'start' => 0, 'length' => 2),
     *     ...
     * )
     *
     * @param array $map
     */
    public function setChoppingMap(array $map)
    {
        $this->choppingMap = $map;
    }

    /**
     * Setter for the file to be parsed
     * @param string $pathToFile /path/to/file.dat
     */
    public function setFilePath($pathToFile)
    {
        $this->file = (string) $pathToFile;
    }

    /**
     * Setter for registering a closure that
     * evaluates if a fetched line needs to be parsed.
     * 
     * The closure needs to
     * <ul>
     * <li>accept the unparsed current line as a string
     * <li>return a boolean value indicating whether or not this line should be parsed
     * </ul>
     *
     * @param \Closure $preflightCheck
     */
    public function setPreflightCheck(\Closure $preflightCheck)
    {
        $this->preflightCheck = $preflightCheck;
    }

    /**
     * Setter method for registering a callback which handles
     * each line *after* parsing.
     * 
     * The closure needs to
     * <ul>
     * <li>accept the parsed current line as an associative array
     * <li>return an associative array in the current file's format
     * </ul>
     *
     * @param \Closure $callback
     */
    public function setCallback(\Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Returns all lines of the parsed content.
     *
     * @return array
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Main method for parsing.
     *
     * @return void
     * @throws ParserException
     */
    public function parse()
    {
        //Check for file parameter
        if (!isset($this->file)) {
            throw new ParserException('No file was specified!');
        }

        //Check for chopping map
        if (!isset($this->choppingMap)) {
            throw new ParserException('A Chopping Map MUST be specified!');
        }

        //Save pre check as local variable (as PHP does not recognize closures as class members)
        $preflightCheck = $this->preflightCheck;

        //Parse file line by line
        $this->content = array();
        $filePointer = fopen($this->file, "r");
        while (!feof($filePointer)) {
            $buffer = fgets($filePointer, 4096);

            if (!empty($buffer)) {
                // If a pre check was registered and it returns not true - the current line
                // does not need to be parsed
                if ($preflightCheck instanceof \Closure && $preflightCheck($buffer) !== true) {
                    continue;
                }

                //Pass the current string buffer
                $this->content[] = $this->parseLine($buffer);
            }
        }
        fclose($filePointer);
    }

    /**
     * Handles a single line
     *
     * @param string $buffer
     * @return array
     */
    private function parseLine($buffer)
    {
        $currentLine = array();

        //Extract each field from the current line
        for ($i = 0; $i < count($this->choppingMap); $i++) {
            $name = $this->choppingMap[$i]['field_name'];
            $currentLine[$name] = substr($buffer,
                    $this->choppingMap[$i]['start'],
                    $this->choppingMap[$i]['length']);
            $currentLine[$name] = trim($currentLine[$name]);
        }

        //Store callback as local variable (as PHP does not recognize closures as class members)
        $callback = $this->callback;

        /**
         * If a call back function was registered - apply it to the current line
         */
        if ($callback instanceof \Closure) {
            $currentLine = $callback($currentLine);
        }

        return $currentLine;
    }

}
