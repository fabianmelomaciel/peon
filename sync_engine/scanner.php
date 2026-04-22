<?php
/**
 * SYNC ENGINE | Scanner Module v4.0 (Ultra-Low Memory)
 * Optimización extrema: Salto de directorios excluidos y filtrado por fecha.
 */

function get_managed_projects() {
    $base = PROJECTS_BASE_DIR;
    if (!is_dir($base)) return [];
    
    $dirs = array_diff(scandir($base), ['.', '..']);
    $projects = [];
    
    foreach ($dirs as $dir) {
        $path = $base . DIRECTORY_SEPARATOR . $dir;
        if (is_dir($path)) {
            if (file_exists($path . '/.env') || file_exists($path . '/index.php')) {
                $projects[] = [
                    'name' => $dir,
                    'path' => $path,
                    'last_sync' => date('Y-m-d H:i', filemtime($path))
                ];
            }
        }
    }
    return $projects;
}

function scan_projects($isStream = false) {
    return get_managed_projects();
}

/**
 * Escáner Táctico con Poda de Directorios (v4.0)
 */
function scan_local_dir_recursive($dir, $exclude, $since = 0) {
    $files = [];
    $baseDir = rtrim(str_replace('\\', '/', $dir), '/');
    $scanCount = 0;

    if (!is_dir($dir)) return [];

    $directory = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    
    // Filtro de Exclusión Recursivo (Poda de directorios)
    $filter = new RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) use ($exclude, $baseDir) {
        $relativePath = ltrim(str_replace($baseDir, '', str_replace('\\', '/', $current->getPathname())), '/');
        
        foreach ($exclude as $ex) {
            if (empty($ex)) continue;
            if ($relativePath === $ex || strpos($relativePath, $ex . '/') === 0) {
                return false; // NO ENTRAR NI ESCANEARESTE DIRECTORIO/ARCHIVO
            }
        }
        return true;
    });

    $iterator = new RecursiveIteratorIterator($filter);

    foreach ($iterator as $path => $fileinfo) {
        if ($fileinfo->isDir()) continue;

        $mtime = $fileinfo->getMTime();
        
        // FILTRADO POR FECHA (Solo si se solicita)
        if ($since > 0 && $mtime < $since) continue;

        $path = str_replace('\\', '/', $path);
        $relativePath = ltrim(str_replace($baseDir, '', $path), '/');
        
        $files[$relativePath] = $mtime;
        $scanCount++;

        // Limpiar memoria cada 1000 archivos
        if ($scanCount % 1000 === 0) {
            gc_collect_cycles();
        }
    }

    return $files;
}
