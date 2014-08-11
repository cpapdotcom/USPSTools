<?php
/**
 * USPSTools Library provided by CPAP.com
 *
 * @author Ben Dauphinee <ben.dauphinee@cpap.com>
 * @link https://github.com/cpapdotcom/USPSTools
 * @version 1.0
 * @license MIT
 */

 namespace USPSTools;

/**
 * This class is for managing USPS Electronic Product Fulfillment (EPF) interactions.
 *
 * When you have a USPS product you have purchased, you can sign up for digital delivery services.
 * This class allows you to automate those interactions via the REST API they provide, instead of
 * manually downloading updated files when emailed.
 *
 * USPS EPF Supported: v1.04.2 - 2014-04-30
 */
class EPF
{
    /**
     * The base URL for the EPF API.
     */
    const EPF_BASE_URL = 'https://epfws.usps.gov/ws/resources';

    /**
     * Set if we are in debug mode or not.
     */
    protected $debugMode = 0;

    /**
     * The file location to save downloads to.
     */
    protected $fileSaveLocation = '';

    /**
     * The user ID assigned to use for login.
     */
    protected $loginUser;

    /**
     * The password assigned to use for login.
     */
    protected $loginPass;

    /**
     * Session key for this login instance.
     */
    protected $logonKey;

    /**
     * Tracks if the latest request was successful or not.
     */
    protected $requestSuccess = null;

    /**
     * Rotating security token, changed with every request. To be used along with loginKey.
     */
    protected $tokenKey;

    /**
     * A list of product codes and IDs that we are subscribed to, to be used with queries to API.
     */
    protected $subscribedProducts;

    /**
     * Handles the setup of this class
     *
     * @param string $loginUser The user to log into the service with.
     * @param string $loginPass The password to log into the service with.
     * @param boolean $debugMode If we want to echo out debugging information.
     */
    public function __construct ($loginUser, $loginPass, $debugMode = false)
    {
        $this->loginUser = $loginUser;
        $this->loginPass = $loginPass;

        $this->debugMode = $debugMode;
    }

    /**
     * Download the actual file you are looking to fetch from the Akamai edge servers.
     *
     * @param string $fileId The file ID to download. This is returned as part of a listFiles call.
     * @param string $filePath The file to download. This is returned as part of a listFiles call.
     *
     * @return array If the file was downloaded or not, and information about the file downloaded.
     */
    public function downloadFile ($fileId, $filePath)
    {
        // Set up the download parameters
        $jsonData = array(
            'fileid' => $fileId,
            'filepath' => $filePath,
        );

        // Make a request and download the file
        $jsonResponse = $this->pullResource('/download/file', 'POST', $jsonData);

        if ($this->requestSuccess == false) {
            throw new Exception('Error downloading file');
        }

        return $jsonResponse;
    }

    /**
     * Download smaller files from the responding webserver.
     *
     * @param string $fileId The file ID to download. This is returned as part of a listFiles call.
     * @param string $filePath The file to download. This is returned as part of a listFiles call.
     *
     * @return array If the file was downloaded or not, and information about the file downloaded.
     */
    public function downloadEpf ($fileId, $filePath)
    {
        $jsonData = array(
            'fileid' => $fileId,
            'filepath' => $filePath,
        );

        $jsonResponse = $this->pullResource('/download/epf', 'POST', $jsonData);

        if ($this->requestSuccess == false) {
            throw new Exception('Error downloading file');
        }

        return $jsonResponse;
    }

    /**
     * Download the newest file available of this product.
     *
     * @param string $productCode The product code to list files for.
     * @param string $productId The product ID under the product code to list files for.
     * @param string $status [Optional] Apply a filter on the file status.
     * @param string $fulfilled [Optional] Apply a filter on the file fulfillment status.
     *
     * @return array Information about the file we've downloaded, if we have.
     */
    public function downloadNewestFile ($productCode, $productId, $status = '', $fulfilled = '')
    {
        // Get the newest file available
        $newestFile = $this->findNewestFile($productCode, $productId, $status, $fulfilled);

        if (empty($newestFile) == true) {
            throw new Exception('No file available to download');
        }

        // Download the file
        $downloadInfo = $this->downloadEpf($newestFile['fileid'], $newestFile['filepath']);

        // Set this file as completed download
        $this->setStatus($newestFile['fileid'], 'C');

        // Pass back the download info
        return $downloadInfo;
    }

