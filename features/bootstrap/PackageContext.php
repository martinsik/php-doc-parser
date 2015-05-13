<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use DocParser\Availability;
use DocParser\Package;

class PackageContext extends BehatContext {

    /**
     * @var Package
     */
    private $package;
    private $dir;
    private $pagesDir;

    /**
     * @When /^I need "([^"]*)" manual from "([^"]*)"$/
     */
    public function iNeedManualFrom($lang, $mirror)
    {
        $this->package = new Package($lang, $mirror);
    }

    /**
     * @Then /^The package can be downloaded to system\'s tmp dir$/
     */
    public function thePackageCanBeDownloadedToSystemSTmpDir()
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php-doc-parser-test';
        @mkdir($this->dir);

        $file = $this->dir . DIRECTORY_SEPARATOR . $this->package->getOrigFilename();

        $this->package->download($file);
    }

    /**
     * @Given /^Unpack files to the same directory:$/
     */
    public function unpackFilesToTheSameDirectory(PyStringNode $string)
    {
        $expectedFiles = $string->getLines();
        $this->pagesDir = $this->package->unpack($expectedFiles);
        assertFileExists($this->pagesDir);

        chdir($this->pagesDir);
        $foundFiles = array_flip(glob('*.html'));
        assertCount(count($expectedFiles), $foundFiles);

        foreach ($expectedFiles as $file) {
            assertArrayHasKey($file, $foundFiles);
        }
    }

    /**
     * @Given /^Cleanup files when it\'s all done$/
     */
    public function cleanupFilesWhenItSAllDone()
    {
        $this->package->cleanup();
        assertFileNotExists($this->pagesDir, 'Directory "' . $this->pagesDir . '" wasn\'t removed.');
    }

}
