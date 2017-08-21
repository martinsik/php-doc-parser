<?php

namespace DocParser\Tests;

use PHPUnit\Framework\TestCase;
use DocParser\Package;

class PackageTest extends TestCase
{
    public function testGetOrigFilename()
    {
        $package = new Package('en', 'cz1.php.net');
        $this->assertEquals('php_manual_en.tar.gz', $package->getOrigFilename());
    }

    public function testGetUrl()
    {
        $package = new Package('en', 'cz1.php.net');
        $this->assertEquals('http://cz1.php.net/get/php_manual_en.tar.gz/from/this/mirror', $package->getUrl());
    }

    public function testUnpack()
    {
        $expectedFiles = [
            'class.pdo.html',
            'class.splheap.html',
            'datetime.diff.html',
            'function.cos.html',
            'function.is-string.html',
            'function.print-r.html',
            'function.strcmp.html',
        ];

        $package = new Package('en', 'cz1.php.net');
        $tmpFile = $package->download();
        $dir = $package->unpack($expectedFiles);

        $unpackedFiles = scandir($dir);
        foreach ($expectedFiles as $file) {
            $this->assertContains($file, $unpackedFiles);
        }

        $package->cleanup();
        $this->assertFileNotExists($tmpFile);
    }
}