    /**
     * Find the newest file available for this product code/id combination. If set, some filtering
     * can be applied to this list.
     *
     * @param string $productCode The product code to list files for.
     * @param string $productId The product ID under the product code to list files for.
     * @param string $status [Optional] Apply a filter on the file status.
     * @param string $fulfilled [Optional] Apply a filter on the file fulfillment status.
     *
     * @return array Information on the newest file available.
     */
    public function findNewestFile ($productCode, $productId, $status = '', $fulfilled = '')
    {
        // Get a list of files for this product
        $fileList = $this->listFiles($productCode, $productId, $status, $fulfilled);

        // Init some vars we need
        $latestFileDate = 0;
        $latestFile = array();

        // Loop over the list of files and find the newest dated one
        foreach ($fileList as $thisFile) {
            $thisTimestamp = strtotime($thisFile['fulfilled']);

            if ($thisTimestamp > $latestFileDate) {
                $latestFileDate = $thisTimestamp;
                $latestFile = $thisFile;
            }
        }

        // Return the file information
        return $latestFile;
    }

    /**
     * You can get a list of available files for a particular EPF product code/id combination. If set,
     * some filtering can be applied to this list.
     *
     * @param string $productCode The product code to list files for.
     * @param string $productId The product ID under the product code to list files for.
     * @param string $status [Optional] Apply a filter on the file status.
     * @param string $fulfilled [Optional] Apply a filter on the file fulfillment status.
     *
     * @return array A list of information and files for this product.
     */
    public function listFiles ($productCode, $productId, $status = '', $fulfilled = '')
    {
        // Set up the request parameters
        $jsonData = array(
            'productcode' => $productCode,
            'productid' => $productId,
        );

        // If we have a status filter, set it
        if (empty($status) == false) {
            $jsonData['status'] = $status;
        }

        // If we have a fulfilled filter, set it
        if (empty($fulfilled) == false) {
            $jsonData['fulfilled'] = $fulfilled;
        }

        // Make the request for the file list
        $jsonResponse = $this->pullResource('/download/list', 'POST', $jsonData);

        // If the request failed, throw off an exception
        if ($this->requestSuccess == false) {
            $extraInfo = (isset($jsonResponse['messages']) == true) ? $jsonResponse['messages'] : 'Unknown';

            throw new Exception('Error getting file list: ' . $extraInfo);
        }

        // Return the file list
        return $jsonResponse['responseData']['fileList'];
    }

    /**
     * Use this function to log in to the EPF system.
     *
     * @return array The response from the login server.
     */
    public function login ()
    {
        // Set up the parameters for a login attempt
        $jsonData = array(
            'login' => $this->loginUser,
            'pword' => $this->loginPass,
        );

        // Make a login request
        $jsonResponse = $this->pullResource('/epf/login', 'POST', $jsonData);

        // If the request failed, throw off an exception
        if ($this->requestSuccess == false) {
            $extraInfo = (isset($jsonResponse['messages']) == true) ? $jsonResponse['messages'] : 'Unknown';

            throw new Exception('Error logging in: ' . $extraInfo);
        }

        // Pass back the login response
        return $jsonResponse;
    }

    /**
     * Use this function to log out of the EPF system. This is not required, but considered good practice.
     *
     * @return array The status of the logout attempt.
     */
    public function logout ()
    {
        // Set up the parameters for a logout attempt
        $jsonData = array();

        // Make a logout request
        $jsonResponse = $this->pullResource('/epf/logout', 'POST', $jsonData);

        // If the request failed, throw off an exception
        if ($this->requestSuccess == false) {
            $extraInfo = (isset($jsonResponse['messages']) == true) ? $jsonResponse['messages'] : 'Unknown';

            throw new Exception('Error logging out: ' . $extraInfo);
        }

        // Pass back the logout response
        return $jsonResponse;
    }

