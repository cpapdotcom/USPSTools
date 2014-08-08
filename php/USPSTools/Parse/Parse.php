<?php
/**
 * This class the base class for parsing USPS files.
 *
 * For more information:
 * @link https://epf.usps.gov/
 * @link https://ribbs.usps.gov/
 *
 * @author Ben Dauphinee <ben.dauphinee@cpap.com>
 * @link https://github.com/cpapdotcom/USPSTools
 * @version 1.0
 * @license MIT
 */

namespace USPSTools;

/**
 * This class the base class for parsing USPS files.
 */
class Parse
{
    /**
     * A variable to store the length of a line for each file type.
     */
    protected static $lineLength = 0;

    /**
     * Processes an entire file worth of records.
     *
     * @param string $line A line of data from the input file.
     * @param string $seekingRecordType [Optional] A record type to specifically seek and return. If
     *      set and current record does not match this, we will not spend the cycles to parse the
     *      rest of the record.
     */
    public static function parseFile ($filename, $seekingRecordType = '', $startLine = 0, $numberOfLines = 0)
    {
        $return = array(
            'foundCount' => 0,
            'lineCount' => 0,
            'records' => array(),
        );

        // Make sure the file we want to read actually exists
        if (file_exists($filename) == false) {
            throw new Exception('File to read does not exist');
        }

        // Open up the file to process
        $fp = fopen($filename, 'r');

        // If we have a start location, seek to it
        if ($startLine > 0) {
            fseek($fp, ($startLine * static::$lineLength));
        }

        $foundCount = 0;
        $lineCount = $startLine;
        while (feof($fp) == false) {
            // If we got enough lines, break
            if ($numberOfLines > 0 && $foundCount == $numberOfLines) {
                break;
            }

            $lineCount++;

            // Read the file for the next line to parse
            $lineText = fread($fp, static::$lineLength);

            // Parse this line
            $thisRecord = static::parseLine($lineText, $seekingRecordType);

            // If we get a record back, add it to the return
            if (isset($thisRecord['record']) == true) {
                $return['records'][] = $thisRecord;
                $foundCount++;
            }
        }

        $return['foundCount'] = $foundCount;

        // Return the line count, so we can start from this position if we need to
        $return['lineCount'] = $lineCount;

        return $return;
    }
}

