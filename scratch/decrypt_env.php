<?php
require_once 'vault.php';
$host_enc = 'rfJXdY5PggIkgWm6vdhvyHYrMEpSZTEwcnJPcUwyQ0dGWUlZOWc9PQ==';
$user_enc = 'MpiKl9MHx+FWQ2uNHoPwR3l1ejI2YjhNNHlqNXBTUHkxUHNXRm8xUmpDeUZYSkV3Nk13eHdyeGFFL0k9';
$pass_enc = '4S0SWizz2i4/fVSimPwYvktXSHJzZEV5SDVsMTNKZ1hMYXJ0WEE9PQ==';

echo "HOST: " . PeonVault::decrypt($host_enc) . "\n";
echo "USER: " . PeonVault::decrypt($user_enc) . "\n";
echo "PASS: " . PeonVault::decrypt($pass_enc) . "\n";
