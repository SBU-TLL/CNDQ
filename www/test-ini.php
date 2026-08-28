<?php
echo "PHPRC env: " . getenv('PHPRC') . "\n";
echo "ini loaded: " . var_export(php_ini_loaded_file(), true) . "\n";
echo "auto_prepend: " . ini_get('auto_prepend_file') . "\n";
echo "ext_dir: " . ini_get('extension_dir') . "\n";
