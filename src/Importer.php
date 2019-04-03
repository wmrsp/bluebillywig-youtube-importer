<?php

namespace wmrsp\BlueBillywig\YouTubeImporter;

use YoutubeDl\YoutubeDl;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Exception;
use DateTime;

class Importer
{
    /**
     * URL of the YouTube account (https://www.youtube.com/user/myawesomeusername).
     *
     * @var string
     */
    protected $youtubeUrl;

    /**
     * Name of the Blue Billywig VMS publication as it appears in the URL (mypublication | mypublication.dev as it appears in https://mypublication.bbvms.com).
     *
     * @var string
     */
    protected $publicationName;

    /**
     * Shared secret which is used to communicate with the Blue Billywig API.
     *
     * @var string
     */
    protected $publicationSharedSecret;

    /**
     * Parameters to be passed to the youtube-dl command line software.
     *
     * @var array
     */
    protected $YouTubeDlParams;

    /**
     * Path to the downloads folder where the json files with the YouTube video's metadata can be stored.
     *
     * @var string
     */
    protected $downloadsFolder;

    /**
     * Path to the logs folder where the logs of the imports will be saved.
     *
     * @var string
     */
    protected $logsFolder;

    /**
     * Filename of the log file.
     *
     * @var string
     */
    protected $logFile;

    /**
     * Time, in seconds, in between importing video's in the Blue Billywig VMS.
     *
     * @var int
     */
    protected $timeoutBetweenImports = 30;

