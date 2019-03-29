<?php

namespace wmrsp\BlueBillywig\YouTubeImporter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Exception;

final class ImporterTest extends TestCase
{

    static protected $config;

    protected function setUp()
    {
        $config_file = dirname(__FILE__) . '/config.yaml';
        $this->assertFileExists($config_file);
        self::$config = Yaml::parseFile($config_file);
    }

    public function testDownloadYouTubeChannelMetaData()
    {
        try {
            $importer = new Importer(self::$config["youtube_channel"], self::$config["publication_name"], self::$config["publication_shared_secret"]);
            $importer->downloadYouTube();
        } catch(Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testImportDownloads()
    {
        try {
            $importer = new Importer(self::$config["youtube_channel"], self::$config["publication_name"], self::$config["publication_shared_secret"]);
            $importer->importDownloads();
        } catch(Exception $e) {
            $this->fail($e->getMessage());
        }
    }

}