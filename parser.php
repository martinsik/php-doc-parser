<?php

if (count($argv) == 1 || in_array($argv[1], array('/?', '--help', '-h', '-H'))) {
    echo <<<EOS
Parse all PHP documentation files and generates one big JSON with all
methods and classes.
Parser generated one extra file stats.json with current timestamp and number
of all methods and classes successfuly parsed and included in the database.json file.

Usage:
php parse.php [--export-examples|--join-examples|--print-test=fn_name] doc_dir output_dir

Where:
doc_dir    - path to "Many HTML files" documentation (http://www.php.net/download-docs.php)
output_dir - directory with generated JSON files
--export-examples (optional) - Export all code snippets to an extra file examples.json.
--join-examples   (optional) - Include all code snippets in the database.json file.
--print-test=fn_name (optional) - Print test function (eg. to check bug fixes)

EOS;
    exit;
}


/**
 * List of file prefixes that will be processed by the parser.
 * Feel free to modify according to your needs.
 */
require 'groups.php';

/**
 * Class names are in the original documentation quiet a mess, so we have to
 * rewrite some of their names.
 * I think you don't need to modify this file.
 */
require 'rewrite.php';

/**
 * List of files that will be ignored by the parser (installation,
 * configuration, ..., whatever files) because we don't want these fiels
 * in the generated output.
 * Feel free to modify according to your needs.
 */
require 'ignore.php';

/**
 * Some simple utility functions
 */
require 'utils.php';

$dir = $argv[$argc - 2]; // input directory
$outputDir = $argv[$argc - 1]; // output directory
if (!is_dir($outputDir)) {
    mkdir($outputDir);
}

// What can we do with code snippets
$processExamples = false;
if (in_array('--export-examples', $argv)) {
    $processExamples = 'export';
    $exportExamples = array();
} elseif (in_array('--join-examples', $argv)) {
    $processExamples = 'join';
}

// If we want to count methods with code snippets
if ($processExamples) {
    $totalMethodsWithExamples = 0;
}


// Array with all fynction/classes parsed by the parser
$functions = array();

// Array with all classes
$classes = array();

// This array is used only to generated 
$functionsNames = array();

if ($handle = opendir($dir)) {

    while (false !== ($file = readdir($handle))) {

        // filename starts with one of the selected categories
        $fileGroup = substr($file, 0, strpos($file, '.'));
        if (in_array($fileGroup, $groups) && !in_array($file, $ignoreFiles)) {
            
            $dom = new DOMDocument();
            @$dom->loadHTML(file_get_contents($dir . '/' . $file));
            // Most important object used to traverse HTML DOM structure
            $xpath = new DOMXPath($dom);
            
            $function = array();
            
            // function description
            $description = $xpath->query('//span[@class="dc-title"]');
            if ($description->length > 0) { // some functions don't have any description
                $function['desc'] = simplify_string($xpath->query('//span[@class="dc-title"]')->item(0)->textContent);
            } else {
                $function['desc'] = null;
            }
            
            // PHP version since this function is available
            $version = $xpath->query('//p[@class="verinfo"]');
            if ($version->length > 0) { // check from which PHP version is it available
                $function['ver'] = trim($version->item(0)->textContent, '()');
            }
            
            // return value desription
            $items = $xpath->query('//div[@class="refsect1 returnvalues"]/p');
            if ($items->length > 0) {
                $function['ret_desc'] = simplify_string($items->item(0)->textContent);
            }
            // return value data type is parsed later...

            // see also part
            $seeAlsoArray = array();
            $seeAlso = $xpath->query('//div[@class="refsect1 seealso"]');
            if ($seeAlso->length > 0) {
                $lis = $xpath->query('.//li', $seeAlso->item(0));
                foreach ($lis as $li) {
                    // store just the name and description without parenthesis
                    $text = explode('-', $li->textContent);
                    $name = rtrim(trim($text[0]), '()');
                    if (strpos($name, ' ') === false) {
                        $seeAlsoArray[] = array(
                            'name' => $name,
                            'desc' => isset($text[1]) ? trim($text[1]) : false,
                        );
                    }
                }
            }
            $function['seealso'] = $seeAlsoArray;
            
            // filename (url)
            $function['url'] = substr($file, 0, -5);
            
            // function name
            $h1 = $xpath->query('//h1');
            if ($h1->length != 0) {
                // make all function names consistent
                $function['name'] = str_replace('->', '::', $h1->item(0)->textContent);
            }
            
            // check whether this function is part of a class
            if (is_class($function['name'])) {
                // class name
                $function['class'] = substr($function['name'], 0, strpos($function['name'], '::'));
                $function['class'] = rewrite_names($function['class']);
                // function name
                $function['name'] = substr($function['name'], strpos($function['name'], '::') + 2);
                
                if (!in_array($function['class'], $classes)) {
                    /**
                     * @TODO: Distinguish classes and function in a different way
                     */
                    $classes[strtolower($function['class'])] = array(
                        'name'   => $function['class'],
                        'class'  => $function['class'],
                    );
                }
            } else {
                $function['class'] = null;
            }


            // function description (parameters)
            // Note: One function can have multiple parameter count (see http://www.php.net/manual/en/function.strtr.php)
            $function['params'] = array();
            $funcDescription = $xpath->query('//div[@class="methodsynopsis dc-description"]');
            foreach ($funcDescription as $index => $description) {
                $parsedParams = array('list' => array());

                // return value data type (boolean, integer, mixed, ... )
                $span = $xpath->query('./span[@class="type"]', $description);
                if ($span->length > 0 && !isset($function['return']['type'])) {
                    $parsedParams['ret_type'] = rewrite_names($span->item(0)->textContent);
                }

                // parameter containers
                $params = $xpath->query('span[@class="methodparam"]', $description);
                // skip empty parameter list (function declaration that doesn't take any parameter)
                if ($params->length != 1 && $params->item(0)->textContent != 'void') {
                    $optional = substr_count($description->textContent, '[');
                    $paramDescriptions = $xpath->query('//div[@class="refsect1 parameters"]//dd');

                    for ($i=0; $i < $params->length; $i++) {
                        // fetch for each parameter only paragraphs (sometimes it contains also tables, etc.)
                        $ps = $xpath->query('./p', $paramDescriptions->item($i));
                        // ... and join them into a single string
                        $desc = '';
                        for ($j=0; $j < $ps->length; $j++) {
                            $desc .= ' ' . $ps->item($j)->textContent;
                        }

                        $paramNodes = $xpath->query('*', $params->item($i));
                        $param = array(
                            'type'  => $paramNodes->item(0)->textContent, // type
                            'var'   => $paramNodes->length >= 2 ? $paramNodes->item(1)->textContent : false, // variable name
                            'beh'   => $paramNodes->length - $optional > $i ? 0 : 1, // behaviour (0 = mandatory, 1 = optional)
                            'desc'  => simplify_string($desc),
                        );
                        if ($paramNodes->length >= 3) {
                            $param['def'] = trim($paramNodes->item(2)->textContent, ' =');
                        }
                        $parsedParams['list'][] = $param;
                    }
                }
                $function['params'][] = $parsedParams;
            }
            
            // proccess examples
            if ($processExamples) {
                // check if there are any examples
                $examplesCont = $xpath->query('//div[@class="refsect1 examples"]');
                if ($examplesCont->length > 0) {
                    $examples = array();

                    // one example
                    $exampleDiv = $xpath->query('div[@class="example"]', $examplesCont->item(0));
                    for ($i=0; $i < $exampleDiv->length; $i++) {
                        $output = null;
                        // get as pure text, without any syntax highlighting
                        $sourceCode = $dom->saveXML($xpath->query('.//div[@class="phpcode"]', $exampleDiv->item($i))->item(0));
                        $outputDiv = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i));
                        if ($outputDiv->length > 0) {
                        	$output = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i))->item(0)->textContent;
                        }

                        // code example title, stript beginning and ending php tags
                        $title = $xpath->query('p', $exampleDiv->item($i))->item(0)->textContent;
                        // remove some unnecessary stuff
                        $title = trim(preg_replace('/^(Example #\d+|Beispiel #\d+|Exemplo #\d+|Exemple #\d+|Przykład #\d+)/', '', $title));
                        $title = trim(preg_replace('/\s+/', ' ', $title));
                        
                        $examples[] = array (
                            'title'  => $title, 
                            'source' => clear_source_code($sourceCode),
                            'output' => $output ? clear_source_code($output) : null,
                        );
                    }

                    // keep examples in a seperate array or put it among other function params
                    if ($processExamples == 'export') {
                        $exportExamples[$file] = $examples;
                    } elseif ($processExamples == 'join') {
                        $function['examples'] = $examples;
                    }
                    $totalMethodsWithExamples++;
                }
                
            }
            
            // add function into the final list
            if (isset($function['name']) && $function['name']) {
                $functions[$function['name']] = $function;
                if (count($functions) % 100 == 0) {
                    echo '.';
                }
            } else {
                echo $file . ": no method name\n";
            }
        }

    }
    closedir($handle);
    echo "\nparsing finished\n";
}

