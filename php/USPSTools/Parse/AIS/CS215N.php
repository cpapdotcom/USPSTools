<?php
/**
 * This class is for parsing USPS AIS product CS215N, known as "City State National".
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

namespace USPSTools\Parse\AIS;

/**
 * This class is for parsing USPS AIS product CS215N, known as "City State National".
 */
class CS215N extends \USPSTools\Parse
{
    protected static $lineLength = 129;

    /**
     * Parse a line from the input file.
     *
     * @param string $line A line of data from the input file.
     * @param string $seekingRecordType [Optional] A record type to specifically seek and return. If
     *      set and current record does not match this, we will not spend the cycles to parse the
     *      rest of the record.
     *
     * @return array The results of the parse. This will contain the type, as well as an associative
     *      array of columns and data for this record type.
     */
    public static function parseLine ($line, $seekingRecordType = '')
    {
        $return = array();

        // Grab the record type
        $return['recordType'] = substr($line, 0, 1);

        // If we have a record type we're seeking, check and return
        if (empty($seekingRecordType) == false && $return['recordType'] != $seekingRecordType) {
            return $return;
        }

        // Set up the record return array
        $return['record'] = array(
            'CopyrightDetailCode' => $return['recordType'],
        );

        switch ($return['recordType']) {
            case 'A':   // Alias type records
                $return['record']['Zip']                                      = trim(substr($line, 1, 6));
                $return['record']['AliasStreetAbbreviation']                  = trim(substr($line, 6, 2));
                $return['record']['AliasStreetName']                          = trim(substr($line, 8, 28));
                $return['record']['AliasStreetSuffixAbbreviation']            = trim(substr($line, 36, 4));
                $return['record']['AliasStreetPostDirectionalAbbreviation']   = trim(substr($line, 40, 2));
                $return['record']['StreetPreDirectionalAbbreviation']         = trim(substr($line, 42, 2));
                $return['record']['StreetName']                               = trim(substr($line, 44, 28));
                $return['record']['StreetSuffixAbbreviation']                 = trim(substr($line, 72, 4));
                $return['record']['StreetPostDirectionalAbbreviation']        = trim(substr($line, 76, 2));
                $return['record']['AliasTypeCode']                            = trim(substr($line, 78, 1));
                $return['record']['AliasCentury']                             = trim(substr($line, 79, 2));
                $return['record']['AliasYear']                                = trim(substr($line, 81, 2));
                $return['record']['AliasMonth']                               = trim(substr($line, 83, 2));
                $return['record']['AliasDay']                                 = trim(substr($line, 85, 2));
                $return['record']['AliasDeliveryAddressLow']                  = trim(substr($line, 87, 10));
                $return['record']['AliasDeliveryAddressHigh']                 = trim(substr($line, 97, 10));
                $return['record']['AliasRangeCode']                           = trim(substr($line, 107, 1));
                $return['record']['Filler']                                   = trim(substr($line, 108, 21));
                break;
            case 'D':   // Detail type records
                $return['record']['Zip']                                  = trim(substr($line, 1, 5));
                $return['record']['CityStateKey']                         = trim(substr($line, 6, 6));
                $return['record']['ZipClassificationCode']                = trim(substr($line, 12, 1));
                $return['record']['CityStateName']                        = trim(substr($line, 13, 28));
                $return['record']['CityStateNameAbbreviation']            = trim(substr($line, 41, 13));
                $return['record']['CityStateNameFacilityCode']            = trim(substr($line, 54, 1));
                $return['record']['CityStateNameMailingNameIndicator']    = trim(substr($line, 55, 1));
                $return['record']['PreferredLastLineCityStateKey']        = trim(substr($line, 56, 6));
                $return['record']['PreferredLastLineCityStateName']       = trim(substr($line, 62, 28));
                $return['record']['CityDeliveryIndicator']                = trim(substr($line, 90, 1));
                $return['record']['CarrierRouteRateSortation']            = trim(substr($line, 91, 1));
                $return['record']['ZipNameIndicator']                     = trim(substr($line, 92, 1));
                $return['record']['FinanceNumber']                        = trim(substr($line, 93, 6));
                $return['record']['StateAbbreviation']                    = trim(substr($line, 99, 2));
                $return['record']['CountyNumber']                         = trim(substr($line, 101, 3));
                $return['record']['CountyName']                           = trim(substr($line, 104, 25));
                break;
        }

        return $return;
    }
}

