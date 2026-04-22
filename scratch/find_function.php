<?php
$files = ['projects_sync.php', 'data_sync.php', 'core.php', 'index.php', 'header.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    if (strpos($content, 'function scan_projects') !== false) {
        echo "FOUND in $file\n";
    }
}
