<?php
/**
 * USPSTools Library provided by CPAP.com
 *
 * @author Ben Dauphinee <ben.dauphinee@cpap.com>
 * @link https://github.com/cpapdotcom/USPSTools
 * @version 1.0
 * @license MIT
 */
 
namespace USPSTools\EPF;

/**
 * This class is for when we try to download a file and we can't find one to download.
 */
class NoFileToDownloadException extends \Exception {}