    /**
     * Importer constructor.
     *
     * @param string $youtube_url
     * @param string $publication_name
     * @param string|NULL $publication_shared_secret
     * @throws Exception
     */
    public function __construct(string $youtube_url, string $publication_name, string $publication_shared_secret = NULL)
    {
        $this->youtubeUrl = $youtube_url;
        $this->publicationName = $publication_name;
        $this->publicationSharedSecret = $publication_shared_secret;

        if(!Functions::YoutubeDlInstalled()) {
            throw new Exception("You must have the youtube-dl command line software installed.");
        }

        $this->setYouTubeDlParams([
            "skip-download" => TRUE,
            "continue" => TRUE,
            "output" => "%(channel_id)s.%(upload_date)s.%(id)s.%(title)s.%(ext)s",
        ]);

        try {
            $this->setDownloadsPath(dirname(__FILE__) . "/../downloads");
            $this->setLogsPath(dirname(__FILE__) . "/../logs");
            $this->logFile = $this->logsFolder . "/" . urlencode($this->youtubeUrl) . ".log";
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Download video(s) from YouTube using youtube-dl.
     * If the default $YouTubeDlParams are set this will only download a json file(s) with the video's metadata.
     *
     * @throws Exception
     */
    public function downloadYouTube()
    {
        try {
            $youtube = new YoutubeDl($this->YouTubeDlParams);
            $youtube->setDownloadPath($this->downloadsFolder);
            $youtube->download($this->youtubeUrl);
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Import video(s) from the json files in the downloads folder into the Blue Billywig VMS.
     *
     * @param bool $skip_duplicates
     * @return array
     * @throws Exception
     */
    public function importDownloads(bool $skip_duplicates = TRUE)
    {
        // Take note of the current time;
        $import_time = new DateTime('now');

        // Loop through the JSON files in the downloads folder.
        foreach(glob($this->downloadsFolder . "/*.info.json") as $filename) {
            $file_contents = file_get_contents($filename);
            // Check if the file's contents is valid JSON.
            if(!Functions::isValidJson($file_contents)) {
                $this->log("The following file does not contain valid json: " . $filename);
                continue;
            }
            $video_json = json_decode($file_contents, TRUE);
            // Check if the JSON has the video's url in it.
            if(!isset($video_json["webpage_url"])) {
                $this->log("The following file does not contain the webpage_url of the video: " . $filename);
                continue;
            }
            // Check if the video has already been imported before in th Blue Billywig VMS.
            if($skip_duplicates && $this->videoAlreadyImported($video_json["webpage_url"])) {
                $this->log("The following file has already been imported once before: " . $filename);
                // Remove the JSON file with the video's data.
                unlink($filename);
                continue;
            }
            try {
                // Collect data for the API call to the Blue Billywig API.
                $guzzle_client = new Client(["base_uri" => "https://" . $this->publicationName . ".bbvms.com"]);
                $video_url = $video_json["webpage_url"];
                $rpc_token = Functions::createBbRpcToken($this->publicationSharedSecret);
                $payload = json_encode(["url" => $video_url]);

                // Post the video's url to the Blue Billywig API and save the API's response.
                $api_response = $guzzle_client->request("POST", "/sapi/import", [
                    "headers" => [
                        "rpctoken" => $rpc_token,
                        "Content-Type" => "application/json",
                        "Accept" => "application/json"
                    ],
                    "body" => $payload
                ]);
                // Collect the response body.
                $api_response_body = $api_response->getBody();
                $api_response_body_contents = $api_response_body->getContents();
                // Log the Blue Billywig API's response.
                $this->log($api_response_body_contents);
                // Remove the JSON file with the video's data.
                unlink($filename);
                // Prevent overloading the Blue Billywig API by waiting for a certain amount of time.
                sleep($this->timeoutBetweenImports);
            } catch (Exception $e) {
                $this->log("Exception: " . $e->getMessage() . " - " . $filename);
                continue;
            } catch(GuzzleException $e) {
                $this->log("Exception: " . $e->getMessage() . " - " . $filename);
                continue;
            }
        }

        return $this->getLogAsArray($import_time);;
    }

    /**
     * Check if a video has already been imported once before into the Blue Billywig VMS.
     *
     * @param string $video_url
     * @return bool
     */
    public function videoAlreadyImported(string $video_url)
    {
        $result = FALSE;

        try {
            // Collect data for the API call to the Blue Billywig API.
            $guzzle_client = new Client(["base_uri" => "https://" . $this->publicationName . ".bbvms.com"]);
            $rpc_token = Functions::createBbRpcToken($this->publicationSharedSecret);
            $querystring = "sourceid:\"imported-from:" . $video_url . "\"";

            // Make API call to the Blue Billywig API to retrieve all video's with the sourceid of the $video_url.
            $api_response = $guzzle_client->request("GET", "/sapi/mediaclip", [
                "headers" => [
                    "rpctoken" => $rpc_token,
                    "Content-Type" => "application/json",
                    "Accept" => "application/json"
                ],
                "query" => ["q" => $querystring]
            ]);

            // Collect the response body.
            $api_response_status_code = $api_response->getStatusCode();
            $api_response_body = $api_response->getBody();
            $api_response_body_contents = $api_response_body->getContents();
            $api_response_body_contents_json = [];

            // Validate the Blue Billywig's API response.
            if(Functions::isValidJson($api_response_body_contents)) {
                $api_response_body_contents_json = json_decode($api_response_body_contents, TRUE);
            }

            // Determine whether or not the video has already been imported.
            if($api_response_status_code == 200 && isset($api_response_body_contents_json["numfound"]) && $api_response_body_contents_json["numfound"] > 0) {
                $result = TRUE;
            }
        } catch(Exception $e) {
            // Do nothing, method will return FALSE.
        } catch(GuzzleException $e) {
            // Do nothing, method will return FALSE.
        }

        return $result;
    }

    /**
     * Getter for the contents of the log file returned in array format.
     *
     * @param DateTime|NULL $as_of_date
     * @return array
     */
    public function getLogAsArray(DateTime $as_of_date = NULL)
    {
        $result = [];
        if(file_exists($this->logFile)) {
            $handle = fopen($this->logFile, "r");
            while(($line = fgets($handle)) !== FALSE) {
                $message_time = substr($line, 0, 25);
                try {
                    $message_dateTime = new DateTime($message_time);
                    if(!empty($as_of_date) && $message_dateTime < $as_of_date) {
                        continue;
                    }
                } catch(Exception $e) {
                    // Do not include the log line in the results since something is wrong with it's timestamp.
                    continue;
                }
                $message = substr($line, 27, -1);
                $result[$message_time][] = $message;
            }
        }
        return $result;
    }

    /**
     * Getter for the downloadsFolder.
     *
     * @return string
     */
    public function getDownloadsPath()
    {
        return $this->downloadsFolder;
    }

    /**
     * Setter for the downloadsFolder.
     *
     * @param string $path
     * @return $this
     * @throws Exception
     */
    public function setDownloadsPath(string $path)
    {
        if(!file_exists($path)) {
            throw new Exception("The provided path does not exist.");
        }
        $this->downloadsFolder = $path;
        return $this;
    }

    /**
     * Getter for the logsFolder.
     *
     * @return string
     */
    public function getLogsPath()
    {
        return $this->logsFolder;
    }

    /**
     * Setter for the logsFolder.
     *
     * @param string $path
     * @return $this
     * @throws Exception
     */
    public function setLogsPath(string $path)
    {
        if(!file_exists($path)) {
            throw new Exception("The provided path does not exist.");
        }
        $this->logsFolder = $path;
        return $this;
    }

    /**
     * Getter for the YouTubeDlParams.
     *
     * @return array
     */
    protected function getYouTubeDlParams()
    {
        return $this->YouTubeDlParams;
    }

    /**
     * Setter for the YouTubeDlParams.
     *
     * @param array $YouTubeDlParams
     * @return $this
     */
    private function setYouTubeDlParams(array $YouTubeDlParams)
    {
        $this->YouTubeDlParams = $YouTubeDlParams;
        return $this;
    }

    /**
     * Logs a message to the log file.
     *
     * @param string $message
     * @throws Exception
     */
    private function log(string $message)
    {
        $message = Functions::timestamp() . " - " . $message . PHP_EOL;

        try {
            Functions::createLogFile($this->logFile);
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }

        $handle = fopen($this->logFile, "a");
        fwrite($handle, $message);
        fclose($handle);
    }

}