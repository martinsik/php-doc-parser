<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use DocParser\Package;
use DocParser\Parser;
use Symfony\Component\Filesystem\Filesystem;


class ParserContext extends BehatContext {

    private $testFiles;
    private $testFilesDir;
    /**
     * @var Package
     */
    private $package;
    /**
     * @var Parser
     */
    private $parser;
    private $unpackedFilesDir;

    public function __construct() {
        $this->parser = new Parser();
//        var_dump($this->testFilesDir);exit;
    }

    /**
     * @Given /^these test files:$/
     */
    public function theseTestFiles(TableNode $table)
    {
        $this->testFiles = $table->getHash();
    }

    /**
     * @Then /^match them with files from "([^"]*)"$/
     */
    public function matchThemWithFilesFrom($dir)
    {
        $this->testFilesDir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR . $dir;

        foreach ($this->testFiles as $row) {
            $expected = json_decode(file_get_contents($this->testFilesDir . DIRECTORY_SEPARATOR . $row['expected-json-result']), true);

            $result = $this->parser->processFile($this->testFilesDir . DIRECTORY_SEPARATOR . $row['source-filename'], Parser::INCLUDE_EXAMPLES);
            $funcName = $result->getFuncNames()[0];

            $actual = array_merge($result->getResult($funcName), [
                'examples' => $result->getExamples($funcName)
            ]);

//            echo json_encode($actual, JSON_PRETTY_PRINT);

            assertEquals($expected, $actual);
        }
    }

    /**
     * @Then /^download manual from "([^"]*)" package from "([^"]*)"$/
     */
    public function downloadManualFromPackageFrom($lang, $mirror)
    {
        $this->package = new Package($lang, $mirror);

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php-doc-parser-test';
        @mkdir($dir);
        $file = $dir . DIRECTORY_SEPARATOR . $this->package->getOrigFilename();

        $this->package->download($file);
        $testUnpackDir = $this->package->unpack(array_map(function($item) { return $item['source-filename']; }, $this->testFiles));

        $this->unpackedFilesDir = $this->package->getUnpackedDir();

        assertEquals($testUnpackDir, $this->unpackedFilesDir);
        assertCount(count($this->testFiles), glob($this->unpackedFilesDir . DIRECTORY_SEPARATOR . '*.html'));
    }

    /**
     * @Given /^test downloaded files against them as well$/
     */
    public function testDownloadedFilesAgainstThemAsWell()
    {
//        $files = glob($this->downloadedFilesDir . DIRECTORY_SEPARATOR . '*.html');

        foreach ($this->testFiles as $row) {
            $file = $row['source-filename'];
            $expectedFileSize = filesize($this->unpackedFilesDir . DIRECTORY_SEPARATOR . $file);
            $testFileSize =  filesize($this->testFilesDir . DIRECTORY_SEPARATOR . $file);

            if ($expectedFileSize != $testFileSize) {
                echo "File sizes doesn't match for \"${file}\"! Documentation might not match the tested sample.\n";
            }

//            $actual = $this->parser->processFile($this->testFilesDir . DIRECTORY_SEPARATOR . $file);
        }

        $this->package->cleanup();
    }
}