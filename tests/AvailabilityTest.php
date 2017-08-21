<?php

namespace DocParser\Tests;

use PHPUnit\Framework\TestCase;
use DocParser\Availability;

class AvailabilityTest extends TestCase
{
    public function testLanguages()
    {
        $expected = [
            'en' => 'English',
            'es' => 'Spanish',
            'de' => 'German',
        ];

        $avail = new Availability();
        $languages = $avail->listPackages();

        $this->assertArraySubset($expected, $languages);
    }
}