    /**
     * Parse cURL response headers into a usable array.
     *
     * @param string $headerString A string containing the headers to parse.
     */
    protected function parseHeaders ($headerString)
    {
        // Init the return array
        $returnHeaders = array();

        // Explode out the header string to an array
        $headerProps = explode("\r\n", $headerString);

        // Roll over each header line and parse it
        foreach ($headerProps as $k => $propLine) {
            if ($k === 0) {
                $headers['http_code'] = $propLine;
            } else {
                list($key, $value) = explode(': ', $propLine);
                $returnHeaders[$key] = $value;
            }
        }

        // Return the list of headers
        return $returnHeaders;
    }

    /**
     * This function will execute a request to the EPF server.
     *
     * @param string $url The EPF sub-url that we want to hit.
     * @param string $mode The mode we want to use in our request. By default, we're going to POST.
     * @param array $jsonData Extra parameters for the EPF request.
     *
     * @return array Information about the request we have made.
     */
    protected function pullResource ($url, $mode = 'POST', $jsonData = array())
    {
        // Init the return variable
        $return = array();

        // Init some other variables
        $mode = strtoupper($mode);
        $transferTimeout = 30;

        // Reset the request status flag
        $this->requestSuccess = 0;

        // Set up the cURL request
        $curlRequest = curl_init();

        if ($this->debugMode == true) {
            // Output the URL for the request
            echo "\n" . 'URL: ' . self::EPF_BASE_URL . $url;

            // Set cURL to verbose, so we can see what's going on with it
            curl_setopt($curlRequest, CURLOPT_VERBOSE, true);
        }

        // If we pass in a filepath, then set up a download
        if (isset($jsonData['filepath']) == true) {
            $filenamePieces = explode('/', $jsonData['filepath']);
            $actualFilename = array_pop($filenamePieces);

            // Name the full path to save to
            $saveFilename = $this->fileSaveLocation . date('Y-m-d_H.i.s') . '_' . $actualFilename;

            // Open a file to save the headers to
            $headerFile = fopen($saveFilename . '.header', 'w');

            // Open a file to save the contents to
            $downloadFile = fopen($saveFilename, 'w');

            // Set up the headers to allow us to download a file
            curl_setopt($curlRequest, CURLOPT_HTTPHEADER, array(
                'Akamai-File-Request: ' . $jsonData['filepath'],
                'fileid: ' . $jsonData['fileid'],
                'logonkey: ' . $this->logonKey,
                'tokenkey: ' . $this->tokenKey,
            ));

            // Set the timeout higher for the download
            $transferTimeout = 600;

            // Set the script time limit to a long one
            set_time_limit($transferTimeout + 30);

            // Output the headers to another file
            curl_setopt($curlRequest, CURLOPT_WRITEHEADER, $headerFile);

            // Give cURL the file location to save download to
            curl_setopt($curlRequest, CURLOPT_FILE, $downloadFile);

            // Set the transfer mode to binary
            curl_setopt($curlRequest, CURLOPT_BINARYTRANSFER, 1);

            // Turn off the headers, so they're not in the file we download
            curl_setopt($curlRequest, CURLOPT_HEADER, 0);
        } else {
            // Tell cURL to return the response into a variable
            curl_setopt($curlRequest, CURLOPT_RETURNTRANSFER, 1);

            // Grab the headers on response, since the next token key is in there
            curl_setopt($curlRequest, CURLOPT_HEADER, 1);
        }

        // Set the URL to hit
        curl_setopt($curlRequest, CURLOPT_URL, self::EPF_BASE_URL . $url);

        // Set up the mode of the transfer
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($curlRequest, CURLOPT_POST, 1);
                break;
            default:
                throw new Exception('Incorrect transfer mode specified');
                break;
        }

        // Set the timeout on the transfer
        curl_setopt($curlRequest, CURLOPT_TIMEOUT, $transferTimeout);

        if ($mode == 'POST') {
            // If we have a logon key set, add it to the JSON
            if (empty($this->logonKey) == false) {
                $jsonData['logonkey'] = $this->logonKey;
            }

            // If we have a token key set, add it to the JSON
            if (empty($this->tokenKey) == false) {
                $jsonData['tokenkey'] = $this->tokenKey;
            }

            // Set up the json object
            $postFields = 'obj=' . json_encode($jsonData);

            // Attach the json to POST to the cURL request
            curl_setopt($curlRequest, CURLOPT_POSTFIELDS, $postFields);

            if ($this->debugMode == true) {
                echo "\n" . "\n" . '------------------ postFields ------------------' . "\n";
                echo $postFields;
                echo "\n" . '------------------ postFields ------------------' . "\n" . "\n";
            }
        }

