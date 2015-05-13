<?php

namespace DocParser;

class Utils {

    public static function simplifyString($str) {
        $str = preg_replace(array("/\n+/", "/ {2,}/"), ' ', $str);
        $str = trim($str);
        return $str;
    }

//    public static function is_class($name) {
//        if (strpos($name, '::') !== false || strpos($name, '->') !== false) {
//            return true;
//        } else {
//            return false;
//        }
//    }

//    public static function rewrite_names($name) {
//        global $rewritename;
//        $lname = strtolower($name);
//        foreach ($rewritename as $pattern => $newName) {
//            if (substr($lname, 0, strlen($pattern)) == $pattern) {
//                $name = substr($newName, 0, strlen($pattern)) . substr($name, strlen($pattern));
//            }
//        }
//        return $name;
//    }



    public static function clearSourceCode($source) {
        $source = trim(html_entity_decode(strip_tags(str_replace(array('<br />', '<br/>', '<br>'), "\n", $source))));

        if (substr($source, 0, 5) == '<?php') {
            $source = substr($source, 6);
        }
        if (substr($source, -2) == '?>') {
            $source = substr($source, 0, -2);
        }
        // replace weird spaces with normal spaces
        $source = str_replace(chr(194) . chr(160), ' ', $source);

        return trim($source);
    }

    public static function extractFormattedText($elms, $xpath = null) {
        $textParts = array();

        if ($elms->length == 0) {
            return null;
        }
        $dom = new \DOMDocument();

        for ($i = 0; $i < $elms->length; $i++) {
            if ($elms->item($i)->tagName == 'ul') {
                $pText = self::extractFormattedText($xpath->query('./li', $elms->item($i)), $xpath);
            } else if ($elms->item($i)->tagName == 'table') {
                $pText = self::extractFormattedText($xpath->query('./tbody/tr', $elms->item($i)), $xpath);
            } else {
                $nodeCopy = $dom->importNode($elms->item($i), true);
                $pText = $dom->saveXML($nodeCopy);
            }

            $pText = html_entity_decode(strip_tags(str_replace(array('<strong><code>', '<var class="varname">', '</var>', '</code></strong>', '<em><code class="parameter">', '</code></em>', '<code>', '<code class="parameter">', '<code class="lang">', '</code>', '<em>', '</em>'), '`', $pText)));

            if ($pText == 'Object oriented style' || $pText == 'Procedural style') {
                continue;
            }

            // looks crazy but this matches all words not surrounded by ` and escapes it
            $pText = preg_replace_callback('/(\b(?<![`])[a-zA-Z_][a-zA-Z_0-9]*\b(?![`]))/i', function($subject) {
                // escape '_' and '*' to avoid malforming function names like 'html_entity_decode' by markdown
                return str_replace(array('_', '*'), array('\_', '\*'), $subject[0]);
            }, $pText);

            if (strpos($pText, 'Warning') != 0) {
                $pText = str_replace('Warning:', '**Warning**:', $pText);
            }

            if (strpos($pText, 'Note') == 0) {
                $pText = str_replace('Note:', '**Note**:', $pText);
            }

            $pText = trim($pText);

            if ($pText) {
                $textParts[] = $pText;
            }
        }

        return self::simplifyString(implode('\n\n', $textParts));
    }

    static public function convertSize($size) {
        // http://stackoverflow.com/questions/11807115/php-convert-kb-mb-gb-tb-etc-to-bytes
        $number = substr($size, 0, -1);
        switch(strtoupper(substr($size,-1))){
            case "K":
                return $number * 1024;
            case "M":
                return $number * pow(1024,2);
            case "G":
                return $number * pow(1024,3);
            default:
                return $size;
        }
    }

//    static public function toFixedArray($array) {
//        $fixed = \SplFixedArray::fromArray($array, true);
//
//        foreach ($fixed as $key => $value) {
//            if (is_array($value)) {
//                $fixed[$key] = self::toFixedArray($value);
//            }
//        }
//        return $fixed;
//    }
}
