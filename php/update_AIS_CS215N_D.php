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
 * This script automates the update of AIS CS215N database tables.
 */

require_once 'USPSTools/EPF/EPF.php';
require_once 'USPSTools/EPF/Exception/NoFileToDownloadException.php';
require_once 'USPSTools/Parse/Parse.php';
require_once 'USPSTools/Parse/AIS/CS215N.php';

// The EPF address set up with USPS
define ('USPS_EPF_USER', 'some@email.address');

// The EPF password provided by USPS
define ('USPS_EPF_PASS', 'APasswordFromUSPS');

// A download location to save to
define ('USPS_EPF_DOWNLOAD_LOCATION', '/var/www/usps/');

// A password for USPS AIS zip files provided by USPS
define ('USPS_AIS_CS215N_ZIP_PASSWORD', 'ADifferentPasswordFromUSPS');

// Database credentials
define('USPS_AIS_CS215N_DB_USER', 'ADatabaseUser');
define('USPS_AIS_CS215N_DB_PASS', 'ADatabasePass');
define('USPS_AIS_CS215N_DB_HOST', 'localhost');
define('USPS_AIS_CS215N_DB_NAME', 'ADatabaseName');

// An email address to send task complete report to
define('EMAIL_REPORT_TO', 'some.other@email.address');

$emailText = '';

$haveNewFile = 0;

try {
    // Get an EPF instance
    $uspsEpf = new USPSTools\EPF(USPS_EPF_USER, USPS_EPF_PASS);

    // Set the file download config location
    $uspsEpf->setDownloadLocation(USPS_EPF_DOWNLOAD_LOCATION);

    // See if we can talk to the service
    $versionInfo = $uspsEpf->version();
    
    if ($versionInfo['responseData']['response'] != 'success') {
        throw new Exception('Unable to get version info. Service may be down.');
    }

    // Attempt to log in
    $uspsEpf->login();

    try {
        // See if we can download a new file
        $fileInfo = $uspsEpf->downloadNewestFile('AIS', 'CS215N', 'N');

        if ($fileInfo['responseData']['response'] == 'success') {
            $haveNewFile = 1;

            $emailText .= "\n\n" . 'Downloaded File';
            $emailText .= "\n" . '    Size: ' . round($fileInfo['responseData']['filesize'] / 1024, 1) . 'kb';
            $emailText .= "\n" . '    Path: ' . $fileInfo['responseData']['filepath'];
        }
    } catch (USPSTools\EPF\NoFileToDownloadException $e) {
        echo "\n" . 'Unable to find new file to download';
    }
} catch (Exception $e) {
    // Email out an exception notices
    $emailText .= "\n" . 'Problem trying to access EPF services: ' . $e->getMessage();
}

if ($haveNewFile == 1) {
    // Set up the folder name we're going to use for this work
    $thisFileDir = USPS_EPF_DOWNLOAD_LOCATION . date('YmdHis') . '-AIS-CS215N';

    // Set up the name of the output file to write to
    $outputSqlFile = $thisFileDir . '/insertRecords.sql';

    // Set up the command to make a directory
    $cmdMkdir = 'mkdir ' . $thisFileDir;

    // Set up the command to extract the zip file from the tar we downloaded
    $cmdUntar = 'tar -m -xf ' . $fileInfo['responseData']['filepath'] . ' -C ' . $thisFileDir
        . ' --strip-components 2 ctystatenatl/ctystate/ctystate.zip';

    // Unzip the data file from the zip file
    $cmdUnzip = 'unzip -o -q -P ' . USPS_AIS_CS215N_ZIP_PASSWORD . ' ' . $thisFileDir . '/ctystate.zip -d ' . $thisFileDir;

    // Run a conversion on the data file to get a SQL file
    $cmdConvert = 'ConversionScripts/AIS/AIS_CS215N_To_SQL.php -f ' . $thisFileDir . '/ctystate.txt'
        . ' -r D -o ' . $outputSqlFile
        . ' --return-count --return-position';

    // Command to run the SQL update from converted file
    $cmdUpdateDb = 'mysql -u' . USPS_AIS_CS215N_DB_USER . ' -p' . USPS_AIS_CS215N_DB_PASS
        . ' -h' . USPS_AIS_CS215N_DB_HOST . ' -D' . USPS_AIS_CS215N_DB_NAME
        . ' < ' . $outputSqlFile;

    try {
        //echo "\n" . $cmdMkdir . "\n";
        exec($cmdMkdir);

        if (file_exists($thisFileDir) == false) {
            throw new Exception('Problem creating directory to work in');
        }

        //echo "\n" . $cmdUntar . "\n";
        exec($cmdUntar);

        if (file_exists($thisFileDir . '/ctystate.zip') == false) {
            throw new Exception('Problem unpacking downloaded file');
        }
    
        //echo "\n" . $cmdUnzip . "\n";
        exec($cmdUnzip);

        if (file_exists($thisFileDir . '/ctystate.txt') == false) {
            throw new Exception('Problem extracting data file');
        }

        // Init the file with transaction commands
        $sql = '-- USPS AIS CS215N UPDATE FILE' . "\n";
        $sql .= 'START TRANSACTION;' . "\n";
        $sql .= 'TRUNCATE TABLE `ref_AIS_CS215N_D`;' . "\n";

        $fp = fopen($outputSqlFile, 'a');
        fwrite($fp, $sql);
        fclose($fp);

        //echo "\n" . $cmdConvert . "\n";
        exec($cmdConvert, $convertReturn);

        // Append a commit to the end of the file
        $sql = 'COMMIT;' . "\n";

        $fp = fopen($outputSqlFile, 'a');
        fwrite($fp, $sql);
        fclose($fp);

        // Parse the conversion return for info to email
        $foundLines = 0;
        $lastLine = 0;

        foreach ($convertReturn as $thisString) {
            if (stristr($thisString, 'Found') == true) {
                list($str, $foundLines) = explode(': ', $thisString);
            }

            if (stristr($thisString, 'Last Line') == true) {
                list($str, $lastLine) = explode(': ', $thisString);
            }
        }

        $emailText .= "\n\n" . 'Parsed File';
        $emailText .= "\n" . '    Found Lines: ' . $foundLines;
        $emailText .= "\n" . '    Last Line: ' . $lastLine;

        // Update the database
        if ($foundLines > 0) {
            exec($cmdUpdateDb, $updateReturn);

            $emailText .= "\n\n" . 'Database Updated';
            $emailText .= "\n" . print_r($updateReturn, 1);
        } else {
            $emailText .= "\n\n" . 'Database Not Updated';
        }
    } catch (Exception $e) {
        // Email out an exception notices
        $emailText .= "\n" . 'Problem with update: ' . $e->getMessage();
    }
}

// Send email
mail(EMAIL_REPORT_TO, 'update_AIS_CS215N Report', $emailText);

