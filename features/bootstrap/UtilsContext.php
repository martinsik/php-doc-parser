<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use DocParser\Utils;



class UtilsContext extends BehatContext {

    private $testArrays;

    /**
     * @When /^I have a very large multi-dimensional array:$/
     */
    public function iHaveAVeryLargeMultiDimensionalArray(TableNode $table)
    {
        $this->testArrays = $table->getHash();
    }

    /**
     * @Then /^I need to convert it recursively to a low level more efficient SplFixedArray$/
     */
    public function iNeedToConvertItRecursivelyToALowLevelMoreEfficientSplfixedarray()
    {
        /**
         * Probably not very helpful
         */
        foreach ($this->testArrays as $json) {
            $array = json_decode($json['test-array-as-json'], true);
//            $fixed = Utils::toFixedArray($array);
//            var_dump(memory_get_peak_usage(true) / 1000000);
        }

    }

}