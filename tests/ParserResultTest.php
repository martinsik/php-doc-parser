<?php

namespace DocParser\Tests;

use PHPUnit\Framework\TestCase;
use DocParser\ParserResult;

class ParserResultTest extends TestCase
{

    public function testMergeWithResult()
    {
        $source = [
        //   test-file   warning       skip    result  examples
            ['file1',    'msg1',       '',     'res1', 'ex1, ex2'],
            ['file2',    'msg2, msg3', 1,      'res2', 'ex4'],
            ['file1',    '',           '',     'res1', 'ex3'],
            ['file2',    'msg4 ',      '',     'res4', ''],
        ];
        $expected = [
        //   test-file    warning             skip    result    examples
            ['file1',     'msg1',             '',     'res1',   'ex1, ex2, ex3'],
            ['file2',     'msg2, msg3, msg4', 1,      'res4',   'ex4'],
        ];

        $allResults = [];

        // Merge results
        foreach ($source as $row) {
            $result = new ParserResult();
            $file = $row[0];
            $result->setResult($file, $row[3]);
            if ($row[4]) {
                foreach (explode(',', $row[4]) as $example) {
                    $result->addExample($file, trim($example));
                }
            }

            if ($row[1]) {
                foreach (explode(',', $row[1]) as $warning) {
                    $result->addWarning($file, trim($warning));
                }
            }

            if ($row[2]) {
                $result->addSkipped($file);
            }

            if (isset($allResults[$file])) {
                $allResults[$file]->mergeWithResult($result);
            } else {
                $allResults[$file] = $result;
            }
        }

        // Check results
        $mergedResult = new ParserResult();
        foreach ($allResults as $r) {
            $mergedResult->mergeWithResult($r);
        }

        $warningsCount = $examplesCount = 0;
        foreach ($expected as $row) {
            $file = $row[0];

            $warningsCount += count(explode(',', $row[1]));
            $examplesCount += count(explode(',', $row[4]));

            $this->assertEquals(array_map(function($s) { return trim ($s); }, explode(',', $row[1])), $mergedResult->getWarnings($file));
            $this->assertEquals(array_map(function($s) { return trim ($s); }, explode(',', $row[4])), $mergedResult->getExamples($file));
            $this->assertEquals($row[3], $mergedResult->getResult($file));
            if ($row[2]) {
                assertTrue($mergedResult->isSkipped($file));
            } else {
                assertFalse($mergedResult->isSkipped($file));
            }
        }

        $this->assertEquals($warningsCount, $mergedResult->countAllWarnings());
        $this->assertEquals($examplesCount, $mergedResult->countAllExamples());
    }

}