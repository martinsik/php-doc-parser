<?php

use Behat\Behat\Context\BehatContext,
    Behat\Gherkin\Node\TableNode;

use DocParser\Availability;


/**
 * Features context.
 */
class AvailabilityContext extends BehatContext
{

    private $languages;


    /**
     * @When /^I want a list all available languages$/
     */
    public function iWantAListAllAvailableLanguages()
    {
        $avail = new Availability();
        $this->languages = $avail->listPackages();
    }

    /**
     * @Then /^I should get these and more:$/
     */
    public function iShouldGetTheseAndMore(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            assertArrayHasKey($row['lang'], $this->languages, 'Language ' . $row['lang'] . ' must be among available languages.');
            assertEquals($row['title'], $this->languages[$row['lang']]);
        }
        foreach ($this->languages as $code => $title) {
            assertNotEmpty($title);
        }
        assertGreaterThan(10, count($this->languages), 'There are missing languages for sure!');
    }

}
