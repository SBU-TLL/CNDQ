<?php
echo json_encode([
    'PHPRC' => getenv('PHPRC'),
    'ini' => php_ini_loaded_file(),
    'ini_all' => php_ini_scanned_files(),
    'extension_dir' => ini_get('extension_dir'),
]);
