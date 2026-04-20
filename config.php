<?php
/**
 * PEÓN CORE CONFIGURATION
 * Security settings and environment variables for the control panel.
 */

define('PEON_VERSION', '1.0.0-PRO');

// Financial / Payments
define('PAY_PAL_CLIENT_ID', 'AftxB-NkFlpSYwKeXcNwMPAZ8wIujMeprnpq1lIJeajruhpDzbgUIWNazMb-uqUOYd-eLSoYdUuS8vml');
define('PAY_PAL_CURRENCY', 'USD');
define('PEON_PLAN_PRICE', '49.99');

// Security & Vault
define('VAULT_PEPPER', 'peon_tactical_pepper_2026');

// Remote Hub
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'sixlan.com') !== false) {
    define('SIXLAN_HUB_URL', 'https://sixlan.com/api/license.php');
} else {
    define('SIXLAN_HUB_URL', 'http://localhost/sixlan/api/license.php');
}

?>
