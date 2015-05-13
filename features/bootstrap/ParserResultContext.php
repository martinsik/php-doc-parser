<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use DocParser\ParserResult;

class ParserResultContext extends BehatContext {

    /**
     * @var \DocParser\ParserResults
     */
    private $results;

    /**
     * @When /^I have multiple parser results like:$/
     */
    public function iHaveMultipleParserResultsLike(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            $result = new ParserResult();
            $file = $row['test-file'];
            $result->setResult($file, $row['result']);
            if ($row['examples']) {
                foreach (explode(', ', $row['examples']) as $example) {
                    $result->addExample($file, $example);
                }
            }

//            $result->addWarning($file, $row['warning'] ? explode(', ', $row['warning']) : []);
            if ($row['warning']) {
                foreach (explode(', ', $row['warning']) as $warning) {
                    $result->addWarning($file, $warning);
                }
            }

            if ($row['skip']) {
                $result->addSkipped($file);
            }

            if (isset($this->results[$file])) {
                $this->results[$file]->mergeWithResult($result);
            } else {
                $this->results[$file] = $result;
            }
        }
    }

    /**
     * @Then /^after merging them I\'m expecting one result with:$/
     */
    public function afterMergingThemIMExpectingOneResultWith(TableNode $table)
    {
        $result = new ParserResult();
        foreach ($this->results as $r) {
            $result->mergeWithResult($r);
        }

        $warningsCount = $examplesCount = 0;
        foreach ($table->getHash() as $row) {
            $file = $row['test-file'];

            $warningsCount += count(explode(', ', $row['warning']));
            $examplesCount += count(explode(', ', $row['examples']));

            assertEquals(explode(', ', $row['warning']), $result->getWarnings($file));
            assertEquals(explode(', ', $row['examples']), $result->getExamples($file));
            assertEquals($row['result'], $result->getResult($file));
            if ($row['skip']) {
                assertTrue($result->isSkipped($file));
            } else {
                assertFalse($result->isSkipped($file));
            }
        }

        assertEquals($warningsCount, $result->countAllWarnings());
        assertEquals($examplesCount, $result->countAllExamples());
    }

}