<?php

/**
 * Replaces all occurences of new lines and multiple whitespaces with single whitespace
 *
 * @param string $str String to be proccessed
 * @return string String with only one whitespaces
 */
function simplify_string($str) {
    $str = preg_replace(array("/\n+/", "/ {2,}/"), ' ', $str);
    $str = trim($str);
    return $str;
}

function is_class($name) {
    if (strpos($name, '::') !== false || strpos($name, '->') !== false) {
        return true;
    } else {
        return false;
    }
}

function rewrite_names($name) {
    global $rewritename;
    $lname = strtolower($name);
    foreach ($rewritename as $pattern => $newName) {
        if (substr($lname, 0, strlen($pattern)) == $pattern) {
            $name = substr($newName, 0, strlen($pattern)) . substr($name, strlen($pattern));
        }
    }
    return $name;
}

function clear_source_code($source) {
    $source = trim(html_entity_decode(strip_tags(str_replace(array('<br />', '<br/>', '<br>'), "\n", $source))));
    //var_dump($source, substr($source, 0, 6), substr($source, -2));exit;
    if (substr($source, 0, 5) == '<?php') {
        $source = substr($source, 6); 
    }
    if (substr($source, -2) == '?>') {
        $source = substr($source, 0, -2); 
    }
    return trim($source);
}
