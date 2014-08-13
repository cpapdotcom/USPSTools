#!/usr/local/bin/php
<?php
/**
 * USPSTools Library provided by CPAP.com
 *
 * @author Ben Dauphinee <ben.dauphinee@cpap.com>
 * @link https://github.com/cpapdotcom/USPSTools
 * @version 1.0
 * @license MIT
 */

/**
 * This script can be used to convert raw AIS CS215N text files into MySQL type SQL INSERT statements.
 */
require_once realpath(dirname(__FILE__)) . '/../../USPSTools/Parse/Parse.php';
require_once realpath(dirname(__FILE__)) . '/../../USPSTools/Parse/AIS/CS215N.php';

if (count($argv) == 1) {
    // Output a help message
    echo 'CS215N To SQL Insert Generator' . "\n"
        . 'Usage: ./AIS_CS215N_To_SQL.php -f <File To Parse> -r <Record Type> [Other Options]' . "\n"
        . "\n"
        . 'Required Parameters'
        . "\n"
        . '  -f <File To Parse>' . "\n"
        . '      The path to the file to parse into SQL.' . "\n"
        . "\n"
        . '  -r <Record Type>' . "\n"
        . '      The type of record to parse. Currently supported are [A]lias records and [D]etail records.' . "\n"
        . "\n"
        . 'Optional Parameters'
        . "\n"
        . '  -s <Start Line>' . "\n"
        . '      The line to start at. Default is 0 (start of file).' . "\n"
        . "\n"
        . '  -n <Number Of Lines>' . "\n"
        . '      The number of lines to parse and return. If not set, defaults to all.' . "\n"
        . "\n"
        . '  -o <Output Files>' . "\n"
        . '      The file to output SQL inserts to.' . "\n"
        . "\n"
        . '  --return-count' . "\n"
        . '      This option outputs the number of records the parser found.' . "\n"
        . "\n"
        . '  --return-position' . "\n"
        . '      This option outputs the last position we have read after.' . "\n";

    die();
} else {
    $shortopts = 'f:r:s:n:o:';
    $longopts = array(
        'return-count',
        'return-position',
    );

    $cmdOptions = getopt($shortopts, $longopts);

    // Make sure we have a file set
    if (isset($cmdOptions['f']) == true) {
        $file = $cmdOptions['f'];
    } else {
        die('No file set' . "\n");
    }

    // And a record type
    if (isset($cmdOptions['r']) == true) {
        switch ($cmdOptions['r']) {
            case 'A':
            case 'D':
                $recordType = $cmdOptions['r'];
                break;
            default:
                die('Record type not supported' . "\n");
                break;
        }
    } else {
        die('No record type requested' . "\n");
    }

    // Default the start line
    $startLine = 0;

    // If we have set a start line, set it
    if (isset($cmdOptions['s']) == true) {
        $startLine = $cmdOptions['s'];
    }

    // Default the number of line to read
    $numOfLines = 0;

    // If we have set a start line, set it
    if (isset($cmdOptions['n']) == true) {
        $numOfLines = $cmdOptions['n'];
    }

    // Default the output file
    $outputFile = '';

    // If we have a place to output, set that up
    if (isset($cmdOptions['o']) == true) {
        $outputFile = $cmdOptions['o'];
    }
}

// A counter for the number of lines we've found
$foundLines = 0;

// Init the start line location
$readStartLine = $startLine;

// Init the number of lines we need to return
$numOfLinesRemaining = $numOfLines;

$loopRead = true;

while ($loopRead == true) {
    // Init the size of the next chunk we want to read
    $readLines = 1000;

    // If we've set a number of lines to read, and it's less than a full chunk, set it
    if ($numOfLines > 0 && $numOfLinesRemaining < $readLines) {
        $readLines = $numOfLinesRemaining;
    }

    // Parse the file to get records
    $records = USPSTools\Parse\AIS\CS215N::parseFile($file, $recordType, $readStartLine, $readLines);

    // Add the number of lines we've found to the total
    $foundLines += $records['foundCount'];

    // And remove them from the number remaining
    $numOfLinesRemaining -= $records['foundCount'];

    // If we're requested a specific number of lines, and found all of them, stop reading the file
    if ($numOfLines > 0 && $numOfLinesRemaining <= 0) {
        $loopRead = false;
    }

    if ($records['foundCount'] == 0) {
        // If we haven't found any records, we must have hit the end of the file
        $loopRead = false;
    } else {
        // Generate SQL for the records we've got
        generateSql($recordType, $records, $outputFile);

        // Update the start line location with the latest position
        $readStartLine = $records['lineCount'];
    }

    // Unset the records, so gc can clean up the memory
    unset($records);
}

// If the command line asked for a return count, echo it out
if (isset($cmdOptions['return-count'])) {
    echo 'Found: ' . $foundLines . "\n";
}

// If the command line asked for a return position, echo it out
if (isset($cmdOptions['return-position'])) {
    echo 'Last Line: ' . $readStartLine . "\n";
}

