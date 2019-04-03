<?php

namespace wmrsp\BlueBillywig\YouTubeImporter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Exception;

final class ImporterTest extends TestCase
{

    static protected $config;
    static protected $importer;

    protected function setUp()
    {
        $config_file = dirname(__FILE__) . '/config.yaml';
        $this->assertFileExists($config_file);
        self::$config = Yaml::parseFile($config_file);
        try {
            self::$importer = new Importer(self::$config["youtube_channel"], self::$config["publication_name"], self::$config["publication_shared_secret"]);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testDownloadYouTubeChannelMetaData()
    {
        try {
            self::$importer->downloadYouTube();
        } catch(Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testImportDownloads()
    {
        try {
            $import_result = self::$importer->importDownloads();
            $this->assertIsArray($import_result);
        } catch(Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testGetLogAsArray()
    {
        try {
            $this->assertIsArray(self::$importer->getLogAsArray());
        } catch(Exception $e) {
            $this->fail($e->getMessage());
        }
    }
}