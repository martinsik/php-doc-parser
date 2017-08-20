<?php

namespace DocParser\Tests;

use PHPUnit\Framework\TestCase;
use DocParser\Parser;

class ParserTest extends TestCase
{

    const TEST_FILES_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'test-manual-files';

    public function testProcessFile()
    {
        $expectedFiles = [
            'datetime.setdate.html' => 'datetime.setdate.json',
            'eventhttp.setcallback.html' => 'eventhttp.setcallback.json',
            'function.array-diff.html' =>  'function.array-diff.json',
            'function.chown.html' => 'function.chown.json',
            'function.json-encode.html' => 'function.json-encode.json',
            'function.str-replace.html' => 'function.str-replace.json',
            'function.strrpos.html' => 'function.strrpos.json',
            'function.strtr.html' => 'function.strtr.json',
            'reflectionclass.getname.html' => 'reflectionclass.getname.json',
            'splfileobject.fgetcsv.html' => 'splfileobject.fgetcsv.json',
        ];

        $parser = new Parser();

        foreach ($expectedFiles as $file => $jsonFile) {
            $parserResult = $parser->processFile(self::TEST_FILES_DIR . DIRECTORY_SEPARATOR . $file);
            $expected = json_decode(file_get_contents(self::TEST_FILES_DIR . DIRECTORY_SEPARATOR . $jsonFile), true);

            $funcName = $parserResult->getFuncNames()[0];
            $actual = $parserResult->getResult($funcName);
            $actual['examples'] = $parserResult->getExamples($funcName);

            $this->assertEquals($expected, $actual);
        }
    }

}
