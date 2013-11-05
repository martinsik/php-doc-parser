<?php

function get_classes($dir) {
    $files = [];

    foreach (glob($dir . '/class.*') as $file) {
        preg_match('/\.([a-zA-Z0-9_\-]+)\./', $file, $matches);
        // use assoc array for performance reasons
        $files[$matches[1]] = true;
    }

    return $files;
}
