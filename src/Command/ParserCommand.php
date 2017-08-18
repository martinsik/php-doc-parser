<?php

namespace DocParser\Command;

use Symfony\Component\Console\Command\Command;
use DocParser\Parser;

abstract class ParserCommand extends Command
{

    protected function stringExamplesParamsToConst($include) {
        if ($include == 'i') {
            return Parser::INCLUDE_EXAMPLES;
        } elseif ($include == 'e') {
            return Parser::EXPORT_EXAMPLES;
        } else {
            return Parser::SKIP_EXAMPLES;
        }
    }

}