<?php
$errors = [];
ob_start();
if (!extension_loaded('pdo_sqlite')) {
    if (@dl('php_pdo_sqlite.dll')) {
        $errors[] = 'loaded via dl()';
    }
}
ob_end_clean();
echo json_encode([
    'timestamp' => time(),
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'ext_dir' => ini_get('extension_dir'),
    'ini' => php_ini_loaded_file(),
    'errors' => error_get_last(),
    'php_binary' => PHP_BINARY,
]);
