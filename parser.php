<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (count($argv) == 1 || in_array($argv[1], array('/?', '--help', '-h', '-H'))) {
    echo <<<EOS
Parse all PHP documentation files and generates one big JSON with all
methods and classes.
Parser generated one extra file stats.json with current timestamp and number
of all methods and classes successfully parsed and included in the database.json file.

Usage:
php parse.php [--export-examples|--include-examples|--print-test=fn_name] doc_dir output_dir

Where:
doc_dir    - path to "Many HTML files" documentation (http://www.php.net/download-docs.php)
output_dir - directory with generated JSON files
--export-examples (optional) - Export all code snippets to an extra file examples.json.
--include-examples (optional) - Include all code snippets in the database.json file.
--disable-progress (optional) - Don't show any information about processed items
--print-test=fn_name (optional) - Print test function - for debugging only

EOS;
    exit(0);
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
$outputDir = rtrim($argv[$argc - 1], '/'); // output directory
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// What can we do with code snippets
$processExamples = false;
if (in_array('--export-examples', $argv)) {
    $processExamples = 'export';
    $exportExamples = array();
} elseif (in_array('--include-examples', $argv)) {
    $processExamples = 'join';
}

$showProgressbar = !in_array('--disable-progress', $argv);

// If we want to count methods with code snippets
//if ($processExamples) {
$totalMethodsWithExamples = 0;
//}

$testFunction = get_cmd_arg_value($argv, '--print-test');

// Array with all fynction/classes parsed by the parser
$functions = [];
$functionsCount = 0;

// Array with all classes
$classes = [];

// This array is used only to generated 
$functionsNames = [];

$allowed_class_groups = get_classes($dir);

if ($handle = opendir($dir)) {
    while (false !== ($file = readdir($handle))) {
        // filename starts with one of the selected categories skip everything else
        $file_group = substr($file, 0, strpos($file, '.'));

        if ($file_group != 'function' && (!isset($allowed_class_groups[$file_group]) || isset($ignoreFiles[$file]))) {
//            echo "skip: $file - $file_group\n";
            continue;
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML(file_get_contents($dir . '/' . $file));
        // Most important object used to traverse HTML DOM structure
        $xpath = new \DOMXPath($dom);

        $function = [];

        // function name
        $h1 = $xpath->query('//h1');
        if ($h1->length != 0) {
            for ($i = 0; $i < $h1->length; $i++) {
                // make all function names consistent
                $funcName = str_replace('->', '::', $h1->item($i)->textContent);
                if ($i == 0) {
                    $function['name'] = $funcName;
                } else {
                    if (!isset($function['alt_names'])) {
                        $function['alt_names'] = [];
                    }
                    $function['alt_names'][] = $funcName;
                }

            }
        }

        // check if it managed to find function name
        if (!isset($function['name']) || !$function['name']) {
            continue;
        }

        // function short description
        $description = $xpath->query('//span[@class="dc-title"]');
        if ($description->length > 0) { // some functions don't have any description
            $function['desc'] = trim(simplify_string($description->item(0)->textContent), '.') . '.';
        } else {
            $function['desc'] = null;
        }

        // function long description
        $longDescParagraphs = $xpath->query('//div[contains(@class,"description")]//p[contains(@class,"para") or contains(@class,"simpara")]');
        $function['long_desc'] = extract_formated_text($longDescParagraphs);
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
            $function['ret_desc'] = simplify_string($items->item(0)->textContent);
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
        $function['url'] = substr($file, 0, -5);

        // check whether this function is part of a class
        if (is_class($function['name'])) {
            // class name
            $function['class'] = substr($function['name'], 0, strpos($function['name'], '::'));
            $function['class'] = rewrite_names($function['class']);
            // function name
            $function['name'] = substr($function['name'], strpos($function['name'], '::') + 2);

            if (!isset($classes[$function['class']])) {
                /* @todo: distinguish classes and function in a different way */
                $classes[$function['class'] . '::'] = array(
                    'name'  => null,
                    'class' => $function['class'],
                );
            }
        } else {
            $function['class'] = null;
        }

        // check if it managed to find function name
        if (!$function['class'] && !isset($function['params']) && !isset($function['desc']) && !isset($function['long_desc'])) {
            continue;
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
            if (/*$params->length != 1 && */$params->item(0)->textContent != 'void') {
                $optional = substr_count($description->textContent, '[');
                $paramDescriptions = $xpath->query('//div[contains(@class,"parameters")]//dd');

                for ($i=0; $i < $params->length; $i++) {
                    $paramNodes = $xpath->query('*', $params->item($i));
                    $param = array(
                        'type'  => $paramNodes->item(0) ? $paramNodes->item(0)->textContent : 'unknown', // type
                        'var'   => $paramNodes->length >= 2 ? $paramNodes->item(1)->textContent : false, // variable name
                        'beh'   => $params->length - $optional > $i ? 0 : 1, // behaviour (0 = mandatory, 1 = optional)
                        'desc'  => extract_formated_text($xpath->query('./*[self::p or self::ul or self::blockquote or self::table or self::div[@class="methodsynopsis dc-description"]]', $paramDescriptions->item($i)), $xpath), // paragraphs with text description
                    );
                    if ($paramNodes->length >= 3) {
                        $param['def'] = trim($paramNodes->item(2)->textContent, ' =');
                    }
                    $parsedParams['list'][] = $param;
                }

            }
            $function['params'][] = $parsedParams;
        }

        // parse all examples on the page
        if ($processExamples) {
            $examples = array();
            // grab all code examples
            $exampleDiv = $xpath->query('//div[@class="example" or @class="informalexample"]');
            for ($i=0; $i < $exampleDiv->length; $i++) {
                $output = null;
                // get as pure text, without any syntax highlighting
                $sourceCode = $dom->saveXML($xpath->query('.//div[@class="phpcode"]', $exampleDiv->item($i))->item(0));
                $outputDiv = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i));
                if ($outputDiv->length > 0) {
                    $output = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i))->item(0)->textContent;
                }

                // code example title, strip beginning and ending php tags
                $ps = $xpath->query('p', $exampleDiv->item($i));
                if ($ps->length > 0) {
                    $title = $ps->item(0)->textContent;
                    // remove some unnecessary stuff
                    $title = trim(preg_replace('/^(Example #\d+|Beispiel #\d+|Exemplo #\d+|Exemple #\d+|Przykład #\d+)/', '', $title));
                    $title = trim(preg_replace('/\s+/', ' ', $title));
                } else {
                    $title = null;
                }

                $examples[] = array(
                    'title'  => $title,
                    'source' => clear_source_code($sourceCode),
                    'output' => trim($output) ?: null,
                );
            }

            // keep examples in a seperate array or put it among other function params
            if ($processExamples == 'export') {
                $exportExamples[$file] = $examples;
            } elseif ($processExamples == 'join') {
                $function['examples'] = $examples;
            }
            $totalMethodsWithExamples++;
//            }
        }

        // add function into the final list
        if (isset($function['name']) && $function['name']) {

            $functions[(isset($function['class']) ? $function['class'] . '::' : '') . $function['name']] = $function;
            $functionsCount++;

            if ($showProgressbar && $functionsCount % 100 == 0) {
                echo '.';
//                echo $functionsCount . PHP_EOL;
            }
        } else {
            echo $file . ": no method name found\n";
        }

    }
    closedir($handle);
    echo "\n";
}