function generateSql ($recordType, $records, $outputFile = '') {
    // Now, let's make some SQL
    if ($recordType == 'A') {
        $sqlHeader = 'INSERT INTO `ref_AIS_CS215N_A` (`CopyrightCode`, `Zip`, `AliasStreetAbbreviation`, `AliasStreetName`, `AliasStreetSuffixAbbreviation`,' . "\n";
        $sqlHeader .= ' `AliasStreetPostDirectionalAbbreviation`, `StreetPreDirectionalAbbreviation`, `StreetName`, `StreetSuffixAbbreviation`,' . "\n";
        $sqlHeader .= ' `StreetPostDirectionalAbbreviation`, `AliasTypeCode`, `AliasCentury`, `AliasYear`, `AliasMonth`, `AliasDay`, `AliasDeliveryAddressLow`,' . "\n";
        $sqlHeader .= ' `AliasDeliveryAddressHigh`, `AliasRangeCode`, `Filler`)' . "\n";
        $sqlHeader .= 'VALUES';
    } else {
        $sqlHeader = 'INSERT INTO `ref_AIS_CS215N_D` (`CopyrightCode`, `Zip`, `CityStateKey`, `ZipClassificationCode`, `CityStateName`,' . "\n";
        $sqlHeader .= ' `CityStateNameAbbreviation`, `CityStateNameFacilityCode`, `CityStateNameMailingNameIndicator`, `PreferredLastLineCityStateKey`,' . "\n";
        $sqlHeader .= ' `PreferredLastLineCityStateName`, `CityDeliveryIndicator`, `CarrierRouteRateSortation`, `ZipNameIndicator`, `FinanceNumber`,' . "\n";
        $sqlHeader .= ' `StateAbbreviation`, `CountyNumber`, `CountyName`)' . "\n";
        $sqlHeader .= 'VALUES' . "\n";
    }

    $sql = $sqlHeader;

    if ($records['foundCount'] > 0) {
        $recordCounter = 0;

        foreach ($records['records'] as $thisRecordInfo) {
            $thisRecord = $thisRecordInfo['record'];

            $sql .= '('
                . '"' . $thisRecord['CopyrightDetailCode'] . '",'
                . '"' . $thisRecord['Zip'] . '",';

            switch ($thisRecord['CopyrightDetailCode']) {
                case 'A':   // Alias type records
                    $sql .= '"' . $thisRecord['AliasStreetAbbreviation'] . '",'
                        . '"' . $thisRecord['AliasStreetName'] . '",'
                        . '"' . $thisRecord['AliasStreetSuffixAbbreviation'] . '",'
                        . '"' . $thisRecord['AliasStreetPostDirectionalAbbreviation'] . '",'
                        . '"' . $thisRecord['StreetPreDirectionalAbbreviation'] . '",'
                        . '"' . $thisRecord['StreetName'] . '",'
                        . '"' . $thisRecord['StreetSuffixAbbreviation'] . '",'
                        . '"' . $thisRecord['StreetPostDirectionalAbbreviation'] . '",'
                        . '"' . $thisRecord['AliasTypeCode'] . '",'
                        . '"' . $thisRecord['AliasCentury'] . '",'
                        . '"' . $thisRecord['AliasYear'] . '",'
                        . '"' . $thisRecord['AliasMonth'] . '",'
                        . '"' . $thisRecord['AliasDay'] . '",'
                        . '"' . $thisRecord['AliasDeliveryAddressLow'] . '",'
                        . '"' . $thisRecord['AliasDeliveryAddressHigh'] . '",'
                        . '"' . $thisRecord['AliasRangeCode'] . '",'
                        . '"' . $thisRecord['Filler'] . '"';
                    break;
                case 'D':   // Detail type records
                    $sql .= '"' . $thisRecord['CityStateKey'] . '",'
                        . '"' . $thisRecord['ZipClassificationCode'] . '",'
                        . '"' . $thisRecord['CityStateName'] . '",'
                        . '"' . $thisRecord['CityStateNameAbbreviation'] . '",'
                        . '"' . $thisRecord['CityStateNameFacilityCode'] . '",'
                        . '"' . $thisRecord['CityStateNameMailingNameIndicator'] . '",'
                        . '"' . $thisRecord['PreferredLastLineCityStateKey'] . '",'
                        . '"' . $thisRecord['PreferredLastLineCityStateName'] . '",'
                        . '"' . $thisRecord['CityDeliveryIndicator'] . '",'
                        . '"' . $thisRecord['CarrierRouteRateSortation'] . '",'
                        . '"' . $thisRecord['ZipNameIndicator'] . '",'
                        . '"' . $thisRecord['FinanceNumber'] . '",'
                        . '"' . $thisRecord['StateAbbreviation'] . '",'
                        . '"' . $thisRecord['CountyNumber'] . '",'
                        . '"' . $thisRecord['CountyName'] . '"';
                    break;
            }

            $sql .= '),' . "\n";
        }

        $sql = substr($sql, 0, -2) . ';' . "\n\n";

        if (empty($outputFile) == true) {
            echo $sql;
        } else {
            $fp = fopen($outputFile, 'a');
            fwrite($fp, $sql);
            fclose($fp);

            unset($fp);
        }

        // Clear this variable for garbage collection
        unset($sql);
    }
}

