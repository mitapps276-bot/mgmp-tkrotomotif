<?php
$files = glob("c:/xampp/htdocs/mgmp-platform/*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Replace isset($var) ? $var : 'default' with isset($var) ? $var : 'default'
    // This regex captures $array['key'] or $var before the ?? and the default value after it
    $new_content = preg_replace('/(\$[a-zA-Z0-9_]+(?:\[[\'"][a-zA-Z0-9_]+[\'"]\])?)\s*\?\?\s*([^;,\)]+)/', 'isset($1) ? $1 : $2', $content);
    if ($content !== $new_content) {
        file_put_contents($file, $new_content);
        echo "Modified: $file\n";
    }
}
