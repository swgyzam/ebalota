<?php
// config.php
define('BASE_PATH', __DIR__); // Gets current directory automatically

// Upload directories (relative paths)
define('PROFILE_PIC_DIR', BASE_PATH . '/uploads/profile_pictures/');
define('CREDENTIALS_DIR', BASE_PATH . '/uploads/credentials/');

// URL paths (for displaying files)
define('BASE_URL', 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/ebalota/');
define('PROFILE_PIC_URL', BASE_URL . 'uploads/profile_pictures/');
define('CREDENTIALS_URL', BASE_URL . 'uploads/credentials/');
?>