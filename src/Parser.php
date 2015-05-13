<?php

namespace DocParser;

use Symfony\Component\Filesystem\Filesystem;
use DocParser\Utils;

class Parser {

    const SKIP_EXAMPLES = 0;
    const INCLUDE_EXAMPLES = 1;
    const EXPORT_EXAMPLES = 2;


    public function processDir($dir, $parseExamples = 0, \Closure $progressCallback = null) {
        $results = new ParserResult();
        $files = $this->getFilesToProcess($dir);
        $processed = 0;

        foreach ($files as $file) {
            $progressCallback(basename($file), count($files), $processed);
            $this->processFile($file, $parseExamples, $results);
            $processed++;
        }
        return $results;
    }

    public function getFilesToProcess($dir) {
        return glob($dir . DIRECTORY_SEPARATOR . '/*.html');
    }

    /**
     * @param $file
     * @param $parseExamples
     * @return ParserResult
     */
    public function processFile($file, $parseExamples, $result = null) {
        if (!$result) {
            $result = new ParserResult();
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML(file_get_contents($file));
        // Most important object used to traverse HTML DOM structure
        $xpath = new \DOMXPath($dom);

        $function = [];

        // Parse function name.
        $h1 = $xpath->query('//h1[@class="refname"]');
//        if ($h1->length != 0) {
//            $function['name'] = [];
//            for ($i = 0; $i < $h1->length; $i++) {
//                // make all function names consistent
//                $funcName = str_replace('->', '::', $h1->item($i)->textContent);
//                $function['name'][] = $funcName;
//            }
//        }

        // Check if it managed to find function name.
        if ($h1->length == 0) {
            $result->addSkipped(basename($file));
            return $result;
        }

        // Primary name
//        $funcName = $function['name'][0];

        // Function short description.
        $description = $xpath->query('//span[@class="dc-title"]');
        if ($description->length > 0) { // some functions don't have any description
            $function['desc'] = trim(Utils::simplifyString($description->item(0)->textContent), '.') . '.';
        } else {
            $function['desc'] = null;
        }

        // Function long description.
        $longDescParagraphs = $xpath->query('//div[contains(@class,"description")]//p[contains(@class,"para") or contains(@class,"simpara")]');
        $function['long_desc'] = Utils::extractFormattedText($longDescParagraphs);
        if ($function['long_desc']) {
            $function['long_desc'] = trim($function['long_desc'], '.') . '.';
        }

        // PHP version since this function is available
        $version = $xpath->query('//p[@class="verinfo"]');
        if ($version->length > 0) { // check from which PHP version is it available
            $function['ver'] = trim($version->item(0)->textContent, '()');
        } else {
            $function['ver'] = null;
        }

        // return value desription
        $items = $xpath->query('//div[contains(@class,"returnvalues")]/p');
        if ($items->length > 0) {
            $function['ret_desc'] = Utils::simplifyString($items->item(0)->textContent);
        }
        // return value data type is parsed later...

        // see also part
        $seeAlso = $xpath->query('//div[contains(@class,"seealso")]');
        if ($seeAlso->length > 0) {
            $function['seealso'] = [];
            //$seeAlsoArray = array();
            $lis = $xpath->query('.//li', $seeAlso->item(0));
            foreach ($lis as $li) {
                // store just the name and description without parenthesis
                $text = explode('-', $li->textContent);
                $name = rtrim(trim($text[0]), '()');
                if (strpos($name, ' ') === false) {
                    $function['seealso'][] = $name;
                }
            }
        }

        // filename (url)
        $function['filename'] = substr(basename($file), 0, -5);

        // function description (parameters)
        // Note: One function can have multiple parameter count (see http://www.php.net/manual/en/function.strtr.php)
        $function['params'] = [];
        $funcDescription = $xpath->query('//div[@class="refsect1 description"]/div[@class="methodsynopsis dc-description"]');
        foreach ($funcDescription as $index => $description) {
            $altName = str_replace('->', '::', $xpath->query('./span[@class="methodname"]', $description)->item(0)->textContent);
            $parsedParams = [
                'list' => [],
                'name' => $altName,
            ];

            // return value data type (boolean, integer, mixed, ... )
            $span = $xpath->query('./span[@class="type"]', $description);
            if ($span->length > 0 && !isset($function['return']['type'])) {
                $parsedParams['ret_type'] = $span->item(0)->textContent;
//                $parsedParams['ret_type'] = rewrite_names($span->item(0)->textContent);
            }

            // parameter containers
            $params = $xpath->query('span[@class="methodparam"]', $description);
            // skip empty parameter list (function declaration that doesn't take any parameter)
            if (/*$params->length != 1 && */$params->item(0)->textContent != 'void') {
                $optional = substr_count($description->textContent, '[');
                $paramDescriptions = $xpath->query('//div[contains(@class,"parameters")]//dd');

                for ($i = 0; $i < $params->length; $i++) {
                    $paramNodes = $xpath->query('*', $params->item($i));
                    $descPattern = './*[self::p or self::ul or self::blockquote or self::table or self::div[@class="methodsynopsis dc-description"]]';

                    $param = array(
                        'type'  => $paramNodes->item(0) ? $paramNodes->item(0)->textContent : 'unknown', // type
                        'var'   => $paramNodes->length >= 2 ? $paramNodes->item(1)->textContent : false, // variable name
                        'beh'   => $params->length - $optional > $i ? 'required' : 'optional',
                        'desc'  => Utils::extractFormattedText($xpath->query($descPattern, $paramDescriptions->item($i)), $xpath),
                    );
                    if ($paramNodes->length >= 3) {
                        $param['default'] = trim($paramNodes->item(2)->textContent, ' =');
                    }
                    $parsedParams['list'][] = $param;
                }
            }
            $function['params'][] = $parsedParams;
        }

        if (!$function['params'] || (!isset($function['desc']) && !isset($function['long_desc']))) {
            $result->addSkipped(basename($file));
            return $result;
        }

//        var_dump($function['params']);
        $funcName = $function['params'][0]['name'];

        foreach ($function['params'] as $index => $param) {
            // For all alternative names use just a reference to the first name
            $result->setResult($function['params'][$index]['name'], $index == 0 ? $function : $funcName);
        }

        // parse all examples on the page
        if ($parseExamples != self::SKIP_EXAMPLES) {
//            $examples = array();
            // grab all lang examples
            $exampleDiv = $xpath->query('//div[@class="example" or @class="informalexample"]');
            for ($i=0; $i < $exampleDiv->length; $i++) {
                $output = null;
                // get as pure text, without any syntax highlighting
                $sourceCode = $dom->saveXML($xpath->query('.//div[@class="phpcode"]', $exampleDiv->item($i))->item(0));
                $outputDiv = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i));
                if ($outputDiv->length > 0) {
                    $output = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i))->item(0)->textContent;
                }

