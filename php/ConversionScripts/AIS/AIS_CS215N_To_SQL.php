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
require_once '../../USPSTools/Parse/Parse.php';
require_once '../../USPSTools/Parse/AIS/CS215N.php';

if (count($argv) == 1) {
    echo 'CS215N To SQL Insert Generator' . "\n"
        . 'Usage:' . "\n"
        . '    ./citystateToSql.php [File To Parse] [Record Type] [Start Line] [Number Of Lines]' . "\n"
        . "\n"
        . 'File To Parse' . "\n"
        . '    The path to the file to parse into SQL.' . "\n"
        . "\n"
        . 'Record Type' . "\n"
        . '    The type of record to parse. Currently supported are [A]lias records and [D]etail records.' . "\n"
        . "\n"
        . 'Start Line' . "\n"
        . '    The line to start at. Default is 0 (start of file).' . "\n"
        . "\n"
        . 'Number Of Lines' . "\n"
        . '    The number of lines to parse and return. If not set, defaults to all.' . "\n";

    die();
}

$file = $argv[1];

$recordType = isset($argv[2]) == true ? strtoupper($argv[2]) : 'D';

$startLine = 0;

// Go to line
if (isset($argv[3]) == true) {
    $startLine = $argv[3];
}

$numOfLines = isset($argv[4]) == true ? $argv[4] : 0;

// Parse the file to get records
$records = USPSTools\Parse\AIS\CS215N::parseFile($file, $recordType, $startLine, $numOfLines);

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

$sql = '';

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

        $recordCounter++;

        // Print for ever 1000 lines processed
        if (($recordCounter % 1000) == 0) {
            $sql = substr($sql, 0, -2) . ';';
            echo $sqlHeader . $sql . "\n\n";
            $sql = '';
        }
    }
    
    $sql = substr($sql, 0, -2) . ';';
    echo $sqlHeader . $sql . "\n\n";
    echo '-- Processed ' . $recordCounter . ' records total' . "\n";
}

