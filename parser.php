<?php

if (count($argv) == 1 || in_array($argv[1], array('/?', '--help', '-h', '-H'))) {
    echo <<<EOS
Parse all PHP documentation files and generates one big JSON with all
methods and classes.
Parser generated one extra file stats.json with current timestamp and number
of all methods and classes successfuly parsed and included in the database.json file.

Usage:
php parse.php doc_dir output_dir [--export-examples|--join-examples]

Where:
doc_dir    - path to "Many HTML files" documentation (http://www.php.net/download-docs.php)
output_dir - directory with generated JSON files
--export-examples (optional) - Export all code snippets to an extra file examples.json.
--join-examples   (optional) - Include all code snippets in the database.json file.

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

$dir = $argv[1]; // input directory
$outputDir = $argv[2]; // output directory
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
    //$index = 0;
    while (false !== ($file = readdir($handle))) {
        // filename starts with one of the selected categories
        if (in_array(substr($file, 0, strpos($file, '.')), $groups) && !in_array($file, $ignoreFiles)) {
            
            //echo substr($file, 0, strpos($file, '.')) . "\n";
            
            //echo "$file\n";
            $dom = new DOMDocument();
            @$dom->loadHTML(file_get_contents($dir . '/' . $file));
            // Most important object used to traverse HTML DOM structure
            $xpath = new DOMXPath($dom);
            
            $function = array();
            
            // function description
            $description = $xpath->query('//span[@class="dc-title"]');
            if ($description->length > 0) { // some functions/methods don't have any description
                $function['desc'] = simplify_string($xpath->query('//span[@class="dc-title"]')->item(0)->textContent);
            } else {
                $function['desc'] = null;
            }
            
            $version = $xpath->query('//p[@class="verinfo"]');
            if ($version->length > 0) { // check from which PHP version is it available
                $function['ver'] = trim($version->item(0)->textContent, '()');
            }
            
            // return value (consists of description and return data type)
            $function['return'] = array();
            
            // return value desription
            $items = $xpath->query('//div[@class="refsect1 returnvalues"]/p');
            if ($items->length > 0) {
                $function['return']['desc'] = simplify_string($items->item(0)->textContent);
            }
            // return value data type is parsed later...

            // see also part
            $seeAlsoArray = array();
            $seeAlso = $xpath->query('//div[@class="refsect1 seealso"]');
            if ($seeAlso->length > 0) {
                $lis = $xpath->query('.//li', $seeAlso->item(0));
                foreach ($lis as $li) {
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
                $function['name'] = str_replace('->', '::', $h1->item(0)->textContent);
                //$function['n'] = rewrite_names($function['n']);
                //$function['n'] = substr($function['n'], strpos($function['n'], '::') + 2);
                //$function['n'] = str_replace('->', '::', $function['n']
            }
            
            // check whether this function is part of a class
            if (is_class($function['name'])) {
                $function['class'] = substr($function['name'], 0, strpos($function['name'], '::'));
                $function['class'] = rewrite_names($function['class']);
                $function['name'] = substr($function['name'], strpos($function['name'], '::') + 2);
                
                if (!in_array($function['class'], $classes)) {
                    $classes[strtolower($function['class'])] = array(
                        'name'   => $function['class'],
                        'class'  => $function['class'],
                    );
                }
            } else {
                $function['class'] = null;
            }
            
            if ($function['name'] == "Installing/Configuring") {
                echo $file;exit;
            }
            
            // description container
            $descriptions = $xpath->query('//div[@class="methodsynopsis dc-description"]');
            foreach ($descriptions as $index => $description) {
            //if ($description->length > 0) {
                //if (!isset($function['n'])) {
                    
                $span = $xpath->query('./span[@class="type"]', $description);

                // return value data type (boolean, integer, mixed, ... )
                if ($span->length > 0 && !isset($function['return']['type'])) {
                    $function['return']['type'] = rewrite_names($span->item(0)->textContent);
                }

                //}
                // index - json filename
                
                // parameters
                $params = $xpath->query('span[@class="methodparam"]', $description);
                if ($params->length == 1 && $params->item(0)->textContent == 'void') {
                    $function['params'][$index] = null;
                } else {
                    $function['params'][$index] = array();
                    $optional = substr_count($description->textContent, '[');
                    for ($i=0; $i < $params->length; $i++) {
                        $param = $xpath->query('*', $params->item($i));
                        
                        $function['params'][$index][] = array(
                            'type'  => $param->item(0)->textContent, // type
                            'var'   => $param->length >= 2 ? $param->item(1)->textContent : false, // variable name
                            'beh'   => $params->length - $optional > $i ? 0 : 1, // behaviour (0 = mandatory, 1 = optional)
                        );
                        if ($param->length >= 3) {
                            $function['params'][$index][count($function['params'][$index]) - 1]['def'] = trim($param->item(2)->textContent, ' =');
                        }
                    }
                }
                
                if ($function['params']) { // has parameters
                    $function['params_desc'] = array(); // parameter descriptions
                    // fetch parameter detail description
                    $paramDescription = $xpath->query('//div[@class="refsect1 parameters"]//dd');
                    for ($i=0; $i < $paramDescription->length; $i++) {
                        // fetch for each parameter only paragraphs (sometimes it contains also tables, etc.)
                        $ps = $xpath->query('./p', $paramDescription->item($i));
                        $desc = '';
                        for ($j=0; $j < $ps->length; $j++) {
                            $desc .= ' ' . $ps->item($j)->textContent;
                        }
                        
                        $function['params_desc'][] = simplify_string($desc);
                    }
                }
                //print_r($function);exit;
                
            }
            
            
            if ($processExamples) {
                $examplesCont = $xpath->query('//div[@class="refsect1 examples"]');
                //echo $examplesCont->length . ',';
                if ($examplesCont->length > 0) {
                    $examples = array();
                    $exampleDiv = $xpath->query('div[@class="example"]', $examplesCont->item(0));
                    //echo $exampleDiv->length . ',';
                    
                    for ($i=0; $i < $exampleDiv->length; $i++) {
                        //$sourceCode = $xpath->query('//div[@class="phpcode"]', $exampleDiv->item($i))->item(0);
                        $output = null;
                        $sourceCode = $dom->saveXML($xpath->query('.//div[@class="phpcode"]', $exampleDiv->item($i))->item(0));
                        $outputDiv = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i));
                        if ($outputDiv->length > 0) {
                        	$output = $xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i))->item(0)->textContent;
                        }
                        //$output = $dom->saveXML($xpath->query('.//div[@class="cdata"]', $exampleDiv->item($i))->item(0));
                        //var_dump(str_replace('<br />', "\n", $sourceCode));exit;
                        // stript beginning and ending php tags
                        //$sourceCode = trim(substr($sourceCode, 5, strlen($sourceCode) - 2));
                        $title = $xpath->query('p', $exampleDiv->item($i))->item(0)->textContent;
                        $title = trim(preg_replace('/^(Example #\d+|Beispiel #\d+|Exemplo #\d+|Exemple #\d+|Przykład #\d+)/', '', $title));
                        $title = trim(preg_replace('/\s+/', ' ', $title));
                        
                        $examples[] = array (
                            'title'  => $title, 
                            'source' => clear_source_code($sourceCode),
                            'output' => $output ? clear_source_code($output) : null,
                        );
                    }
                    //print_r($examples);
                    
                    if ($processExamples == 'export') {
                        $exportExamples[$file] = $examples;
                    } elseif ($processExamples == 'join') {
                        $function['examples'] = $examples;
                    }
                    $totalMethodsWithExamples++;
                }
                
            }
            
            
            //echo strtolower($function['n']) . "\n";
            if (isset($function['name']) && $function['name']) {
                //$functions[strtolower(str_replace('::', '_', $function['n']))] = $function;
                $functions[$function['name']] = $function;
                //$functionsNames[] = $function['name'];
            } else {
                echo $file . ": no method name\n";
            }
            //print_r($function);
            
        }
    }
    closedir($handle);
}

