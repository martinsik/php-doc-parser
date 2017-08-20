<?php

namespace DocParser;

use Symfony\Component\Filesystem\Filesystem;
use DocParser\Utils;

class Parser {
    /**
     * Process all files in the directory with selected by getFilesToProcess() method.
     *
     * @param string $dir Directory to search for files
     * @param boolean $parseExamples Whether or not include also examples
     * @param callable $progressCallback Optional callback used to monitor progress
     * @return ParserResult Parse result including all warnings and skipped files
     */
    public function processDir($dir, $parseExamples = true, \Closure $progressCallback = null) {
        $results = new ParserResult();
        $files = $this->getFilesToProcess($dir);
        $processed = 0;

        // Parse each file.
        foreach ($files as $file) {
            $progressCallback(basename($file), count($files), $processed);
            $this->processFile($file, $parseExamples, $results);
            $processed++;
        }
        return $results;
    }

    /**
     * @param string $dir Directory where we want to search files
     * @return array
     */
    public function getFilesToProcess($dir) {
        return glob($dir . DIRECTORY_SEPARATOR . '/*.html');
    }

    /**
     * Process single file.
     *
     * @param string $file Source file
     * @param boolean $parseExamples Whether or not include also examples
     * @return ParserResult Parse result including all warnings and skipped files
     */
    public function processFile($file, $parseExamples = true, $result = null) {
        if (!$result) {
            $result = new ParserResult();
        }

        if (!file_exists($file)) {
            throw new \Exception("File \"${file}\" doesn't exist.");
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML(file_get_contents($file));
        // Most important object used to traverse HTML DOM structure.
        $xpath = new \DOMXPath($dom);

        $function = [];

        // Parse function name.
        $h1 = $xpath->query('//h1[@class="refname"]');

        // Check if it managed to find function name.
        if ($h1->length == 0) {
            $result->addSkipped(basename($file));
            return $result;
        }

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

        // PHP version since this function is available.
        $version = $xpath->query('//p[@class="verinfo"]');
        if ($version->length > 0) { // check from which PHP version is it available
            $function['ver'] = trim($version->item(0)->textContent, '()');
        } else {
            $function['ver'] = null;
        }

        // Return value description.
        $items = $xpath->query('//div[contains(@class,"returnvalues")]/p');
        if ($items->length > 0) {
            $function['ret_desc'] = Utils::simplifyString($items->item(0)->textContent);
        }

        // "See also" methods
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

        // All function parameters in an array of arrays because some function have mutliple definitions.
        // Note: One function can have multiple parameter count (see http://www.php.net/manual/en/function.strtr.php)
        $function['params'] = [];
        $funcDescription = $xpath->query('//div[@class="refsect1 description"]/div[@class="methodsynopsis dc-description"]');
        foreach ($funcDescription as $index => $description) {
            // Function name for this parameter list.
            $altName = str_replace('->', '::', $xpath->query('./span[@class="methodname"]', $description)->item(0)->textContent);
            $parsedParams = [
                'list' => [],
                'name' => $altName,
            ];

            // Return value type (boolean, integer, mixed, ... ).
            $span = $xpath->query('./span[@class="type"]', $description);
            if ($span->length > 0 && !isset($function['return']['type'])) {
                $parsedParams['ret_type'] = $span->item(0)->textContent;
            }

            // Parameter containers.
            $params = $xpath->query('span[@class="methodparam"]', $description);

            // skip empty parameter list (function declaration that doesn't take any parameter)
            if (/*$params->length != 1 && */$params->item(0)->textContent != 'void') {
                $optional = substr_count($description->textContent, '[');
                $allParameters = $xpath->query('//div[contains(@class,"parameters")]/dl/dt/code[@class="parameter"]');

                for ($i = 0; $i < $params->length; $i++) {
                    $paramNodes = $xpath->query('*', $params->item($i));
                    $descPattern = './*[self::p or self::ul or self::blockquote or self::table or self::div[@class="methodsynopsis dc-description"]]';

                    $paramDescriptions = null;
                    $varName = $paramNodes->item(1)->textContent;

                    foreach ($allParameters as $j => $paramDesc) {
                        if (ltrim($varName, '$&') == $paramDesc->textContent) {
                            $paramDescriptions = $xpath->query('//div[contains(@class,"parameters")]/dl/dd[' . ($j + 1) . ']');
                            break;
                        }
                    }

                    // Single parameter.
                    $param = array(
                        'type'  => $paramNodes->item(0) ? $paramNodes->item(0)->textContent : 'unknown', // type
                        'var'   => $varName, // variable name
                        'beh'   => $params->length - $optional > $i ? 'required' : 'optional', // required/optional
                        // parameter description
                        'desc'  => $paramDescriptions ? Utils::extractFormattedText($xpath->query($descPattern, $paramDescriptions->item(0)), $xpath) : null,
                    );
                    // Default value for this parameter
                    if ($paramNodes->length >= 3) {
                        $param['default'] = trim($paramNodes->item(2)->textContent, ' =');
                    }
                    $parsedParams['list'][] = $param;
                }
            }
            $function['params'][] = $parsedParams;
        }

        // If parser didn't find any parameters, no short or long description then it's probably not a function in this file
        if (!$function['params'] || (!isset($function['desc']) && !isset($function['long_desc']))) {
            $result->addSkipped(basename($file));
            return $result;
        }


        // Find all possible names for this function
        $names = [];
        foreach ($function['params'] as $index => $param) {
            $names[] = strtolower($function['params'][$index]['name']);
        }
        $funcName = $names[0];

        // Use all alternative names just as a reference to the first (primary) name.
        foreach (array_unique($names) as $index => $name) {
            $result->setResult($name, $index == 0 ? $function : $funcName);
        }

        // Parse all examples in this file.
        if ($parseExamples) {
            // Find all source code containers.
            $exampleDiv = $xpath->query('//div[@class="example" or @class="informalexample"]');
            for ($i=0; $i < $exampleDiv->length; $i++) {
                $output = null;
                // Get source code as pure text, without any syntax highlighting tags.
                $sourceCode = $dom->saveXML($xpath->query('.//div[@class="phpcode"]', $exampleDiv->item($i))->item(0));
                $outputDiv = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i));
                if ($outputDiv->length > 0) {
                    $output = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i))->item(0)->textContent;
                }

                // Example title, strip beginning and ending php tags.
                $ps = $xpath->query('p', $exampleDiv->item($i));
                if ($ps->length > 0) {
                    $title = $ps->item(0)->textContent;
                    // Remove some unnecessary stuff.
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
                    $result->addWarning($funcName, 'Example "' . $title . '": ' . json_last_error_msg());
                } else {
                    $result->addExample($funcName, $example);
                }

            }
        }

        return $result;
    }
}