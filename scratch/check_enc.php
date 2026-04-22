<?php
require_once 'vault.php';
$pass = 'Sixlan.500380';
$enc = PeonVault::encrypt($pass);
echo "Pass: $pass\n";
echo "Enc: $enc\n";

$dec = PeonVault::decrypt($enc);
echo "Dec: $dec\n";

if ($pass === $dec) echo "MATCH!\n";
else echo "FAIL!\n";

// Check the one in .env
$envEnc = 'ENC(bm3oQ0L2DqnpcLT8s7lGqFo1VkdZSkh3WFpFcWtOMFRKMm9IN2c9PQ==)';
$decrypted = PeonVault::decrypt($envEnc);
echo "EnvEnc Decrypted: [$decrypted]\n";
if ($decrypted === $pass) echo "ENV MATCH!\n";
else {
    echo "ENV FAIL!\n";
    echo "Expected: [$pass]\n";
}
