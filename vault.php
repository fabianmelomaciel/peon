<?php
/**
 * PEON VAULT | Shielding Service
 * Encrypts and decrypts sensitive environment variables.
 */

class PeonVault {
    private static $key = 'sixlan_secret_salt_2026'; // Consolidated with Sixlan API
    private static $method = 'aes-256-cbc';

    public static function isEncrypted($val) {
        return (strpos($val, 'ENC(') === 0 && substr($val, -1) === ')');
    }

    public static function encrypt($data) {
        if (self::isEncrypted($data)) return $data;
        if (empty($data)) return '';
        
        $ivLen = openssl_cipher_iv_length(self::$method);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $encrypted = openssl_encrypt($data, self::$method, self::$key, 0, $iv);
        
        return 'ENC(' . base64_encode($iv . $encrypted) . ')';
    }

    public static function decrypt($data) {
        if (!self::isEncrypted($data)) return $data;
        
        $data = substr($data, 4, -1);
        $decoded = base64_decode($data);
        
        $ivLen = openssl_cipher_iv_length(self::$method);
        if (strlen($decoded) < $ivLen) return '';
        
        $iv = substr($decoded, 0, $ivLen);
        $encrypted = substr($decoded, $ivLen);
        
        return openssl_decrypt($encrypted, self::$method, self::$key, 0, $iv);
    }
}
?>
