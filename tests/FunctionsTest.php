<?php

namespace wmrsp\BlueBillywig\YouTubeImporter;

use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase
{

    public function testYouTubeDlInstalled()
    {
        $this->assertTrue(Functions::YoutubeDlInstalled());
    }

}