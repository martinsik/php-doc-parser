<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;


class FeatureContext extends BehatContext {

    public function __construct(array $parameters) {
        $this->useContext('availability_subcontext_alias', new AvailabilityContext());
        $this->useContext('package_subcontext_alias', new PackageContext());
        $this->useContext('parser_subcontext_alias', new ParserContext());
        $this->useContext('parser_result_subcontext_alias', new ParserResultContext());
        $this->useContext('utils_subcontext_alias', new UtilsContext());
    }

}