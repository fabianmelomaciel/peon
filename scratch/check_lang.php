<?php
$langs = ['es.js', 'en.js', 'pt.js'];
$basePath = 'c:/laragon/www/peon/lang/';
$keys = [];

foreach ($langs as $file) {
    if (!file_exists($basePath . $file)) continue;
    $content = file_get_contents($basePath . $file);
    preg_match_all('/([a-z0-9_]+):\s*["\']/i', $content, $matches);
    $keys[$file] = $matches[1];
}

$allKeys = array_unique(array_merge(...array_values($keys)));
echo "Total Unique Keys: " . count($allKeys) . "\n\n";

foreach ($langs as $file) {
    $missing = array_diff($allKeys, $keys[$file]);
    if (!empty($missing)) {
        echo "Missing in $file:\n";
        print_r($missing);
    } else {
        echo "Perfect Parity for $file\n";
    }
}
