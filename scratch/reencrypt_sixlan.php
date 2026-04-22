<?php
require_once 'vault.php';
$host = trim('ftp.sixlan.com');
$user = trim('u892879089.sixlan');
$pass = trim('Sixlan.500380');

echo "NEW_HOST: " . PeonVault::encrypt($host) . "\n";
echo "NEW_USER: " . PeonVault::encrypt($user) . "\n";
echo "NEW_PASS: " . PeonVault::encrypt($pass) . "\n";