$functions = array_merge($functions, $classes);

// not necessary but it's easier to find buggy function manually
ksort($functions);

// traverse all function's "see also" and drop those references that were not parsed
// (that are not included in the generated JSON output)
foreach ($functions as &$function) {
    if ($function['seealso']) {
        foreach ($function['seealso'] as $i => $seealso) {
            if (!isset($functions[$seealso['name']])) {
                unset($function['seealso'][$i]);
            }
        }
    }
}

$stats = array (
    'methods'    => count($functions),
    'timestamp'  => time(),
    'examples'   => $totalMethodsWithExamples ?: 0,
);

if ($processExamples == 'export') {
    file_put_contents(rtrim($outputDir) . '/examples.json', json_encode($exportExamples, JSON_NUMERIC_CHECK));
}

$testFuncion = strtolower(get_cmd_arg_value($argv, '--print-test'));
if ($testFuncion) {
    echo "\nPrinting test function '$testFuncion':\n";
    print_r($functions[$testFuncion]);
}

file_put_contents(rtrim($outputDir) . '/database.json', json_encode($functions, JSON_NUMERIC_CHECK));
file_put_contents(rtrim($outputDir) . '/stats.json', json_encode($stats, JSON_NUMERIC_CHECK));
file_put_contents('functions.json', json_encode(array_keys($functions)));