$functions = array_merge($functions, $classes);

//uksort($functions, function($a, $b) {
//    return strcasecmp($a, $b);
//});

// traverse all function's "see also" and drop those references that were not parsed
// (that are not included in the generated JSON output)
// sometimes there're links that aren't interesting for us
foreach ($functions as &$function) {
    if (isset($function['seealso'])) {
        foreach ($function['seealso'] as $i => $seealso) {
            if (!isset($functions[$seealso])) {
                unset($function['seealso'][$i]);
            }
        }
    }
}

// overall statistics
$stats = array (
    'methods'    => count($functions),
    'timestamp'  => time(),
    'examples'   => $totalMethodsWithExamples ?: 0,
);

if ($processExamples == 'export') {
    file_put_contents($outputDir . '/examples.json', json_encode($exportExamples, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
}

file_put_contents($outputDir . '/database.json', json_encode($functions, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));
file_put_contents($outputDir . '/stats.json', json_encode($stats, JSON_NUMERIC_CHECK));
file_put_contents($outputDir . '/functions.json', json_encode(array_keys($functions)));


// print parsed test function and exit
if ($testFunction) {
    if (isset($functions[$testFunction])) {
        echo json_encode($functions[$testFunction], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo "Unknown test method $testFunction.\n";
        exit(1);
    }
}

exit(0);