//print_r($classes);exit;
$functions = array_merge($functions, $classes);
$functions = array_merge($functions);

ksort($functions);

foreach ($functions as &$function) {
    if ($function['seealso']) {
        foreach ($function['seealso'] as $i => $seealso) {
            //if (!in_array($seealso['name'], $functionsNames)) {
            if (!isset($functions[$seealso['name']])) {
                unset($function['seealso'][$i]);
            }
        }
    }
}


$modDatabase = array();
foreach ($functions as $fun) {
    $arr = array (
        'method'  => $fun['name'],
        'pclass'  => isset($fun['class']) ? $fun['class'] : null,
        'desc'    => $fun['desc'],
    );
    unset($fun['name']);
    unset($fun['class']);
    
    $arr['json'] = json_encode($fun);
    $modDatabase[] = $arr;
}


$stats = array (
    'methods'    => count($functions),
    'timestamp'  => time(),
    'examples'   => $totalMethodsWithExamples,
);

if ($processExamples == 'export') {
    file_put_contents(rtrim($outputDir) . '/examples.json', json_encode($exportExamples));
}

file_put_contents(rtrim($outputDir) . '/database.json', json_encode($modDatabase));
file_put_contents(rtrim($outputDir) . '/stats.json', json_encode($stats));
file_put_contents('functions.json', json_encode(array_keys($functions)));
//}

