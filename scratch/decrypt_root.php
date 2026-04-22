<?php
require_once 'vault.php';
$root_enc = 'ENC(kqJJm9/rsi9takt8TF4bPVMxY1hiVExJYndKb24wZSthWWJaS0E9PQ==)';
echo "ROOT: " . PeonVault::decrypt($root_enc) . "\n";