        // Run the cURL request
        $curlOutput = curl_exec($curlRequest);

        // Get cURL error info
        $curlErrno = curl_errno($curlRequest);
        $curlError = curl_error($curlRequest);

        if ($curlErrno > 0) {
            // Close the connection
            curl_close($curlRequest);

            // If there is a cURL error, throw off an exception
            throw new Exception('cURL error ' . $curlErrno . ': ' . $curlError);
        }

        // If we don't have any cURL output, throw off an exception
        if (empty($curlOutput) == true) {
            // Close the connection
            curl_close($curlRequest);

            // Throw off an exception
            throw new Exception('No response (cURL error ' . $curlErrno . ': ' . $curlError . ')');
        }

        // Get other cURL info
        $curlInfo = curl_getinfo($curlRequest);

        // Close the connection
        curl_close($curlRequest);

        if (isset($jsonData['filepath']) == true) {
            // Close header and download file pointers
            fclose($headerFile);
            fclose($downloadFile);

            // Read in the info for header data
            $headerData = trim(file_get_contents($saveFilename . '.header'));

            // Set up the response info
            $responseData = array(
                'filesize' => filesize($saveFilename),
                'filepath' => $saveFilename,
            );

            // See if we have an actual size or not
            if ($responseData['filesize'] > 0) {
                $responseData['response'] = 'success';
            } else {
                $responseData['response'] = 'failed';
            }
        } else {
            // Pull apart the response data
            list($headerData, $responseData) = explode("\r\n\r\n", $curlOutput, 2);

            // Decode the response JSON
            $responseData = json_decode($responseData, 1);
        }

        // Get the header properties
        $headerParams = $this->parseHeaders($headerData);

        // Check the headers for the next logon key
        if (isset($headerParams['User-Logonkey']) == true) {
            $this->logonKey = $headerParams['User-Logonkey'];
        }

        // Check the headers for the next token key
        if (isset($headerParams['User-Tokenkey']) == true) {
            $this->tokenKey = $headerParams['User-Tokenkey'];
        }

        // If the request succeeded, then set the request success flag
        if ($responseData['response'] == 'success') {
            $this->requestSuccess = 1;
        }

        // Add the info from cURL to the return
        $return['requestInfo'] = $curlInfo;

        // Add the headers from our response to the return
        $return['headerParams'] = $headerParams;

        // Add the response data into the return
        $return['responseData'] = $responseData;

        // Pass back the return info
        return $return;
    }

    /**
     * Set the download location to use when storing downloads.
     *
     * @param string $fileSaveLocation The location to save our downloaded files to.
     */
    public function setDownloadLocation ($fileSaveLocation)
    {
        // Sanity check that we have a file save location
        if (empty($fileSaveLocation) == true) {
            throw new Exception('No file save location specified');
        }

        // Make sure the directory we want to save into exists
        if (file_exists($fileSaveLocation) == false) {
            throw new Exception('File save location does not exist');
        }

        // Set the file location to save downloads
        $this->fileSaveLocation = $fileSaveLocation;
    }

    /**
     * Set the status on a particular file. This should be set after a download is successfully completed.
     *
     * @param string $fileId The file ID to download. This is returned as part of a listFiles call.
     * @param string $status The status to set the file to.
     *
     * @return array The status of the attempt to set.
     */
    public function setStatus ($fileId, $status)
    {
        // Set up the parameters for a status set attempt
        $jsonData = array(
            'fileid' => $fileId,
            'newstatus' => $status,
        );

        // Make the request to set the status
        $jsonResponse = $this->pullResource('/download/status', 'POST', $jsonData);

        // If the request failed, throw off an exception
        if ($this->requestSuccess == false) {
            $extraInfo = (isset($jsonResponse['messages']) == true) ? $jsonResponse['messages'] : 'Unknown';

            throw new Exception('Error setting status: ' . $extraInfo);
        }

        // Pass back the logout response
        return $jsonResponse;
    }

    /**
     * This function allows you to check connectivity and get the version of the web service.
     *
     * @return array The status of the web service.
     */
    public function version ()
    {
        return $this->pullResource('/epf/version', 'GET');
    }
}