                // lang example title, strip beginning and ending php tags
                $ps = $xpath->query('p', $exampleDiv->item($i));
                if ($ps->length > 0) {
                    $title = $ps->item(0)->textContent;
                    // remove some unnecessary stuff
                    $title = trim(preg_replace('/^(Example #\d+|Beispiel #\d+|Exemplo #\d+|Exemple #\d+|Przykład #\d+)/', '', $title));
                    $title = trim(preg_replace('/\s+/', ' ', $title));
                } else {
                    $title = null;
                }

                $example = [
                    'title'  => $title,
                    'source' => Utils::clearSourceCode($sourceCode),
                    'output' => trim($output) ?: null,
                ];

                // Skip examples with malformed UTF-8 characters.
                // @todo: check where's the problem
                json_encode($example, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
                if (json_last_error()) {
                    $result->addWarning($funcName, json_last_error_msg());
                } else {
                    $result->addExample($funcName, $example);
                }

            }


            // Keep examples in a seperate array or put it among other function params.


//            if ($parseExamples == self::EXPORT_EXAMPLES) {
//                $exportExamples[$file] = $examples;
//            } elseif ($parseExamples == self::INCLUDE_EXAMPLES) {
//                $function['examples'] = $examples;
//            }
//            $totalMethodsWithExamples++;
//            }
        }



        return $result;

        // add function into the final list
//        if (isset($function['name']) && $function['name']) {
//
//            $functions[(isset($function['class']) ? $function['class'] . '::' : '') . $function['name']] = $function;
//            $functionsCount++;
//
//            if ($showProgressbar && $functionsCount % 100 == 0) {
//                echo '.';
////                echo $functionsCount . PHP_EOL;
//            }
//        } else {
//            echo $file . ": no method name found\n";
//        }
    }

}