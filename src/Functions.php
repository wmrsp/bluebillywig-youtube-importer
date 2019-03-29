<?php

namespace wmrsp\BlueBillywig\YouTubeImporter;

use BlueBillywig\VMSRPC\HOTP;
use Exception;

/**
 * Class Functions
 * @package wmrsp\BlueBillywig\YouTubeImporter
 */
class Functions
{
    /**
     * Checks whether or not the youtube-dl command line software is installed.
     *
     * @return bool
     */
    public static function YoutubeDlInstalled()
    {
        return `which youtube-dl` ? TRUE : FALSE;
    }

    /**
     * Check whether or not a string is valid JSON.
     *
     * @param string $json_string
     * @return bool
     */
    public static function isValidJson(string $json_string)
    {
        json_decode($json_string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Creates an rpctoken for communicating with the Blue Billywig API based on the publication_shared_secret.
     *
     * @param string $publication_shared_secret
     * @return string
     */
    public static function createBbRpcToken(string $publication_shared_secret)
    {
        $ar_token = preg_split("/-/", $publication_shared_secret);
        $token_id = $ar_token[0];
        $result = HOTP::generateByTime($ar_token[1], 120, time());
        return $token_id . "-" . $result->toString();
    }

    /**
     * Create a log file in case it does not exist yet.
     *
     * @param string $filename
     * @throws Exception
     */
    public static function createLogFile(string $filename)
    {
        if(!file_exists($filename)) {
            $handle = fopen($filename, "w");
            if(!$handle) {
                throw new Exception("Could not create the log file: " . $filename);
            }
            fclose($handle);
        }
    }

    /**
     * Create a timestamp based on the current time.
     *
     * @return false|string
     */
    public static function timestamp()
    {
        return date("M t Y H:i:s", time());
    }
}