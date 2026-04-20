<?php
/**
 * Peón Discovery Engine v1.5
 * Inteligencia de detección de entorno y resolución de rutas dinámicas.
 * 100% Adaptive - Zero Hardcoding Policy.
 */

class PeonEnv {
    private static $detected = null;

    public static function getDiagnostics() {
        if (self::$detected === null) self::discover();
        return self::$detected;
    }

    public static function getUserName() {
        return getenv('USERNAME') ?: getenv('USER') ?: 'CEO_STRATEGIC';
    }

    public static function getHomeDir() {
        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: (getenv('HOMEDRIVE') . getenv('HOMEPATH'));
        return str_replace('\\', '/', $home);
    }

    public static function discover() {
        $os = PHP_OS_FAMILY; 
        $env = [
            'os' => $os,
            'hostname' => gethostname(),
            'user' => self::getUserName(),
            'home' => self::getHomeDir(),
            'current_path' => str_replace('\\', '/', __DIR__),
            'type' => 'unknown',
            'root' => '',
            'mysql_bin' => '',
            'mysqldump_bin' => '',
            'skills_path' => '',
            'peon_md_exists' => false,
            'docker_installed' => false,
            'is_container' => false,
            'version' => 'N/D'
        ];

        // Check if Docker is available
        $dockerCheck = ($os === 'Windows') ? @shell_exec('where docker 2>nul') : @shell_exec('which docker 2>/dev/null');
        $env['docker_installed'] = !empty($dockerCheck);
        $env['is_container'] = file_exists('/.dockerenv') || @file_exists('/proc/self/cgroup') && @strpos(file_get_contents('/proc/self/cgroup'), 'docker') !== false;

        if ($os === 'Windows') {
            self::probeWindows($env);
        } else {
            self::probeUnix($env);
        }

        if (empty($env['skills_path'])) {
            $env['skills_path'] = $env['home'] . "/.gemini/antigravity/skills";
        }

        // Detección de PEON.md en la raíz del servidor
        if (!empty($env['root'])) {
            $env['peon_md_exists'] = file_exists($env['root'] . '/PEON.md');
        }

        self::$detected = $env;
        return $env;
    }

    private static function probeWindows(&$env) {
        $paths = [
            'Laragon' => 'C:/laragon',
            'WAMP' => 'C:/wamp64',
            'XAMPP' => 'C:/xampp'
        ];

        foreach ($paths as $type => $base) {
            if (is_dir($base)) {
                $env['type'] = $type;
                if ($type === 'Laragon') {
                    $env['root'] = "$base/www";
                    $mysqlBase = "$base/bin/mysql";
                    if (is_dir($mysqlBase)) {
                        $versions = array_diff(scandir($mysqlBase), ['.', '..']);
                        rsort($versions);
                        foreach ($versions as $v) {
                            $bin = "$mysqlBase/$v/bin";
                            if (file_exists("$bin/mysqldump.exe")) {
                                $env['mysqldump_bin'] = "$bin/mysqldump.exe";
                                $env['mysql_bin'] = "$bin/mysql.exe";
                                $env['version'] = $v;
                                break;
                            }
                        }
                    }
                } elseif ($type === 'WAMP') {
                    $env['root'] = "$base/www";
                    $mysqlBase = "$base/bin/mysql";
                    if (is_dir($mysqlBase)) {
                        $versions = array_diff(scandir($mysqlBase), ['.', '..']);
                        rsort($versions);
                        foreach ($versions as $v) {
                             $bin = "$mysqlBase/$v/bin";
                             if (file_exists("$bin/mysqldump.exe")) {
                                 $env['mysqldump_bin'] = "$bin/mysqldump.exe";
                                 $env['mysql_bin'] = "$bin/mysql.exe";
                                 break;
                             }
                        }
                    }
                } elseif ($type === 'XAMPP') {
                    $env['root'] = "$base/htdocs";
                    $bin = "$base/mysql/bin";
                    if (file_exists("$bin/mysqldump.exe")) {
                        $env['mysqldump_bin'] = "$bin/mysqldump.exe";
                        $env['mysql_bin'] = "$bin/mysql.exe";
                    }
                }
                break;
            }
        }
        
        // Fallback si no se detecta suite de servidor
        if (empty($env['root'])) {
            $env['root'] = str_replace('\\', '/', dirname(__DIR__));
        }
    }

    private static function probeUnix(&$env) {
        $env['root'] = '/var/www/html';
        $env['mysqldump_bin'] = @trim(`which mysqldump` ?? '');
        $env['mysql_bin'] = @trim(`which mysql` ?? '');
        
        if (is_dir('/Applications/MAMP')) {
            $env['type'] = 'MAMP (macOS)';
            $env['root'] = '/Applications/MAMP/htdocs';
            $env['mysqldump_bin'] = '/Applications/MAMP/Library/bin/mysqldump';
            $env['mysql_bin'] = '/Applications/MAMP/Library/bin/mysql';
        } elseif (is_dir('/opt/lampp')) {
            $env['type'] = 'XAMPP (Linux)';
            $env['root'] = '/opt/lampp/htdocs';
            $env['mysqldump_bin'] = '/opt/lampp/bin/mysqldump';
            $env['mysql_bin'] = '/opt/lampp/bin/mysql';
        }
    }

    public static function applyOverrides($globalEnv) {
        if (self::$detected === null) self::discover();
        if (isset($globalEnv['CUSTOM_MYSQLDUMP'])) self::$detected['mysqldump_bin'] = $globalEnv['CUSTOM_MYSQLDUMP'];
        if (isset($globalEnv['CUSTOM_MYSQL'])) self::$detected['mysql_bin'] = $globalEnv['CUSTOM_MYSQL'];
        if (isset($globalEnv['CUSTOM_ROOT'])) self::$detected['root'] = $globalEnv['CUSTOM_ROOT'];
        if (isset($globalEnv['CUSTOM_SKILLS'])) self::$detected['skills_path'] = $globalEnv['CUSTOM_SKILLS'];
        
        if (!empty(self::$detected['root'])) {
            self::$detected['peon_md_exists'] = file_exists(self::$detected['root'] . '/PEON.md');
        }
    }

    public static function getBinaryPath($key) {
        $diag = self::getDiagnostics();
        return $key === 'mysql' ? $diag['mysql_bin'] : $diag['mysqldump_bin'];
    }

    public static function getProjectsRoot() {
        $diag = self::getDiagnostics();
        return $diag['root'];
    }
}

