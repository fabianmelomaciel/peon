<?php
require_once 'c:/laragon/www/peon/env_discovery.php';
require_once 'c:/laragon/www/peon/vault.php';
require_once 'c:/laragon/www/peon/data_sync.php';

$diag = PeonEnv::getDiagnostics();
echo "--- DIAGNOSTICS ---\n";
print_r($diag);

$baseDir = !empty($diag['root']) ? $diag['root'] : (dirname(__DIR__));
echo "\n--- PROJECTS_BASE_DIR ---\n";
echo $baseDir . "\n";

echo "\n--- SCANDIR OF BASE_DIR ---\n";
$scan = @array_diff(scandir($baseDir), ['.', '..']);
print_r($scan);

echo "\n--- MANAGED PROJECTS ---\n";
$projects = get_managed_projects();
print_r($projects);
