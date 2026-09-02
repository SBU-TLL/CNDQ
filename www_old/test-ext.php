<?php
echo json_encode([
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'ini' => php_ini_loaded_file(),
    'php_binary' => PHP_BINARY,
    'extension_dir' => ini_get('extension_dir'),
    'auto_prepend' => ini_get('auto_prepend_file'),
    'cwd' => getcwd(),
]);
